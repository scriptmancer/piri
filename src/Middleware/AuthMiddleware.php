<?php

declare(strict_types=1);

namespace Piri\Middleware;

use Piri\Contracts\MiddlewareInterface;
use Piri\Exceptions\UnauthorizedException;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param string|null $token
     */
    public function __construct(
        private ?string $token = null
    ) {
    }

    /**
     * Handle the incoming request
     *
     * @param callable $next
     * @param array<string, mixed> $parameters
     * @return mixed
     * @throws UnauthorizedException
     */
    public function handle(callable $next, array $parameters = []): mixed
    {
        if ($this->token === null) {
            throw new UnauthorizedException('No authentication token provided');
        }

        // Here you would typically validate the token
        // For this example, we'll just check if it's not empty
        if (empty($this->token)) {
            throw new UnauthorizedException('Invalid authentication token');
        }

        return $next($parameters);
    }
} 