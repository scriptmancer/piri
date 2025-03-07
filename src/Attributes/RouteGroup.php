<?php

namespace Piri\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
    /**
     * @param string $prefix The prefix for all routes in this group
     * @param string $name The name of the group
     * @param mixed $middleware The middleware for all routes in this group
     */
    public function __construct(
        private string $prefix = '',
        private string $name = '',
        private mixed $middleware = []
    ) {
    }

    /**
     * Get the prefix for this group
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the name of this group
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the middleware for this group
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        if (is_array($this->middleware)) {
            return $this->middleware;
        }

        return [$this->middleware];
    }
}