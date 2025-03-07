# RouteGroup Attribute

The `RouteGroup` attribute provides a declarative way to define route groups at the controller class level. This document explains how to use the `RouteGroup` attribute effectively in your Piri Router applications.

## Table of Contents

- [RouteGroup Attribute](#routegroup-attribute)
  - [Table of Contents](#table-of-contents)
  - [Basic Usage](#basic-usage)
  - [Parameters](#parameters)
  - [Combining with Route Attributes](#combining-with-route-attributes)
  - [Group-Based Attribute Routing](#group-based-attribute-routing)
  - [Middleware Integration](#middleware-integration)
  - [Nested Groups](#nested-groups)
  - [Examples](#examples)
    - [API Controllers](#api-controllers)
    - [Admin Controllers](#admin-controllers)
    - [Resource Controllers](#resource-controllers)
  - [Best Practices](#best-practices)

## Basic Usage

The `RouteGroup` attribute is applied at the class level to define a route group for all routes in the controller:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/admin', name: 'admin')]
class AdminController
{
    #[Route('/dashboard')]
    public function dashboard(): string
    {
        return 'Admin Dashboard';
    }

    #[Route('/users')]
    public function users(): array
    {
        return ['users' => []];
    }
}
```

In this example, all routes in the `AdminController` will have the prefix `/admin` and will belong to the group named `admin`.

## Parameters

The `RouteGroup` attribute accepts the following parameters:

- `prefix` (string): The URL prefix for all routes in the group
- `name` (string): The name prefix for all named routes in the group
- `middleware` (array|string|object): The middleware to apply to all routes in the group

All parameters are optional, and you can use any combination of them:

```php
// With prefix only
#[RouteGroup(prefix: '/api')]

// With name only
#[RouteGroup(name: 'api')]

// With middleware only
#[RouteGroup(middleware: [ApiMiddleware::class])]

// With all parameters
#[RouteGroup(prefix: '/api', name: 'api', middleware: [ApiMiddleware::class])]
```

## Combining with Route Attributes

The `RouteGroup` attribute works seamlessly with the `Route` attribute:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/api', name: 'api')]
class ApiController
{
    // Results in route: GET /api/users with name 'api.users'
    #[Route('/users', name: 'users')]
    public function users(): array
    {
        return ['users' => []];
    }

    // Results in route: GET /api/users/{id} with name 'api.users.show'
    #[Route('/users/{id}', name: 'users.show')]
    public function show(array $params): array
    {
        return ['user' => $params['id']];
    }
}
```

## Group-Based Attribute Routing

In addition to the `RouteGroup` attribute, you can also use the `group` parameter in the `Route` attribute to associate routes with a group defined elsewhere:

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

## Middleware Integration

The `RouteGroup` attribute allows you to apply middleware to all routes in a controller:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;
use App\Middleware\AuthMiddleware;
use App\Middleware\LoggingMiddleware;

#[RouteGroup(middleware: [AuthMiddleware::class, LoggingMiddleware::class])]
class UserController
{
    // Both routes will have AuthMiddleware and LoggingMiddleware applied
    #[Route('/profile')]
    public function profile(): array
    {
        return ['profile' => 'user profile'];
    }

    #[Route('/settings')]
    public function settings(): array
    {
        return ['settings' => 'user settings'];
    }
}
```

You can also combine group middleware with route-specific middleware:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

#[RouteGroup(middleware: [AuthMiddleware::class])]
class UserController
{
    // Has only AuthMiddleware
    #[Route('/profile')]
    public function profile(): array
    {
        return ['profile' => 'user profile'];
    }

    // Has both AuthMiddleware and AdminMiddleware
    #[Route('/admin', middleware: [AdminMiddleware::class])]
    public function admin(): array
    {
        return ['admin' => 'admin panel'];
    }
}
```

## Nested Groups

You can create nested groups by combining the `RouteGroup` attribute with the `group` method:

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;
use Piri\Core\Router;

#[RouteGroup(prefix: '/api')]
class ApiController
{
    // ...
}

// In your routes.php file
$router->group(['prefix' => '/v1'], function(Router $router) {
    $router->registerClass(ApiController::class);
});

// Results in routes with prefix /v1/api/...
```

## Examples

### API Controllers

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;
use App\Middleware\ApiAuthMiddleware;

#[RouteGroup(prefix: '/api/v1', name: 'api.v1', middleware: [ApiAuthMiddleware::class])]
class UserApiController
{
    #[Route('/users', name: 'users.index')]
    public function index(): array
    {
        return ['users' => $this->userRepository->all()];
    }

    #[Route('/users', methods: ['POST'], name: 'users.store')]
    public function store(): array
    {
        // Create user logic
        return ['user' => $user, 'message' => 'User created'];
    }

    #[Route('/users/{id}', name: 'users.show')]
    public function show(array $params): array
    {
        return ['user' => $this->userRepository->find($params['id'])];
    }

    #[Route('/users/{id}', methods: ['PUT', 'PATCH'], name: 'users.update')]
    public function update(array $params): array
    {
        // Update user logic
        return ['user' => $user, 'message' => 'User updated'];
    }

    #[Route('/users/{id}', methods: ['DELETE'], name: 'users.destroy')]
    public function destroy(array $params): array
    {
        // Delete user logic
        return ['message' => 'User deleted'];
    }
}
```

### Admin Controllers

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;
use App\Middleware\AdminAuthMiddleware;

#[RouteGroup(prefix: '/admin', name: 'admin', middleware: [AdminAuthMiddleware::class])]
class DashboardController
{
    #[Route('', name: 'dashboard')]
    public function index(): string
    {
        return 'Admin Dashboard';
    }

    #[Route('/stats', name: 'stats')]
    public function stats(): array
    {
        return [
            'users' => 1000,
            'orders' => 500,
            'revenue' => 10000
        ];
    }
}

#[RouteGroup(prefix: '/admin', name: 'admin', middleware: [AdminAuthMiddleware::class])]
class UserManagementController
{
    #[Route('/users', name: 'users')]
    public function index(): array
    {
        return ['users' => $this->userRepository->all()];
    }

    #[Route('/users/create', name: 'users.create')]
    public function create(): string
    {
        return 'Create User Form';
    }

    #[Route('/users/{id}/edit', name: 'users.edit')]
    public function edit(array $params): string
    {
        return 'Edit User Form for User ' . $params['id'];
    }
}
```

### Resource Controllers

```php
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/products', name: 'products')]
class ProductController
{
    #[Route('', name: 'index')]
    public function index(): array
    {
        return ['products' => $this->productRepository->all()];
    }

    #[Route('/create', name: 'create')]
    public function create(): string
    {
        return 'Create Product Form';
    }

    #[Route('', methods: ['POST'], name: 'store')]
    public function store(): array
    {
        // Create product logic
        return ['product' => $product, 'message' => 'Product created'];
    }

    #[Route('/{id}', name: 'show')]
    public function show(array $params): array
    {
        return ['product' => $this->productRepository->find($params['id'])];
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(array $params): string
    {
        return 'Edit Product Form for Product ' . $params['id'];
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'], name: 'update')]
    public function update(array $params): array
    {
        // Update product logic
        return ['product' => $product, 'message' => 'Product updated'];
    }

    #[Route('/{id}', methods: ['DELETE'], name: 'destroy')]
    public function destroy(array $params): array
    {
        // Delete product logic
        return ['message' => 'Product deleted'];
    }
}
```

## Best Practices

1. **Group Related Routes**
   - Use `RouteGroup` to group related routes in a controller
   - Keep controllers focused on a single resource or feature
   - Use consistent naming conventions for groups and routes

2. **Middleware Organization**
   - Apply common middleware at the group level
   - Apply specific middleware at the route level
   - Keep middleware chain as short as possible

3. **Naming Conventions**
   - Use consistent naming conventions for groups and routes
   - Use dot notation for nested groups (e.g., 'api.v1.users')
   - Use plural nouns for resource groups (e.g., 'users', 'products')

4. **Prefix Structure**
   - Use consistent prefix structure (e.g., '/api/v1/users')
   - Avoid redundant prefixes
   - Use singular nouns for resource identifiers (e.g., '/user/{id}') 