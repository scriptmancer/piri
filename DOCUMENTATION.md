# Piri Router Documentation

A modern, attribute-based PHP router with powerful features for building web applications.

## Table of Contents

- [Piri Router Documentation](#piri-router-documentation)
  - [Table of Contents](#table-of-contents)
  - [Installation](#installation)
    - [Via Composer](#via-composer)
    - [Manual Installation](#manual-installation)
  - [Basic Usage](#basic-usage)
    - [Using Attributes](#using-attributes)
    - [Using Method Calls](#using-method-calls)
    - [Basic Integration](#basic-integration)
    - [Custom Error Handling](#custom-error-handling)
  - [Route Parameters](#route-parameters)
    - [Basic Parameters](#basic-parameters)
    - [Optional Parameters](#optional-parameters)
    - [Pattern Constraints](#pattern-constraints)
  - [Route Groups](#route-groups)
    - [Method-Based Groups](#method-based-groups)
    - [Attribute-Based Groups](#attribute-based-groups)
    - [Group-Based Attribute Routing](#group-based-attribute-routing)
    - [Namespace-Based Groups](#namespace-based-groups)
  - [Middleware](#middleware)
    - [Global Middleware](#global-middleware)
    - [Group Middleware](#group-middleware)
    - [Route Middleware](#route-middleware)
  - [Named Routes](#named-routes)
  - [Controller Registration](#controller-registration)
    - [Individual Controllers](#individual-controllers)
    - [Namespace Registration](#namespace-registration)
  - [Advanced Features](#advanced-features)
    - [Route Caching](#route-caching)
    - [Request Handling](#request-handling)
    - [Custom Response Handling](#custom-response-handling)
    - [Error Handling](#error-handling)

## Installation

### Via Composer

```bash
composer require scriptmancer/piri
```

### Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/scriptmancer/piri.git
```

2. Include the autoloader:
```php
require_once 'path/to/piri/autoload.php'
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

### Custom Error Handling

You can provide a custom error handler function:

```php
$router->handle([
    'error_handler' => function(\Exception $e) {
        if ($e instanceof RouteNotFoundException) {
            http_response_code(404);
            include 'templates/404.php';
        } else {
            http_response_code(500);
            include 'templates/500.php';
        }
    }
]);
```

## Route Parameters

### Basic Parameters

You can define route parameters using curly braces:

```php
// Using attributes
#[Route('/users/{id}')]
public function show(array $params): string
{
    return 'User ID: ' . $params['id'];
}

// Using method calls
$router->get('/users/{id}', function(array $params) {
    return 'User ID: ' . $params['id'];
});
```

### Optional Parameters

Optional parameters are defined with a question mark:

```php
// Using attributes
#[Route('/users/{id}/posts/{postId?}')]
public function posts(array $params): array
{
    return [
        'user' => $params['id'],
        'post' => $params['postId'] ?? null
    ];
}

// Using method calls
$router->get('/users/{id}/posts/{postId?}', function(array $params) {
    return [
        'user' => $params['id'],
        'post' => $params['postId'] ?? null
    ];
});
```

### Pattern Constraints

You can add pattern constraints to parameters using regular expressions:

```php
// Using attributes
#[Route('/users/{id:\d+}')]
public function show(array $params): string
{
    return 'User ID: ' . $params['id'];
}

// Using method calls
$router->get('/users/{id:\d+}', function(array $params) {
    return 'User ID: ' . $params['id'];
});
```

## Route Groups

### Method-Based Groups

You can group routes using the `group` method:

```php
$router->group(['prefix' => '/admin', 'name' => 'admin', 'middleware' => [AdminAuth::class]], function(Router $router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});
```

### Attribute-Based Groups

You can also define route groups using the `RouteGroup` attribute:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/admin', name: 'admin', middleware: [AdminAuth::class])]
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
$router->group(['prefix' => '/api', 'name' => 'api_root'], function(Router $router) {
    // Routes from ApiController with group:'api_root' will be automatically
    // registered with the prefix '/api'
});
```

This will register the following routes:
- `GET /api/status` - from the `status()` method
- `GET /api/config` - from the `config()` method

### Namespace-Based Groups

You can register all controllers in a namespace with a common prefix, name, and middleware:

```php
// Register all controllers in the App\Http\Controllers\Api namespace
// with the prefix '/api', the name 'api', and the AuthMiddleware
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new AuthMiddleware()]
);

// Register all controllers in the App\Http\Controllers\Admin namespace
// with the prefix '/admin', the name 'admin', and the AdminMiddleware
$router->registerNamespace(
    'App\Http\Controllers\Admin', 
    null, 
    '/admin', 
    'admin', 
    [new AdminMiddleware()]
);
```

## Middleware

### Global Middleware

Global middleware is applied to all routes:

```php
$router->addGlobalMiddleware(new LoggingMiddleware());
```

### Group Middleware

Group middleware is applied to all routes in a group:

```php
// Method-based groups
$router->group(['middleware' => [AuthMiddleware::class]], function(Router $router) {
    // Routes here will have the AuthMiddleware applied
});

// Attribute-based groups
#[RouteGroup(middleware: [AuthMiddleware::class])]
class UserController
{
    // Routes here will have the AuthMiddleware applied
}

// Namespace-based groups
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new AuthMiddleware()]
);
```

### Route Middleware

Route middleware is applied to specific routes:

```php
// Using attributes
#[Route('/users/{id}', middleware: [AuthMiddleware::class])]
public function show(array $params): array
{
    return ['user' => $params['id']];
}

// Using method calls
$router->get('/users/{id}', [UserController::class, 'show'])
    ->middleware(new AuthMiddleware());
```

## Named Routes

Named routes allow you to generate URLs for routes:

```php
use Piri\Core\Route;

// Define a named route
#[Route('/users/{id}', name: 'users.show')]
public function show(array $params): array
{
    return ['user' => $params['id']];
}

// Generate a URL for the named route
$url = Route::url('users.show', ['id' => 123]);
// Output: /users/123
```

## Controller Registration

### Individual Controllers

You can register individual controllers using the `registerClass` method:

```php
$router->registerClass(UserController::class);
$router->registerClass(ProductController::class);
```

### Namespace Registration

You can register all controllers in a namespace using the `registerNamespace` method:

```php
// Register all controllers in the App\Http\Controllers namespace
$router->registerNamespace('App\Http\Controllers');

// Register all controllers in the App\Http\Controllers\Api namespace
// with the prefix '/api', the name 'api', and the AuthMiddleware
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new AuthMiddleware()]
);
```

## Advanced Features

### Route Caching

Piri Router supports route caching to improve performance:

```php
// Enable route caching
$router->enableCache(__DIR__ . '/cache');

// Clear the route cache
$router->clearCache();
```

### Request Handling

The `handle()` method provides a streamlined way to handle HTTP requests with minimal boilerplate code:

```php
// Basic usage
$router->handle();

// With configuration options
$router->handle([
    'cache_dir' => __DIR__ . '/cache',
    'debug' => true,
    'json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    'error_handler' => function(\Exception $e) {
        // Custom error handling
    }
]);
```

This method handles:
- Determining the request method and path
- Matching the route
- Executing the route handler
- Formatting and outputting the response
- Error handling with customizable options

For applications with special requirements, you can still use the lower-level methods:

```php
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$route = $router->match($method, $path);
$result = $router->execute($route);

// Custom response handling
```

### Custom Response Handling

You can customize how responses are handled:

```php
$result = $router->execute($route);

// Handle the response
if ($result instanceof JsonResponse) {
    header('Content-Type: application/json');
    echo json_encode($result->getData(), JSON_PRETTY_PRINT);
} elseif ($result instanceof ViewResponse) {
    echo $result->render();
} elseif (is_array($result) || is_object($result)) {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    echo $result;
}
```

### Error Handling

You can customize how errors are handled:

```php
try {
    $route = $router->match($method, $path);
    $result = $router->execute($route);
    
    // Handle the response
    // ...
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    echo json_encode([
        'error' => '404 Not Found',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (UnauthorizedException $e) {
    http_response_code(401);
    echo json_encode([
        'error' => '401 Unauthorized',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '500 Internal Server Error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
``` 