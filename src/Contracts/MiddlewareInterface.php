<?php

declare(strict_types=1);

namespace Piri\Contracts;

interface MiddlewareInterface
{
    /**
     * Handle the incoming request
     *
     * @param callable $next
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function handle(callable $next, array $parameters = []): mixed;
} 