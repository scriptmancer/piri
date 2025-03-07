<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Piri\Core\Router;
use Piri\Contracts\MiddlewareInterface;

// Define a simple middleware
class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        echo "Request logged at " . date('Y-m-d H:i:s') . "\n";
        return $next($parameters);
    }
}

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($token) || !str_starts_with($token, 'Bearer ')) {
            http_response_code(401);
            return "Unauthorized: Missing or invalid Bearer token";
        }
        return $next($parameters);
    }
}

// Create a new router instance
$router = new Router();

// Add global middleware
$router->addGlobalMiddleware(new LoggingMiddleware());

// Register controllers by namespace with prefix and middleware
$router->registerNamespace(
    'Example\Controller',
    prefix: '/api',
    name: 'api',
    middleware: [new AuthMiddleware()]
);

// Define a route using a closure
$router->get('/', function() {
    return 'Welcome to the Piri Router example!';
});

// Define a route with parameters
$router->get('/users/{id:\d+}', function(array $params) {
    return 'User ID: ' . $params['id'];
});

// Define a route with optional parameters
$router->get('/posts/{id:\d+}/{slug?}', function(array $params) {
    $response = 'Post ID: ' . $params['id'];
    if (isset($params['slug'])) {
        $response .= ', Slug: ' . $params['slug'];
    }
    return $response;
});

// Define a named route
$router->get('/about', function() {
    return 'About page';
}, 'about_page');

// Define a route group
$router->group(['prefix' => '/admin', 'middleware' => [new AuthMiddleware()]], function($r) {
    $r->get('/dashboard', function() {
        return 'Admin Dashboard';
    });
    
    $r->get('/users', function() {
        return 'Admin Users List';
    });
});

// Print all registered routes
echo "\nRegistered Routes:\n";
foreach ($router->getRoutes() as $method => $routes) {
    foreach ($routes as $path => $route) {
        echo $method . ' ' . $path;
        if (!empty($route['name'])) {
            echo ' (name: ' . $route['name'] . ')';
        }
        echo "\n";
    }
}

// Test route matching
$testPaths = [
    '/',
    '/users/123',
    '/posts/456/my-post',
    '/about',
    '/admin/dashboard',
    '/api/users',
    '/nonexistent'
];

echo "\nRoute Matching Tests:\n";
foreach ($testPaths as $path) {
    echo "Testing path: $path\n";
    try {
        $match = $router->match('GET', $path);
        if ($match) {
            echo "  ✓ Matched to: " . ($match['name'] ?? 'unnamed route') . "\n";
        } else {
            echo "  ✗ No match found\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Uncomment to run the router
// $router->run(); 