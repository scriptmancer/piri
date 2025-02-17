<?php

namespace Example\Middleware;

use Piri\Contracts\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        // Simulate authentication check
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            return [
                'error' => 'Unauthorized',
                'message' => 'Please provide a valid Bearer token'
            ];
        }

        // For demo purposes, accept any token
        return $next($parameters);
    }
} 