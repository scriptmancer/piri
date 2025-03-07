<?php

declare(strict_types=1);

namespace Piri\Core;

use Piri\Contracts\RouterInterface;
use Piri\Contracts\MiddlewareInterface;
use Piri\Exceptions\RouteNotFoundException;
use Piri\Attributes\Route as RouteAttribute;
use Piri\Attributes\RouteGroup as RouteGroupAttribute;
use ReflectionClass;
use ReflectionMethod;

class Router implements RouterInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * @var array<MiddlewareInterface>
     */
    private array $globalMiddleware = [];

    /**
     * @var string
     */
    private string $prefix = '';

    /**
     * @var array<MiddlewareInterface>
     */
    private array $groupMiddleware = [];

    /**
     * @var array<string, bool>
     */
    private array $optionalParameters = [];

    /**
     * @var RouteCache|null
     */
    private ?RouteCache $cache = null;

    /**
     * @var string|null
     */
    private ?string $cacheDir = null;

    /**
     * @var array<string>
     */
    private array $routeFiles = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $groups = [];

    /**
     * Register a new route
     *
     * @param string $method
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @param string|null $group
     * @return void
     */
    public function add(string $method, string $path, callable|array|string $handler, ?string $name = null, ?string $group = null): void
    {
        $this->optionalParameters = [];
        $path = $this->prefix . $path;
        $routeData = [
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, []),
            'pattern' => $this->buildPattern($path),
            'optionalParameters' => $this->optionalParameters,
            'group' => $group
        ];

        $this->routes[strtoupper($method)][$path] = $routeData;

        if ($name !== null && $name !== '') {
            $existingRoute = Route::getByName($name);
            if ($existingRoute !== null && $existingRoute['path'] !== $path) {
                throw new \RuntimeException("Duplicate route name: {$name}");
            }
            if ($existingRoute === null) {
                Route::addNamed($name, $path, $routeData);
            }
        }
    }

    /**
     * Create a route group
     *
     * @param array{prefix?: string, middleware?: array<MiddlewareInterface|string>, name?: string} $attributes
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Store current group state
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // Set new group state
        $prefix = $attributes['prefix'] ?? '';
        $this->prefix .= $prefix;
        
        // Handle middleware
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge(
                $this->groupMiddleware,
                $this->normalizeMiddleware($attributes['middleware'])
            );
        }
        
        // If the group has a name, register it in our groups registry
        if (isset($attributes['name']) && !empty($attributes['name'])) {
            $groupName = $attributes['name'];
            // Store the group information
            $this->groups[$groupName] = [
                'prefix' => $this->prefix,
                'middleware' => $this->groupMiddleware
            ];
        }

        // Execute the group callback
        $callback($this);

        // Restore previous group state
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add middleware to a specific route
     *
     * @param string $method
     * @param string $path
     * @param MiddlewareInterface $middleware
     * @return void
     * @throws RouteNotFoundException
     */
    public function addMiddleware(string $method, string $path, MiddlewareInterface $middleware): void
    {
        $method = strtoupper($method);
        $path = $this->prefix . $path;
        
        if (!isset($this->routes[$method][$path])) {
            throw new RouteNotFoundException(
                sprintf('Cannot add middleware - route not found: %s %s', $method, $path)
            );
        }

        $this->routes[$method][$path]['middleware'][] = $middleware;
    }

    /**
     * Add global middleware
     *
     * @param MiddlewareInterface $middleware
     * @return void
     */
    public function addGlobalMiddleware(MiddlewareInterface $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Execute middleware chain
     *
     * @param array<MiddlewareInterface> $middleware
     * @param callable|array $handler
     * @param array $parameters
     * @return mixed
     */
    private function executeMiddlewareChain(array $middleware, callable|array $handler, array $parameters): mixed
    {
        if (empty($middleware)) {
            if (is_array($handler)) {
                // If the handler is a class method, check the parameter types
                if (is_object($handler[0]) && method_exists($handler[0], $handler[1])) {
                    $reflection = new \ReflectionMethod($handler[0], $handler[1]);
                    $reflectionParams = $reflection->getParameters();
                    
                    // Convert parameters to the correct types
                    foreach ($parameters as $name => $value) {
                        foreach ($reflectionParams as $param) {
                            if ($param->getName() === $name && $param->hasType()) {
                                $type = $param->getType();
                                
                                // Convert to the correct type
                                if ($type instanceof \ReflectionNamedType) {
                                    $typeName = $type->getName();
                                    
                                    switch ($typeName) {
                                        case 'int':
                                            $parameters[$name] = (int) $value;
                                            break;
                                        case 'float':
                                            $parameters[$name] = (float) $value;
                                            break;
                                        case 'bool':
                                            $parameters[$name] = (bool) $value;
                                            break;
                                        case 'string':
                                            $parameters[$name] = (string) $value;
                                            break;
                                        case 'array':
                                            $parameters[$name] = (array) $value;
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }
                
                return call_user_func_array($handler, $parameters);
            }
            
            return $handler($parameters);
        }

        $next = function (array $params) use ($middleware, $handler) {
            return $this->executeMiddlewareChain(
                array_slice($middleware, 1),
                $handler,
                $params
            );
        };

        return $middleware[0]->handle($next, $parameters);
    }

    /**
     * Find a route for the given method and path
     *
     * @param string $method
     * @param string $path
     * @return array<string, mixed>
     * @throws RouteNotFoundException
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        
        // Handle HEAD requests by trying GET route
        if ($method === 'HEAD' && !isset($this->routes[$method])) {
            $method = 'GET';
        }

        if (!isset($this->routes[$method])) {
            throw new RouteNotFoundException(
                sprintf('No route found for %s %s', $method, $path)
            );
        }

        // Sort routes by specificity (static routes first)
        $routes = $this->routes[$method];
        uksort($routes, function ($a, $b) {
            // Count the number of static segments
            $aStatic = substr_count($a, '/') - substr_count($a, '{');
            $bStatic = substr_count($b, '/') - substr_count($b, '{');
            
            // If different number of static segments, more static segments = higher priority
            if ($aStatic !== $bStatic) {
                return $bStatic - $aStatic;
            }
            
            // If same number of static segments, longer path = higher priority
            return strlen($b) - strlen($a);
        });

        // First try to match the exact path
        foreach ($routes as $routePath => $routeData) {
            if (preg_match($routeData['pattern'], $path, $matches)) {
                // Extract named parameters
                $parameters = $this->extractParameters($matches, $routeData['optionalParameters']);
                $routeData['parameters'] = $parameters;

                // Add global middleware to route middleware
                $routeData['middleware'] = array_merge(
                    $this->globalMiddleware,
                    $routeData['middleware']
                );

                return $routeData;
            }
        }
        
        // If no match found, try to match group routes
        foreach ($this->groups as $groupName => $groupData) {
            $groupPrefix = $groupData['prefix'];
            
            // Check if the path starts with the group prefix
            if (strpos($path, $groupPrefix) === 0) {
                // Get the path without the group prefix
                $pathWithoutPrefix = substr($path, strlen($groupPrefix));
                
                // If the path is empty, use '/'
                if ($pathWithoutPrefix === '') {
                    $pathWithoutPrefix = '/';
                }
                
                // Try to match the path without the group prefix
                foreach ($routes as $routePath => $routeData) {
                    if (isset($routeData['group']) && $routeData['group'] === $groupName) {
                        // Build a pattern for the path without the group prefix
                        $pattern = $this->buildPattern($pathWithoutPrefix);
                        
                        if (preg_match($pattern, $pathWithoutPrefix, $matches)) {
                            // Extract named parameters
                            $parameters = $this->extractParameters($matches, $routeData['optionalParameters']);
                            $routeData['parameters'] = $parameters;

                            // Add global middleware to route middleware
                            $routeData['middleware'] = array_merge(
                                $this->globalMiddleware,
                                $routeData['middleware']
                            );

                            return $routeData;
                        }
                    }
                }
            }
        }

        throw new RouteNotFoundException(
            sprintf('No route found for %s %s', $method, $path)
        );
    }

    /**
     * Execute a route with its middleware chain
     *
     * @param array<string, mixed> $routeData
     * @return mixed
     */
    public function execute(array $routeData): mixed
    {
        $handler = $routeData['handler'];
        $middleware = $routeData['middleware'];
        $parameters = $routeData['parameters'] ?? [];

        if (is_array($handler)) {
            $handler = [$handler[0], $handler[1]];
            
            // If the handler is a class method, check the parameter types
            if (is_object($handler[0]) && method_exists($handler[0], $handler[1])) {
                $reflection = new \ReflectionMethod($handler[0], $handler[1]);
                $reflectionParams = $reflection->getParameters();
                
                // Convert parameters to the correct types
                foreach ($parameters as $name => $value) {
                    foreach ($reflectionParams as $param) {
                        if ($param->getName() === $name && $param->hasType()) {
                            $type = $param->getType();
                            
                            // Handle built-in types
                            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                                // Skip non-builtin types (classes, interfaces, etc.)
                                continue;
                            }
                            
                            // Convert to the correct type
                            if ($type instanceof \ReflectionNamedType) {
                                $typeName = $type->getName();
                                
                                switch ($typeName) {
                                    case 'int':
                                        $parameters[$name] = (int) $value;
                                        break;
                                    case 'float':
                                        $parameters[$name] = (float) $value;
                                        break;
                                    case 'bool':
                                        $parameters[$name] = (bool) $value;
                                        break;
                                    case 'string':
                                        $parameters[$name] = (string) $value;
                                        break;
                                    case 'array':
                                        $parameters[$name] = (array) $value;
                                        break;
                                }
                            }
                        }
                    }
                }
            }
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $handler = [new $class(), $method];
        }

        return $this->executeMiddlewareChain($middleware, $handler, $parameters);
    }

    /**
     * Build a regex pattern for route matching
     *
     * @param string $path
     * @return string
     */
    private function buildPattern(string $path): string
    {
        // Normalize the path to ensure it starts with a slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Split the path into segments
        $segments = array_filter(explode('/', $path));
        $pattern = '';

        foreach ($segments as $segment) {
            // Check if segment contains a parameter
            if (preg_match('/\{([^}]+)\}/', $segment, $matches)) {
                $paramConfig = $matches[1];
                
                // Check for regex pattern
                if (str_contains($paramConfig, ':')) {
                    [$paramName, $regex] = explode(':', $paramConfig);
                } else {
                    $paramName = $paramConfig;
                    $regex = '[^/]+';
                }
                
                // Remove optional marker if present
                if (str_ends_with($paramName, '?')) {
                    $paramName = rtrim($paramName, '?');
                    $this->optionalParameters[$paramName] = true;
                    $pattern .= '(?:/(?P<' . $paramName . '>' . $regex . '))?';
                } else {
                    $pattern .= '/(?P<' . $paramName . '>' . $regex . ')';
                }
            } else {
                $pattern .= '/' . preg_quote($segment, '#');
            }
        }

        // Handle root path
        if ($pattern === '') {
            $pattern = '/';
        }

        return '#^' . $pattern . '$#';
    }

    /**
     * Extract named parameters from regex matches
     *
     * @param array<string, string> $matches
     * @param array<string, bool> $optionalParams
     * @return array<string, string|null>
     */
    private function extractParameters(array $matches, array $optionalParams): array
    {
        $parameters = array_filter(
            $matches,
            fn($key) => !is_numeric($key),
            ARRAY_FILTER_USE_KEY
        );

        // Add null values for optional parameters that weren't matched
        foreach ($optionalParams as $param => $isOptional) {
            if (!isset($parameters[$param])) {
                $parameters[$param] = null;
            }
        }

        return $parameters;
    }

    /**
     * Add a GET route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function get(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $name);
    }

    /**
     * Add a POST route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function post(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $name);
    }

    /**
     * Add a PUT route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function put(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $name);
    }

    /**
     * Add a DELETE route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function delete(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $name);
    }

    /**
     * Add a PATCH route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function patch(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('PATCH', $path, $handler, $name);
    }

    /**
     * Add an OPTIONS route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function options(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('OPTIONS', $path, $handler, $name);
    }

    /**
     * Add a HEAD route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function head(string $path, callable|array|string $handler, ?string $name = null): void
    {
        $this->add('HEAD', $path, $handler, $name);
    }

    /**
     * Enable route caching
     *
     * @param string $cacheDir Directory to store cache files
     * @return self
     */
    public function enableCache(string $cacheDir): self
    {
        $this->cache = new RouteCache();
        $this->cacheDir = $cacheDir;
        return $this;
    }

    /**
     * Add a route file to watch for cache invalidation
     *
     * @param string $file
     * @return self
     */
    public function addRouteFile(string $file): self
    {
        $this->routeFiles[] = $file;
        return $this;
    }

    /**
     * Clear the route cache
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        if ($this->cache === null || $this->cacheDir === null) {
            return false;
        }

        return $this->cache->clear($this->cacheDir);
    }

    /**
     * Load routes from cache or build them
     *
     * @return bool True if routes were loaded from cache
     */
    private function loadRoutes(): bool
    {
        // If caching is not enabled or no cache directory is set, return
        if ($this->cache === null || $this->cacheDir === null) {
            return false;
        }

        // If we already have routes (from registration), don't load from cache
        if (!empty($this->routes)) {
            return false;
        }

        // Try to load from cache
        $routes = $this->cache->load($this->cacheDir);
        if ($routes !== null && !empty($routes)) {
            $this->routes = $routes;
            return true;
        }

        return false;
    }

    /**
     * Handle the current request and return the response
     * 
     * This method provides a streamlined way to handle HTTP requests
     * with minimal boilerplate code in your application.
     *
     * @param array $options Configuration options
     *                      - 'cache_dir': Directory for route caching
     *                      - 'debug': Enable debug mode (default: false)
     *                      - 'json_options': JSON encoding options (default: JSON_PRETTY_PRINT)
     *                      - 'error_handler': Custom error handler callable
     * @return void
     */
    public function handle(array $options = []): void
    {
        // Set up options with defaults
        $options = array_merge([
            'cache_dir' => null,
            'debug' => false,
            'json_options' => JSON_PRETTY_PRINT,
            'error_handler' => null,
        ], $options);
        
        // Enable cache if directory is provided
        if ($options['cache_dir'] !== null) {
            $this->enableCache($options['cache_dir']);
        }
        
        try {
            // Get the request method and path
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            
            // Handle base path if the application is in a subdirectory
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $scriptPath = dirname($scriptName);
            
            if ($scriptPath !== '/' && $scriptPath !== '\\') {
                $baseUrl = rtrim($scriptPath, '/');
                if (strpos($path, $baseUrl) === 0) {
                    $path = substr($path, strlen($baseUrl));
                }
            }
            
            // Ensure path starts with a slash
            $path = '/' . ltrim($path, '/');
            
            // Match the route
            $route = $this->match($method, $path);
            
            // Execute the route
            $result = $this->execute($route);
            
            // Handle the response
            if (is_array($result) || is_object($result)) {
                header('Content-Type: application/json');
                echo json_encode($result, $options['json_options']);
            } else {
                echo $result;
            }
        } catch (\Exception $e) {
            // Use custom error handler if provided
            if (is_callable($options['error_handler'])) {
                call_user_func($options['error_handler'], $e);
                return;
            }
            
            // Default error handling
            if ($e instanceof RouteNotFoundException) {
                http_response_code(404);
                if ($options['debug']) {
                    echo json_encode([
                        'error' => '404 Not Found',
                        'message' => $e->getMessage(),
                        'path' => $path ?? null,
                        'method' => $method ?? null,
                        'available_routes' => array_keys($this->routes[$method] ?? [])
                    ], $options['json_options']);
                } else {
                    echo json_encode([
                        'error' => '404 Not Found',
                        'message' => 'The requested resource was not found'
                    ], $options['json_options']);
                }
            } else {
                http_response_code(500);
                if ($options['debug']) {
                    echo json_encode([
                        'error' => 'Internal Server Error',
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ], $options['json_options']);
                } else {
                    echo json_encode([
                        'error' => 'Internal Server Error',
                        'message' => 'An unexpected error occurred'
                    ], $options['json_options']);
                }
            }
        }
    }

    /**
     * Register routes from a class using attributes
     *
     * @param string|object $class
     * @return void
     */
    public function registerClass(string|object $class): void
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : new ReflectionClass($class::class);
        $instance = is_string($class) ? new $class() : $class;

        // Check for RouteGroup attribute first
        $routeGroupAttr = $reflection->getAttributes(RouteGroupAttribute::class)[0] ?? null;
        $routeGroupInstance = $routeGroupAttr?->newInstance();
        
        // If RouteGroup attribute exists, register it as a group
        if ($routeGroupInstance !== null) {
            $groupName = $routeGroupInstance->getName();
            $groupPrefix = $routeGroupInstance->getPrefix();
            $groupMiddleware = $this->normalizeMiddleware($routeGroupInstance->getMiddleware());
            
            // Register the group
            if (!empty($groupName)) {
                $this->groups[$groupName] = [
                    'prefix' => $groupPrefix,
                    'middleware' => $groupMiddleware
                ];
            }
        }
        
        // Get class-level route attribute (for backward compatibility)
        $classRoute = $reflection->getAttributes(RouteAttribute::class)[0] ?? null;
        $classRouteInstance = $classRoute?->newInstance();
        
        $classPrefix = $classRouteInstance?->getPrefix() ?? '';
        $classMiddleware = $classRouteInstance?->getMiddleware() ?? [];
        
        // If RouteGroup exists, use its values instead
        if ($routeGroupInstance !== null) {
            $classPrefix = $routeGroupInstance->getPrefix();
            $classMiddleware = $routeGroupInstance->getMiddleware();
        }
        
        // Store current state
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // Apply class-level settings
        $this->prefix .= $classPrefix;
        $this->groupMiddleware = array_merge(
            $this->groupMiddleware,
            $this->normalizeMiddleware($classMiddleware)
        );

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);
            if (empty($attributes)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $methodMiddleware = $this->normalizeMiddleware($route->getMiddleware());
                $group = $route->getGroup();
                
                // If RouteGroup exists and the route doesn't specify a group, use the RouteGroup's name
                if (empty($group) && $routeGroupInstance !== null && !empty($routeGroupInstance->getName())) {
                    $group = $routeGroupInstance->getName();
                }
                
                // If the route belongs to a group, we need to handle it differently
                if (!empty($group) && isset($this->groups[$group])) {
                    // Get the group prefix and middleware
                    $groupPrefix = $this->groups[$group]['prefix'];
                    $groupMiddleware = $this->groups[$group]['middleware'];
                    
                    // Add the route with the group prefix
                    foreach ($route->getMethods() as $httpMethod) {
                        $path = $route->getPath();
                        $fullPath = $groupPrefix . $path;
                        
                        // Reset optional parameters
                        $this->optionalParameters = [];
                        
                        // Build the pattern
                        $pattern = $this->buildPattern($fullPath);
                        
                        // Add the route with the full path
                        $this->routes[strtoupper($httpMethod)][$fullPath] = [
                            'handler' => [$instance, $method->getName()],
                            'middleware' => array_merge(
                                $groupMiddleware,
                                $methodMiddleware
                            ),
                            'pattern' => $pattern,
                            'optionalParameters' => $this->optionalParameters,
                            'group' => $group
                        ];
                        
                        // Add to named routes if it has a name
                        $name = $route->getName();
                        if ($name !== null && $name !== '') {
                            $existingRoute = Route::getByName($name);
                            if ($existingRoute !== null && $existingRoute['path'] !== $fullPath) {
                                throw new \RuntimeException("Duplicate route name: {$name}");
                            }
                            if ($existingRoute === null) {
                                Route::addNamed($name, $fullPath, $this->routes[strtoupper($httpMethod)][$fullPath]);
                            }
                        }
                    }
                    continue;
                }
                
                // Regular route handling (no group or group not found)
                foreach ($route->getMethods() as $httpMethod) {
                    $this->add(
                        $httpMethod,
                        $route->getPath(),
                        [$instance, $method->getName()],
                        $route->getName(),
                        $route->getGroup()
                    );

                    if (!empty($methodMiddleware)) {
                        $fullPath = $this->prefix . $route->getPath();
                        if (isset($this->routes[strtoupper($httpMethod)][$fullPath])) {
                            $this->routes[strtoupper($httpMethod)][$fullPath]['middleware'] = array_merge(
                                $this->routes[strtoupper($httpMethod)][$fullPath]['middleware'],
                                $methodMiddleware
                            );
                        }
                    }
                }
            }
        }

        // Restore previous state
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        // Store routes in cache after registration if caching is enabled
        if ($this->cache !== null && $this->cacheDir !== null && !empty($this->routes)) {
            $this->cache->store($this->routes, $this->cacheDir);
        }
    }

    /**
     * Find the prefix for a named group
     *
     * @param string $groupName
     * @return string|null
     */
    private function findGroupPrefix(string $groupName): ?string
    {
        // Check if the group exists in our registry
        if (isset($this->groups[$groupName])) {
            return $this->groups[$groupName]['prefix'];
        }
        
        return null;
    }

    /**
     * Normalize middleware to array of MiddlewareInterface instances
     *
     * @param mixed $middleware
     * @return array<MiddlewareInterface>
     */
    private function normalizeMiddleware(mixed $middleware): array
    {
        if (empty($middleware)) {
            return [];
        }

        $middleware = (array) $middleware;
        return array_map(function ($m) {
            // If already an instance, return it
            if ($m instanceof MiddlewareInterface) {
                return $m;
            }

            // If a class name string, instantiate it
            if (is_string($m)) {
                if (!class_exists($m)) {
                    throw new \InvalidArgumentException("Middleware class not found: {$m}");
                }
                if (!is_subclass_of($m, MiddlewareInterface::class)) {
                    throw new \InvalidArgumentException("Middleware class must implement MiddlewareInterface: {$m}");
                }
                return new $m();
            }

            // If a callable, wrap it
            if (is_callable($m)) {
                return new class($m) implements MiddlewareInterface {
                    public function __construct(private $callback) {}
                    
                    public function handle(callable $next, array $parameters = []): mixed
                    {
                        return ($this->callback)($next, $parameters);
                    }
                };
            }

            throw new \InvalidArgumentException('Invalid middleware type: ' . (is_object($m) ? get_class($m) : gettype($m)));
        }, $middleware);
    }

    /**
     * Get all registered routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Register all controllers in a namespace
     *
     * @param string $namespace The namespace to register
     * @param string|null $directory The directory to scan for controllers (optional)
     * @param string $prefix The prefix to apply to all routes in this namespace
     * @param string $name The name of the group for all routes in this namespace
     * @param mixed $middleware The middleware to apply to all routes in this namespace
     * @return void
     */
    public function registerNamespace(
        string $namespace, 
        ?string $directory = null, 
        string $prefix = '', 
        string $name = '', 
        mixed $middleware = []
    ): void {
        // If directory is not provided, try to determine it from the autoloader
        if ($directory === null) {
            $directory = $this->getDirectoryFromNamespace($namespace);
            
            if ($directory === null) {
                throw new \RuntimeException(
                    "Could not determine directory for namespace '$namespace'. " .
                    "Please provide the directory parameter."
                );
            }
        }
        
        // Ensure the directory exists
        if (!is_dir($directory)) {
            throw new \RuntimeException("Directory '$directory' does not exist.");
        }
        
        // If a name is provided, register it as a group
        if (!empty($name)) {
            $normalizedMiddleware = $this->normalizeMiddleware($middleware);
            
            // Register the group
            $this->groups[$name] = [
                'prefix' => $prefix,
                'middleware' => $normalizedMiddleware
            ];
        }
        
        // Store current state
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // Apply namespace-level settings
        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge(
            $this->groupMiddleware,
            $this->normalizeMiddleware($middleware)
        );
        
        // Scan the directory for PHP files
        $files = $this->scanDirectory($directory);
        
        // Load and register each class in the namespace
        foreach ($files as $file) {
            // Get the class name from the file path
            $className = $this->getClassNameFromFile($file, $namespace, $directory);
            
            if ($className !== null) {
                // Check if the class exists and is not abstract
                if (class_exists($className)) {
                    $reflection = new \ReflectionClass($className);
                    
                    if (!$reflection->isAbstract() && !$reflection->isInterface() && !$reflection->isTrait()) {
                        // Check for RouteGroup attribute
                        $routeGroupAttr = $reflection->getAttributes(RouteGroupAttribute::class)[0] ?? null;
                        $routeGroupInstance = $routeGroupAttr?->newInstance();
                        
                        // If RouteGroup attribute exists, use its values
                        if ($routeGroupInstance !== null) {
                            // If namespace has a name, but the class has its own RouteGroup, skip the namespace group
                            $this->registerClass($className);
                        } else {
                            // If no RouteGroup attribute, but namespace has a name, apply it to the class
                            if (!empty($name)) {
                                // Create a new instance of the class
                                $instance = new $className();
                                
                                // Get all public methods with Route attributes
                                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                                    $attributes = $method->getAttributes(RouteAttribute::class);
                                    if (empty($attributes)) {
                                        continue;
                                    }
                                    
                                    foreach ($attributes as $attribute) {
                                        $route = $attribute->newInstance();
                                        $methodMiddleware = $this->normalizeMiddleware($route->getMiddleware());
                                        $routePath = $route->getPath();
                                        $routeName = $route->getName();
                                        $routeGroup = $route->getGroup();
                                        
                                        // If the route has its own group, use that instead of the namespace group
                                        $finalGroup = !empty($routeGroup) ? $routeGroup : $name;
                                        $finalPrefix = !empty($routeGroup) && isset($this->groups[$routeGroup]) ? 
                                            $this->groups[$routeGroup]['prefix'] : $prefix;
                                        
                                        // Add the route with the namespace group
                                        foreach ($route->getMethods() as $httpMethod) {
                                            $fullPath = $finalPrefix . $routePath;
                                            
                                            // Reset optional parameters
                                            $this->optionalParameters = [];
                                            
                                            // Build the pattern
                                            $pattern = $this->buildPattern($fullPath);
                                            
                                            // Add the route with the full path
                                            $this->routes[strtoupper($httpMethod)][$fullPath] = [
                                                'handler' => [$instance, $method->getName()],
                                                'middleware' => array_merge(
                                                    $normalizedMiddleware,
                                                    $methodMiddleware
                                                ),
                                                'pattern' => $pattern,
                                                'optionalParameters' => $this->optionalParameters,
                                                'group' => $finalGroup
                                            ];
                                            
                                            // Add to named routes if it has a name
                                            if ($routeName !== null && $routeName !== '') {
                                                $existingRoute = Route::getByName($routeName);
                                                if ($existingRoute !== null && $existingRoute['path'] !== $fullPath) {
                                                    throw new \RuntimeException("Duplicate route name: {$routeName}");
                                                }
                                                if ($existingRoute === null) {
                                                    Route::addNamed($routeName, $fullPath, $this->routes[strtoupper($httpMethod)][$fullPath]);
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                // Register the class normally
                                $this->registerClass($className);
                            }
                        }
                    }
                }
            }
        }
        
        // Restore previous state
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }
    
    /**
     * Get the directory path from a namespace using Composer's autoloader
     *
     * @param string $namespace
     * @return string|null
     */
    private function getDirectoryFromNamespace(string $namespace): ?string
    {
        // Normalize namespace (ensure it ends with a namespace separator)
        $namespace = rtrim($namespace, '\\') . '\\';
        
        // Get Composer's autoloader
        $autoloaders = spl_autoload_functions();
        
        foreach ($autoloaders as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0]) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                $classLoader = $autoloader[0];
                $prefixes = array_merge(
                    $classLoader->getPrefixesPsr4(),
                    $classLoader->getPrefixes()
                );
                
                // Find the longest matching namespace prefix
                $matchingPrefix = '';
                $matchingDir = null;
                
                foreach ($prefixes as $prefix => $dirs) {
                    if (strpos($namespace, $prefix) === 0 && strlen($prefix) > strlen($matchingPrefix)) {
                        $matchingPrefix = $prefix;
                        $matchingDir = $dirs[0];
                    }
                }
                
                if ($matchingDir !== null) {
                    // Calculate the subdirectory based on the remaining namespace
                    $subNamespace = substr($namespace, strlen($matchingPrefix));
                    $subDir = str_replace('\\', DIRECTORY_SEPARATOR, $subNamespace);
                    
                    return rtrim($matchingDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . rtrim($subDir, DIRECTORY_SEPARATOR);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Recursively scan a directory for PHP files
     *
     * @param string $directory
     * @return array
     */
    private function scanDirectory(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Get the fully qualified class name from a file path
     *
     * @param string $file
     * @param string $namespace
     * @param string $directory
     * @return string|null
     */
    private function getClassNameFromFile(string $file, string $namespace, string $directory): ?string
    {
        // Normalize directory path (ensure it ends with a directory separator)
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Get the relative path from the directory
        $relativePath = substr($file, strlen($directory));
        
        // Convert the relative path to a namespace
        $subNamespace = str_replace(
            DIRECTORY_SEPARATOR, 
            '\\', 
            substr($relativePath, 0, -4) // Remove .php extension
        );
        
        // Build the fully qualified class name
        $className = rtrim($namespace, '\\') . '\\' . $subNamespace;
        
        return $className;
    }

    /**
     * Run the router and handle the current request
     * 
     * This method is maintained for backward compatibility.
     * It's recommended to use the handle() method instead.
     *
     * @return void
     * @deprecated Use handle() instead
     */
    public function run(): void
    {
        $this->handle([
            'debug' => true,
            'cache_dir' => $this->cacheDir
        ]);
    }
} 