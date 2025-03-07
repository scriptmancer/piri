# Namespace Registration

The namespace registration feature in Piri Router allows you to register all controllers in a namespace at once, making it easier to organize your routes and apply common settings to groups of controllers.

## Table of Contents

- [Namespace Registration](#namespace-registration)
  - [Table of Contents](#table-of-contents)
  - [Basic Usage](#basic-usage)
  - [Advanced Usage](#advanced-usage)
    - [With Prefix, Name, and Middleware](#with-prefix-name-and-middleware)
    - [With Custom Directory](#with-custom-directory)
  - [How It Works](#how-it-works)
  - [Integration with Group-Based Routing](#integration-with-group-based-routing)
  - [Examples](#examples)
    - [API Controllers](#api-controllers)
    - [Admin Controllers](#admin-controllers)
    - [Multiple Namespaces](#multiple-namespaces)
  - [Best Practices](#best-practices)
  - [Troubleshooting](#troubleshooting)
    - [Common Issues](#common-issues)
    - [Debugging](#debugging)

## Basic Usage

The simplest way to register all controllers in a namespace:

```php
// Register all controllers in the App\Http\Controllers namespace
$router->registerNamespace('App\Http\Controllers');
```

This will scan the directory associated with the namespace, find all controller classes, and register them with the router.

## Advanced Usage

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
```

This is equivalent to wrapping all controllers in the namespace in a route group with the specified prefix, name, and middleware.

### With Custom Directory

If the namespace doesn't follow PSR-4 autoloading standards, or if you want to specify a custom directory, you can provide the directory parameter:

```php
// Register all controllers in the App\Http\Controllers namespace
// from a custom directory
$router->registerNamespace(
    'App\Http\Controllers', 
    __DIR__ . '/src/Controllers'
);
```

## How It Works

The `registerNamespace` method works as follows:

1. It determines the directory associated with the namespace using Composer's autoloader.
2. If a custom directory is provided, it uses that instead.
3. It scans the directory for PHP files.
4. For each PHP file, it extracts the class name and checks if it's a valid controller class.
5. If a prefix, name, or middleware is provided, it creates a group with those settings.
6. It registers each controller class with the router, applying the group settings if applicable.

## Integration with Group-Based Routing

Namespace registration works seamlessly with group-based attribute routing:

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
$router->registerNamespace('App\Http\Controllers\Api');

// Define the group with a prefix
$router->group(['prefix' => '/api', 'name' => 'api_root'], function(Router $router) {
    // Routes from ApiController with group:'api_root' will be automatically
    // registered with the prefix '/api'
});
```

The order of registration is important:
1. First register the controllers using `registerNamespace`
2. Then define the groups with matching names

## Examples

### API Controllers

```php
// Register all controllers in the App\Http\Controllers\Api namespace
// with the prefix '/api', the name 'api', and the ApiAuthMiddleware
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new ApiAuthMiddleware()]
);

// This will register all of these controllers:
// - App\Http\Controllers\Api\UserController
// - App\Http\Controllers\Api\ProductController
// - App\Http\Controllers\Api\OrderController
// - etc.

// All routes in these controllers will have:
// - The prefix '/api'
// - The name prefix 'api.'
// - The ApiAuthMiddleware applied
```

### Admin Controllers

```php
// Register all controllers in the App\Http\Controllers\Admin namespace
// with the prefix '/admin', the name 'admin', and the AdminAuthMiddleware
$router->registerNamespace(
    'App\Http\Controllers\Admin', 
    null, 
    '/admin', 
    'admin', 
    [new AdminAuthMiddleware()]
);

// This will register all of these controllers:
// - App\Http\Controllers\Admin\DashboardController
// - App\Http\Controllers\Admin\UserController
// - App\Http\Controllers\Admin\SettingsController
// - etc.

// All routes in these controllers will have:
// - The prefix '/admin'
// - The name prefix 'admin.'
// - The AdminAuthMiddleware applied
```

### Multiple Namespaces

You can register multiple namespaces with different settings:

```php
// Register API controllers
$router->registerNamespace(
    'App\Http\Controllers\Api', 
    null, 
    '/api', 
    'api', 
    [new ApiAuthMiddleware()]
);

// Register Admin controllers
$router->registerNamespace(
    'App\Http\Controllers\Admin', 
    null, 
    '/admin', 
    'admin', 
    [new AdminAuthMiddleware()]
);

// Register Web controllers
$router->registerNamespace(
    'App\Http\Controllers\Web', 
    null, 
    '', 
    'web', 
    [new WebAuthMiddleware()]
);
```

## Best Practices

1. **Organize Controllers by Namespace**
   - Group related controllers in the same namespace
   - Use a consistent naming convention for namespaces
   - Keep namespace hierarchy shallow (2-3 levels)

2. **Use Meaningful Prefixes**
   - Use prefixes that match the namespace structure
   - Keep prefixes short and descriptive
   - Use consistent casing (e.g., lowercase with hyphens)

3. **Apply Middleware Appropriately**
   - Apply common middleware at the namespace level
   - Apply specific middleware at the controller or route level
   - Keep middleware chain as short as possible

4. **Naming Conventions**
   - Use consistent naming conventions for namespaces and routes
   - Use plural nouns for resource controllers (e.g., `UsersController`)
   - Use singular nouns for non-resource controllers (e.g., `AuthController`)

## Troubleshooting

### Common Issues

1. **Controllers Not Found**
   - Ensure the namespace follows PSR-4 autoloading standards
   - Check if the directory exists and is readable
   - Verify that the controller classes are in the correct namespace

2. **Routes Not Registered**
   - Check if the controller classes have route attributes
   - Ensure the controller classes are instantiable (not abstract)
   - Verify that the controller methods have the correct visibility (public)

3. **Middleware Not Applied**
   - Check if the middleware classes exist and implement the MiddlewareInterface
   - Ensure the middleware classes are instantiable
   - Verify that the middleware is correctly registered

### Debugging

To debug namespace registration, you can use the following approach:

```php
try {
    $router->registerNamespace(
        'App\Http\Controllers\Api', 
        null, 
        '/api', 
        'api', 
        [new ApiAuthMiddleware()]
    );
    echo "Successfully registered namespace\n";
} catch (\Exception $e) {
    echo "Error registering namespace: " . $e->getMessage() . "\n";
}

// Print all routes for debugging
echo "\nAll Routes:\n";
foreach ($router->getRoutes() as $method => $routes) {
    echo "Method: $method\n";
    foreach ($routes as $path => $routeData) {
        echo "  Path: '$path'\n";
        if (isset($routeData['group'])) {
            echo "    Group: " . $routeData['group'] . "\n";
        }
        if (isset($routeData['pattern'])) {
            echo "    Pattern: " . $routeData['pattern'] . "\n";
        }
        echo "    Handler: " . (is_array($routeData['handler']) ? get_class($routeData['handler'][0]) . '::' . $routeData['handler'][1] : 'Closure') . "\n";
        if (!empty($routeData['middleware'])) {
            echo "    Middleware: " . count($routeData['middleware']) . "\n";
        }
        echo "\n";
    }
}
``` 