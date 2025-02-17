<?php

declare(strict_types=1);

namespace Piri\Core;

class RouteCache
{
    private const CACHE_FILE_PREFIX = 'piri_routes_';

    /**
     * Store routes in cache
     *
     * @param array<string, array<string, mixed>> $routes
     * @param string $cacheDir
     * @return bool
     */
    public function store(array $routes, string $cacheDir): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheDir);
        $cacheDir = dirname($cacheFile);

        // Ensure cache directory exists
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            throw new \RuntimeException("Cache directory {$cacheDir} could not be created");
        }

        // Process routes to handle Closures
        $processedRoutes = $this->processRoutesForCache($routes);

        $content = sprintf('<?php return %s;', var_export($processedRoutes, true));
        return (bool) file_put_contents($cacheFile, $content);
    }

    /**
     * Load routes from cache
     *
     * @param string $cacheDir
     * @return array<string, array<string, mixed>>|null
     */
    public function load(string $cacheDir): ?array
    {
        $cacheFile = $this->getCacheFilePath($cacheDir);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $routes = require $cacheFile;
        
        if (!is_array($routes)) {
            return null;
        }

        // Restore Closures from serialized data
        return $this->restoreRoutesFromCache($routes);
    }

    /**
     * Process routes for caching
     *
     * @param array<string, array<string, mixed>> $routes
     * @return array<string, array<string, mixed>>
     */
    private function processRoutesForCache(array $routes): array
    {
        $processed = [];

        foreach ($routes as $method => $methodRoutes) {
            $processed[$method] = [];
            foreach ($methodRoutes as $path => $routeData) {
                $processedRoute = $routeData;
                
                // Handle route handlers
                if (isset($routeData['handler'])) {
                    if ($routeData['handler'] instanceof \Closure) {
                        $processedRoute['handler'] = [
                            '__type' => 'Closure',
                            'source' => $this->serializeClosure($routeData['handler'])
                        ];
                    } elseif (is_array($routeData['handler'])) {
                        // Handle controller method calls
                        if (is_object($routeData['handler'][0])) {
                            $processedRoute['handler'] = [
                                '__type' => 'Controller',
                                'class' => get_class($routeData['handler'][0]),
                                'method' => $routeData['handler'][1]
                            ];
                        }
                    }
                }

                // Handle middleware
                if (isset($routeData['middleware'])) {
                    $processedRoute['middleware'] = array_map(function ($middleware) {
                        if (is_object($middleware)) {
                            return [
                                '__type' => 'Middleware',
                                'class' => get_class($middleware)
                            ];
                        }
                        return $middleware;
                    }, $routeData['middleware']);
                }

                $processed[$method][$path] = $processedRoute;
            }
        }

        return $processed;
    }

    /**
     * Restore routes from cache
     *
     * @param array<string, array<string, mixed>> $routes
     * @return array<string, array<string, mixed>>
     */
    private function restoreRoutesFromCache(array $routes): array
    {
        $restored = [];

        foreach ($routes as $method => $methodRoutes) {
            $restored[$method] = [];
            foreach ($methodRoutes as $path => $routeData) {
                $restoredRoute = $routeData;
                
                // Restore handlers
                if (isset($routeData['handler'])) {
                    if (is_array($routeData['handler']) && isset($routeData['handler']['__type'])) {
                        switch ($routeData['handler']['__type']) {
                            case 'Closure':
                                $restoredRoute['handler'] = $this->unserializeClosure($routeData['handler']['source']);
                                break;
                            case 'Controller':
                                $controllerClass = $routeData['handler']['class'];
                                $method = $routeData['handler']['method'];
                                $restoredRoute['handler'] = [new $controllerClass(), $method];
                                break;
                        }
                    }
                }

                // Restore middleware
                if (isset($routeData['middleware'])) {
                    $restoredRoute['middleware'] = array_map(function ($middleware) {
                        if (is_array($middleware) && isset($middleware['__type']) && $middleware['__type'] === 'Middleware') {
                            $class = $middleware['class'];
                            return new $class();
                        }
                        return $middleware;
                    }, $routeData['middleware']);
                }

                $restored[$method][$path] = $restoredRoute;
            }
        }

        return $restored;
    }

    /**
     * Serialize a Closure
     *
     * @param \Closure $closure
     * @return string
     */
    private function serializeClosure(\Closure $closure): string
    {
        $reflection = new \ReflectionFunction($closure);
        $file = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        // Read the closure's source code
        if ($file === false || $startLine === false || $endLine === false) {
            throw new \RuntimeException('Could not serialize Closure');
        }

        $source = implode('', array_slice(file($file), $startLine - 1, $endLine - $startLine + 1));
        
        return base64_encode(serialize([
            'file' => $file,
            'start' => $startLine,
            'end' => $endLine,
            'source' => $source
        ]));
    }

    /**
     * Unserialize a Closure
     *
     * @param string $serialized
     * @return \Closure
     */
    private function unserializeClosure(string $serialized): \Closure
    {
        $data = unserialize(base64_decode($serialized));
        
        // Create a unique function name
        $functionName = 'route_handler_' . md5(uniqid('', true));
        
        // Create the function definition
        $code = sprintf('return function() use ($functionName) { return %s; };', trim($data['source']));
        
        return eval($code);
    }

    /**
     * Check if cache exists and is fresh
     *
     * @param string $cacheDir
     * @param array<string> $routeFiles List of files to check against cache
     * @return bool
     */
    public function isFresh(string $cacheDir, array $routeFiles): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheDir);

        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);

        // Check if any route file is newer than cache
        foreach ($routeFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            if (filemtime($file) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear the route cache
     *
     * @param string $cacheDir
     * @return bool
     */
    public function clear(string $cacheDir): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheDir);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * Get the cache file path
     *
     * @param string $cacheDir
     * @return string
     */
    private function getCacheFilePath(string $cacheDir): string
    {
        return rtrim($cacheDir, '/') . '/' . self::CACHE_FILE_PREFIX . md5(__DIR__) . '.php';
    }
} 