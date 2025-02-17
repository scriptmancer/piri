<?php

declare(strict_types=1);

namespace Piri\Core;

use Piri\Contracts\RouterInterface;
use Piri\Contracts\MiddlewareInterface;
use Piri\Exceptions\RouteNotFoundException;
use Piri\Attributes\Route as RouteAttribute;
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
     * @param array{prefix?: string, middleware?: array<MiddlewareInterface|string>} $attributes
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Store current group state
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        // Set new group state
        $this->prefix .= $attributes['prefix'] ?? '';
        
        // Handle middleware
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge(
                $this->groupMiddleware,
                $this->normalizeMiddleware($attributes['middleware'])
            );
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
     * Execute the middleware chain
     *
     * @param array<MiddlewareInterface> $middleware
     * @param callable $handler
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    private function executeMiddlewareChain(array $middleware, callable $handler, array $parameters): mixed
    {
        if (empty($middleware)) {
            return $handler($parameters);
        }

        $next = fn($params) => $this->executeMiddlewareChain(
            array_slice($middleware, 1),
            $handler,
            $params
        );

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
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $handler = [new $class(), $method];
        }

        return $this->executeMiddlewareChain(
            $middleware,
            is_callable($handler) ? $handler : [$handler, '__invoke'],
            $parameters
        );
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
     * Run the router and handle the current request
     *
     * @return void
     */
    public function run(): void
    {
        try {
            // Try to load routes from cache
            $loadedFromCache = $this->loadRoutes();

            // If we have no routes at all, something is wrong
            if (empty($this->routes)) {
                throw new \RuntimeException('No routes defined');
            }

            $method = $_SERVER['REQUEST_METHOD'];
            
            // Get the request URI
            $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            
            // Get the script path and name
            $scriptFile = $_SERVER['SCRIPT_FILENAME'];
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $scriptPath = dirname($scriptName);
            
            // Calculate the base URL
            $baseUrl = '';
            if ($scriptPath !== '/' && $scriptPath !== '\\') {
                // For subdirectory installations
                $baseUrl = rtrim($scriptPath, '/');
                if (strpos($requestUri, $baseUrl) === 0) {
                    $requestUri = substr($requestUri, strlen($baseUrl));
                }
            }
            
            // Clean the path
            $path = '/' . trim($requestUri, '/');
            
            // Debug information
            error_log(sprintf(
                "Request Debug - Method: %s, URI: %s, Script: %s, Base: %s, Path: %s, Cached: %s, Routes: %s",
                $method,
                $_SERVER['REQUEST_URI'],
                $scriptFile,
                $baseUrl,
                $path,
                $loadedFromCache ? 'yes' : 'no',
                json_encode(array_keys($this->routes[$method] ?? []))
            ));
            
            // Match and execute the route
            $route = $this->match($method, $path);
            $result = $this->execute($route);
            
            // Handle the response
            if (is_array($result)) {
                header('Content-Type: application/json');
                echo json_encode($result, JSON_PRETTY_PRINT);
            } else {
                echo $result;
            }
        } catch (RouteNotFoundException $e) {
            http_response_code(404);
            echo json_encode([
                'error' => '404 Not Found',
                'message' => $e->getMessage(),
                'debug' => [
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                    'script_file' => $scriptFile ?? null,
                    'script_name' => $scriptName ?? null,
                    'base_url' => $baseUrl ?? null,
                    'path' => $path ?? null,
                    'method' => $method ?? null,
                    'cached' => $loadedFromCache ?? false,
                    'available_routes' => array_keys($this->routes[$method] ?? [])
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], JSON_PRETTY_PRINT);
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

        // Get class-level route attribute
        $classRoute = $reflection->getAttributes(RouteAttribute::class)[0] ?? null;
        $classRouteInstance = $classRoute?->newInstance();
        
        $classPrefix = $classRouteInstance?->getPrefix() ?? '';
        $classMiddleware = $classRouteInstance?->getMiddleware() ?? [];
        
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

                foreach ($route->getMethods() as $httpMethod) {
                    $this->add(
                        $httpMethod,
                        $route->getPath(),
                        [$instance, $method->getName()],
                        $route->getName(),
                        $route->getGroup()
                    );

                    if (!empty($methodMiddleware)) {
                        $this->routes[strtoupper($httpMethod)][$this->prefix . $route->getPath()]['middleware'] = array_merge(
                            $this->routes[strtoupper($httpMethod)][$this->prefix . $route->getPath()]['middleware'],
                            $methodMiddleware
                        );
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
} 