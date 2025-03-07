# Piri Router Documentation

A lightweight, attribute-based PHP router with support for middleware, route groups, and parameter validation.

## Table of Contents

- [Piri Router Documentation](#piri-router-documentation)
  - [Table of Contents](#table-of-contents)
  - [Installation](#installation)
  - [Basic Usage](#basic-usage)
    - [Using Attributes](#using-attributes)
    - [Using Method Calls](#using-method-calls)
    - [Basic Integration](#basic-integration)
  - [Route Parameters](#route-parameters)
    - [Basic Parameters](#basic-parameters)
    - [Optional Parameters](#optional-parameters)
    - [Pattern Constraints](#pattern-constraints)
    - [Type Conversion](#type-conversion)
  - [Route Groups](#route-groups)
    - [Method-Based Groups](#method-based-groups)
    - [Attribute-Based Groups](#attribute-based-groups)
    - [Group-Based Attribute Routing](#group-based-attribute-routing)
  - [Middleware](#middleware)
    - [Global Middleware](#global-middleware)
    - [Group Middleware](#group-middleware)
    - [Route Middleware](#route-middleware)
  - [Controller Registration](#controller-registration)
    - [Individual Controllers](#individual-controllers)
    - [Namespace Registration](#namespace-registration)
  - [Advanced Features](#advanced-features)
    - [Route Caching](#route-caching)
    - [Request Handling](#request-handling)
    - [Custom Response Handling](#custom-response-handling)
    - [Error Handling](#error-handling)

## Installation

```bash
composer require yourusername/piri-router
```

## Basic Usage

### Using Attributes

Piri Router supports attribute-based routing, which is the recommended way to define routes:

```php
use Piri\Attributes\Route;
use Piri\Core\Router;

class UserController
{
    #[Route('/users', name: 'users.list')]
    public function list(): array
    {
        return ['users' => []];
    }

    #[Route('/users/{id}', methods: ['GET', 'POST'], name: 'users.show')]
    public function show(array $params): array
    {
        return ['user' => $params['id']];
    }

    #[Route('/users/{id}/posts/{postId?}')]
    public function posts(array $params): array
    {
        return [
            'user' => $params['id'],
            'post' => $params['postId'] ?? null
        ];
    }
}

$router = new Router();
$router->registerClass(UserController::class);
$router->handle();
```

### Using Method Calls

You can also define routes using method calls:

```php
use Piri\Core\Router;

$router = new Router();

// Simple route
$router->get('/hello', function() {
    return 'Hello, World!';
});

// Route with parameters
$router->get('/users/{id}', function(array $params) {
    return 'User ID: ' . $params['id'];
});

// Route with multiple methods
$router->add('POST', '/api/data', [ApiController::class, 'handleData']);
```

### Basic Integration

Here's a basic example of integrating Piri Router in your application:

```php
// index.php
<?php

require_once 'vendor/autoload.php';

use Piri\Core\Router;

$router = new Router();

// Load routes from a separate file
require_once 'routes.php';

// Handle the request with a single line of code
$router->handle([
    'cache_dir' => getenv('APP_ENV') !== 'local' ? __DIR__ . '/cache' : null,
    'debug' => getenv('APP_ENV') === 'local',
    'json_options' => JSON_PRETTY_PRINT
]);
```

The `handle()` method accepts the following options:

- `cache_dir`: Directory for route caching (enables caching if provided)
- `debug`: Enable debug mode with detailed error messages (default: false)
- `json_options`: JSON encoding options for array/object responses (default: JSON_PRETTY_PRINT)
- `error_handler`: Custom error handler callable for advanced error handling

## Route Parameters

### Basic Parameters

You can define route parameters using curly braces:

```php
// Using attributes
#[Route('/users/{id}')]
public function show(array $params): string
{
    // Access the parameter from the $params array
    $id = $params['id'];
    return 'User ID: ' . $id;
}

// Using method calls
$router->get('/users/{id}', function(array $params) {
    $id = $params['id'];
    return 'User ID: ' . $id;
});
```

All route parameters are passed to your handler method as a single array. You should always define your handler method to accept an array parameter, and then access the route parameters from that array.

### Optional Parameters

Optional parameters are defined with a question mark:

```php
// Using attributes
#[Route('/users/{id}/posts/{postId?}')]
public function posts(array $params): array
{
    $id = $params['id'];
    $postId = $params['postId'] ?? null; // Use null coalescing for optional parameters
    
    return [
        'user' => $id,
        'post' => $postId
    ];
}

// Using method calls
$router->get('/users/{id}/posts/{postId?}', function(array $params) {
    $id = $params['id'];
    $postId = $params['postId'] ?? null;
    
    return [
        'user' => $id,
        'post' => $postId
    ];
});
```

### Pattern Constraints

You can add pattern constraints to parameters using regular expressions:

```php
// Using attributes
#[Route('/users/{id:\d+}')]
public function numericId(array $params): string
{
    // The id parameter will only match digits
    $id = $params['id']; // Will be a string of digits
    return 'User ID: ' . $id;
}

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

### Type Conversion

The router automatically converts route parameters to appropriate PHP types based on the method signature:

```php
// The id parameter will be converted to an integer
#[Route('/users/{id:\d+}')]
public function show(array $params): string
{
    $id = (int)$params['id']; // Manual conversion
    return 'User ID: ' . $id;
}
```

## Route Groups

### Method-Based Groups

The traditional way to define route groups using method calls:

```php
$router->group(['prefix' => '/admin', 'middleware' => [AdminAuthMiddleware::class]], function($router) {
    $router->get('/dashboard', function() {
        return 'Admin Dashboard';
    });
    
    $router->get('/users', function() {
        return 'Admin Users List';
    });
});
```

### Attribute-Based Groups

You can also define route groups using the `RouteGroup` attribute:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/admin', name: 'admin', middleware: [AdminAuthMiddleware::class])]
class AdminController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): string
    {
        return 'Admin Dashboard';
    }

    #[Route('/users', name: 'users')]
    public function users(): array
    {
        return ['users' => []];
    }
}
```

### Group-Based Attribute Routing

You can define routes that belong to a group using the `group` attribute parameter:

```php
// In your controller
class ApiController
{
    #[Route('/status', group: 'api_root')]
    public function status(): string
    {
        return 'API Status';
    }
    
    #[Route('/config', group: 'api_root')]
    public function config(): array
    {
        return ['version' => '1.0'];
    }
}

// In your routes.php file
$router->registerClass(ApiController::class);

// Define the group with a prefix
$router->group(['prefix' => '/api', 'name' => 'api_root'], function($router) {
    // Routes from ApiController with group:'api_root' will be automatically
    // registered with the prefix '/api'
});
```

This will register the following routes:
- `GET /api/status` - from the `status()` method
- `GET /api/config` - from the `config()` method

The order of registration is important:
1. First register the controller classes
2. Then define the groups with matching names

## Middleware

### Global Middleware

Apply middleware to all routes:

```php
$router->addGlobalMiddleware(new LoggingMiddleware());
```

### Group Middleware

Apply middleware to a group of routes:

```php
$router->group(['middleware' => [AuthMiddleware::class]], function($router) {
    // Routes here will have the AuthMiddleware applied
});

// Or using attributes
#[RouteGroup(middleware: [AuthMiddleware::class])]
class UserController
{
    // Routes here will have the AuthMiddleware applied
}
```

### Route Middleware

Apply middleware to specific routes:

```php
// Using attributes
#[Route('/profile', middleware: [AuthMiddleware::class])]
public function profile(): string
{
    return 'Profile page';
}

// Using method calls
$router->get('/profile', [UserController::class, 'profile'], 
    middleware: [new AuthMiddleware()]);
```

Middleware classes must implement the `MiddlewareInterface`:

```php
use Piri\Contracts\MiddlewareInterface;

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
```

## Controller Registration

### Individual Controllers

Register a single controller:

```php
$router->registerClass(UserController::class);
```

### Namespace Registration

Register all controllers in a namespace:

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

## Advanced Features

### Route Caching

Enable route caching for better performance in production:

```php
// Enable route caching
$router->enableCache(__DIR__ . '/cache');

// Add route files to watch for cache invalidation
$router->addRouteFile(__FILE__);

// Clear the route cache
$router->clearCache();
```

### Request Handling

Handle requests with custom options:

```php
$router->handle([
    'cache_dir' => __DIR__ . '/cache',
    'debug' => true,
    'json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
]);
```

### Custom Response Handling

Return different types of responses:

```php
// Return a string (plain text)
#[Route('/text')]
public function text(): string
{
    return 'Plain text response';
}

// Return an array (JSON)
#[Route('/json')]
public function json(): array
{
    return ['message' => 'JSON response'];
}

// Return HTML
#[Route('/html')]
public function html(): string
{
    return '<h1>HTML response</h1>';
}
```

### Error Handling

Customize error handling:

```php
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