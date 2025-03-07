# Piri Router

A lightweight, attribute-based PHP router with support for middleware, route groups, and parameter validation.

## Features

- Attribute-based routing for clean controller definitions
- Support for route parameters with regex pattern validation
- Middleware support (global, group, and route-specific)
- Route groups for organizing related routes
- Named routes for easy URL generation
- Namespace-based controller registration
- Type conversion for route parameters
- PSR-7 compatible

## Installation

```bash
composer require yourusername/piri-router
```

## Basic Usage

```php
require_once __DIR__ . '/vendor/autoload.php';

use Piri\Core\Router;
use Piri\Attributes\Route;

// Create a new router instance
$router = new Router();

// Register a controller
$router->registerClass(HomeController::class);

// Define a route using a closure
$router->get('/', function() {
    return 'Hello, World!';
});

// Run the router
$router->run();
```

## Controller Example

```php
use Piri\Attributes\Route;

class HomeController
{
    #[Route('/')]
    public function index(): string
    {
        return 'Welcome to the homepage!';
    }
    
    #[Route('/about')]
    public function about(): string
    {
        return 'About us page';
    }
}
```

## Route Patterns

You can use regex patterns in your route parameters:

```php
// Numeric ID parameter
#[Route('/users/{id:\d+}')]
public function numericId(array $params): string
{
    // The id parameter will only match digits
    $id = $params['id']; // Will be a string of digits
    return 'User ID: ' . $id;
}

// Alphabetic username parameter
#[Route('/users/{username:[a-zA-Z]+}')]
public function alphaUsername(array $params): string
{
    // The username parameter will only match letters
    $username = $params['username']; // Will be a string of letters
    return 'Username: ' . $username;
}

// Using method calls
$router->get('/posts/{id:\d+}', function(array $params) {
    $id = $params['id'];
    return 'Post ID: ' . $id;
});
```

### Handling Route Parameters

All route parameters are passed to your handler method as a single array. You should always define your handler method to accept an array parameter, and then access the route parameters from that array:

```php
#[Route('/users/{id}/posts/{postId?}')]
public function posts(array $params): array
{
    // Access parameters from the $params array
    $id = $params['id'];
    $postId = $params['postId'] ?? null; // Use null coalescing for optional parameters
    
    return [
        'user' => $id,
        'post' => $postId
    ];
}
```

## Route Groups

You can group routes together using the `RouteGroup` attribute on controller classes:

```php
#[RouteGroup(prefix: '/api', name: 'api', middleware: [ApiAuthMiddleware::class])]
class ApiController
{
    #[Route('/users', name: 'users.list')]
    public function listUsers(): array
    {
        // This route will be accessible at /api/users
        // It will have the middleware ApiAuthMiddleware applied
        // Its name will be api.users.list
        return ['users' => []];
    }
    
    #[Route('/users/{id:\d+}')]
    public function getUser(array $params): array
    {
        // This route will be accessible at /api/users/{id}
        // It will also have the middleware ApiAuthMiddleware applied
        return ['user' => ['id' => $params['id']]];
    }
}
```

## Namespace Registration

You can register all controllers in a namespace at once:

```php
// Register all controllers in the App\Controller namespace
$router->registerNamespace('App\Controller');

// Register with prefix, name, and middleware
$router->registerNamespace(
    'App\Api\Controller', 
    prefix: '/api', 
    name: 'api', 
    middleware: [ApiAuthMiddleware::class]
);
```

This will automatically register all controllers in the specified namespace and apply the prefix, name, and middleware to all routes in those controllers.

## Middleware

Middleware allows you to filter HTTP requests entering your application:

```php
// Define a middleware class
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        // Check if user is authenticated
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            return 'Unauthorized';
        }
        
        // Call the next middleware or the route handler
        return $next($parameters);
    }
}

// Apply middleware globally
$router->addGlobalMiddleware(new LoggingMiddleware());

// Apply middleware to a group
$router->group(['prefix' => '/admin', 'middleware' => [new AuthMiddleware()]], function($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
});

// Apply middleware to a specific route
$router->get('/profile', [UserController::class, 'profile'], 
    middleware: [new AuthMiddleware()]);
    
// Apply middleware using attributes
#[Route('/settings', middleware: [AuthMiddleware::class])]
public function settings(): string
{
    return 'Settings page';
}
```

## Error Handling

You can customize error handling by providing an error handler function to the `handle` method:

```php
// Custom error handling
$router->handle([
    'error_handler' => function(\Exception $e) {
        if ($e instanceof RouteNotFoundException) {
            http_response_code(404);
            include 'templates/404.php';
        } else {
            http_response_code(500);
            error_log($e->getMessage());
            include 'templates/500.php';
        }
    }
]);
```

## Advanced Usage

For more advanced usage, including route caching, custom request handling, and more, please refer to the [Documentation](DOCUMENTATION.md).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- Inspired by modern PHP routing libraries
- Built with PHP 8.1+ features in mind
