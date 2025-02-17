<?php

declare(strict_types=1);

namespace Piri\Contracts;

interface RouterInterface
{
    /**
     * Register a new route
     *
     * @param string $method
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function add(string $method, string $path, callable|array|string $handler, ?string $name = null): void;

    /**
     * Create a route group
     *
     * @param array{prefix?: string, middleware?: array<MiddlewareInterface>} $attributes
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void;

    /**
     * Find a route for the given method and path
     *
     * @param string $method
     * @param string $path
     * @return array<string, mixed>
     * @throws \Piri\Exceptions\RouteNotFoundException
     */
    public function match(string $method, string $path): array;

    /**
     * Add middleware to a specific route
     *
     * @param string $method
     * @param string $path
     * @param MiddlewareInterface $middleware
     * @return void
     * @throws \Piri\Exceptions\RouteNotFoundException
     */
    public function addMiddleware(string $method, string $path, MiddlewareInterface $middleware): void;

    /**
     * Add global middleware
     *
     * @param MiddlewareInterface $middleware
     * @return void
     */
    public function addGlobalMiddleware(MiddlewareInterface $middleware): void;

    /**
     * Execute a route with its middleware chain
     *
     * @param array<string, mixed> $routeData
     * @return mixed
     */
    public function execute(array $routeData): mixed;

    /**
     * Add a GET route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function get(string $path, callable|array|string $handler, ?string $name = null): void;

    /**
     * Add a POST route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function post(string $path, callable|array|string $handler, ?string $name = null): void;

    /**
     * Add a PUT route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function put(string $path, callable|array|string $handler, ?string $name = null): void;

    /**
     * Add a DELETE route
     *
     * @param string $path
     * @param callable|array|string $handler
     * @param string|null $name
     * @return void
     */
    public function delete(string $path, callable|array|string $handler, ?string $name = null): void;
} 