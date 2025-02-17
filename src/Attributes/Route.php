<?php

declare(strict_types=1);

namespace Piri\Attributes;

use Attribute;
use Piri\Contracts\MiddlewareInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{
    /**
     * @param string $path
     * @param string|array<string> $methods
     * @param string $name
     * @param string $prefix
     * @param MiddlewareInterface|callable|array<MiddlewareInterface|callable> $middleware
     * @param string $group
     */
    public function __construct(
        private string $path = '',
        private string|array $methods = ['GET'],
        private string $name = '',
        private string $prefix = '',
        private mixed $middleware = [],
        private string $group = '',
    ) {
    }

    /**
     * Get the route path
     */
    public function getPath(): string
    {
        return $this->prefix . $this->path;
    }

    /**
     * Get the HTTP methods
     *
     * @return array<string>
     */
    public function getMethods(): array
    {
        return array_map('strtoupper', (array) $this->methods);
    }

    /**
     * Get the route name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the route prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the route group
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Get the middleware
     *
     * @return array<MiddlewareInterface|callable>
     */
    public function getMiddleware(): array
    {
        return (array) $this->middleware;
    }
} 