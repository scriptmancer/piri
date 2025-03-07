# Namespace Routing and Route Groups

This document provides detailed information about the namespace routing and route group features in Piri Router.

## Table of Contents

- [Namespace Routing and Route Groups](#namespace-routing-and-route-groups)
  - [Table of Contents](#table-of-contents)
  - [Namespace Registration](#namespace-registration)
    - [Basic Usage](#basic-usage)
    - [With Prefix, Name, and Middleware](#with-prefix-name-and-middleware)
    - [Directory Specification](#directory-specification)
  - [Route Groups](#route-groups)
    - [Method-Based Groups](#method-based-groups)
    - [Attribute-Based Groups](#attribute-based-groups)
    - [Group-Based Attribute Routing](#group-based-attribute-routing)
    - [Nested Groups](#nested-groups)
  - [Group Middleware](#group-middleware)
  - [Advanced Usage](#advanced-usage)
    - [Combining Approaches](#combining-approaches)
    - [Priority and Overrides](#priority-and-overrides)
    - [Best Practices](#best-practices)

## Namespace Registration

Namespace registration allows you to register all controllers in a namespace at once, making it easier to organize your routes and apply common settings to groups of controllers.

### Basic Usage

The simplest way to register all controllers in a namespace:

```php
// Register all controllers in the App\Http\Controllers namespace
$router->registerNamespace('App\Http\Controllers');
```

This will scan the directory associated with the namespace, find all controller classes, and register them with the router.

### With Prefix, Name, and Middleware

You can also specify a prefix, name, and middleware for all controllers in the namespace:

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

This is equivalent to wrapping all controllers in the namespace in a route group with the specified prefix, name, and middleware.

### Directory Specification

If the namespace doesn't follow PSR-4 autoloading standards, or if you want to specify a custom directory, you can provide the directory parameter:

```php
// Register all controllers in the App\Http\Controllers namespace
// from a custom directory
$router->registerNamespace(
    'App\Http\Controllers', 
    __DIR__ . '/src/Controllers'
);
```

## Route Groups

Route groups allow you to apply common settings to multiple routes. Piri Router supports several ways to define route groups:

### Method-Based Groups

The traditional way to define route groups using method calls:

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

The order of registration is important:
1. First register the controller classes
2. Then define the groups with matching names

### Nested Groups

You can nest route groups to create hierarchical route structures:

```php
// Method-based nested groups
$router->group(['prefix' => '/api'], function(Router $router) {
    $router->group(['prefix' => '/v1'], function(Router $router) {
        $router->get('/users', [ApiController::class, 'users']);
    });
});

// Attribute-based nested groups
#[RouteGroup(prefix: '/api')]
class ApiController
{
    #[Route('/v1/users', name: 'api.users')]
    public function users(): array
    {
        return ['users' => []];
    }
}

// Namespace-based nested groups
$router->registerNamespace('App\Http\Controllers\Api\V1', null, '/api/v1');
```

## Group Middleware

Middleware can be applied to groups of routes:

```php
// Method-based group middleware
$router->group(['middleware' => [AuthMiddleware::class]], function(Router $router) {
    // Routes here will have the AuthMiddleware applied
});

// Attribute-based group middleware
#[RouteGroup(middleware: [AuthMiddleware::class])]
class UserController
{
    // Routes here will have the AuthMiddleware applied
}

// Namespace-based group middleware
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new AuthMiddleware()]
);
```

## Advanced Usage

### Combining Approaches

You can combine different approaches to create a flexible routing structure:

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

// Define additional routes in the same group
$router->group(['prefix' => '/api', 'name' => 'api'], function(Router $router) {
    $router->get('/status', function() {
        return ['status' => 'OK'];
    });
});
```

### Priority and Overrides

When using multiple approaches, the following priority order applies:

1. Route-specific settings (highest priority)
2. Attribute-based group settings
3. Method-based group settings
4. Namespace-based group settings (lowest priority)

For example:

```php
// Namespace-based group
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new ApiMiddleware()]
);

// Attribute-based group (overrides namespace-based group)
#[RouteGroup(prefix: '/api/v1', name: 'api.v1', middleware: [ApiV1Middleware::class])]
class ApiV1Controller
{
    // Route-specific settings (overrides attribute-based group)
    #[Route('/users', name: 'users', middleware: [UsersMiddleware::class])]
    public function users(): array
    {
        return ['users' => []];
    }
}
```

### Best Practices

1. **Consistent Naming**
   - Use consistent naming conventions for groups
   - Use dot notation for nested groups (e.g., 'api.v1.users')
   - Use plural nouns for resource groups (e.g., 'users', 'products')

2. **Logical Grouping**
   - Group routes by domain or feature
   - Use namespace-based groups for large applications
   - Use attribute-based groups for smaller controllers

3. **Middleware Organization**
   - Apply common middleware at the namespace or group level
   - Apply specific middleware at the route level
   - Order middleware from most general to most specific

4. **Prefix Structure**
   - Use consistent prefix structure (e.g., '/api/v1/users')
   - Avoid redundant prefixes
   - Use singular nouns for resource identifiers (e.g., '/user/{id}') 