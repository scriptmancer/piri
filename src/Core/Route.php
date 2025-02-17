<?php

declare(strict_types=1);

namespace Piri\Core;

class Route
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $routes = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $namedRoutes = [];

    /**
     * Get URL for a named route
     *
     * @param string $name
     * @param array<string, string|int> $parameters
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function url(string $name, array $parameters = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not found.");
        }

        $path = self::$namedRoutes[$name]['path'];

        foreach ($parameters as $key => $value) {
            $path = str_replace("{{$key}}", (string) $value, $path);
        }

        // Check if all parameters are replaced
        if (preg_match('/{[^}]*}/', $path)) {
            throw new \InvalidArgumentException('Not all parameters were provided for the route.');
        }

        return $path;
    }

    /**
     * Register a named route
     */
    public static function addNamed(string $name, string $path, array $routeData): void
    {
        self::$namedRoutes[$name] = array_merge($routeData, ['path' => $path]);
    }

    /**
     * Get a named route
     *
     * @return array<string, mixed>|null
     */
    public static function getByName(string $name): ?array
    {
        return self::$namedRoutes[$name] ?? null;
    }

    /**
     * Clear all named routes (for testing purposes)
     */
    public static function clearNamed(): void
    {
        self::$namedRoutes = [];
    }

    /**
     * Clear all routes and named routes
     */
    public static function clear(): void
    {
        self::$routes = [];
        self::$namedRoutes = [];
    }

    /**
     * Get all registered routes
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::$routes;
    }

    /**
     * Get all named routes
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allNamed(): array
    {
        return self::$namedRoutes;
    }

    /**
     * Add a route
     *
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $routeData
     */
    public static function add(string $method, string $path, array $routeData): void
    {
        self::$routes[$method][$path] = $routeData;
    }
} 