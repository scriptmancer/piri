<?php

namespace Example\Middleware;

use Piri\Contracts\MiddlewareInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        // Log the request
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        $time = date('Y-m-d H:i:s');
        
        error_log("[{$time}] {$method} {$path}");
        
        return $next($parameters);
    }
} 