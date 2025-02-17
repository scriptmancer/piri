# Piri Router Documentation

## Table of Contents

1. [Installation](#installation)
2. [Basic Usage](#basic-usage)
3. [Route Definition](#route-definition)
4. [Middleware](#middleware)
5. [Route Groups](#route-groups)
6. [Route Parameters](#route-parameters)
7. [Named Routes](#named-routes)
8. [Advanced Features](#advanced-features)
9. [Best Practices](#best-practices)
10. [Examples](#examples)

## Installation

### Via Composer

```bash
composer require piri/router
```

### Manual Installation

1. Download the latest release
2. Include the autoloader:
```php
require_once 'path/to/piri/autoload.php';
```

## Basic Usage

### 1. Setting Up Your Entry Point (index.php)

```php
<?php
require_once 'vendor/autoload.php';

// Load routes
$router = require 'routes.php';

// Run the router
$router->run();
```

### 2. Defining Routes (routes.php)

```php
<?php
use Piri\Core\Router;

$router = new Router();

// Define routes
$router->get('/', function() {
    return 'Hello World!';
});

return $router;
```

## Route Definition

### Using Attributes (Recommended)

```php
use Piri\Attributes\Route;

#[Route(prefix: '/api', middleware: [AuthMiddleware::class])]
class UserController
{
    #[Route('/users', name: 'users.list')]
    public function list(): array
    {
        return ['users' => []];
    }

    #[Route('/users/{id}', methods: ['GET', 'POST'])]
    public function show(array $params): array
    {
        return ['user' => $params['id']];
    }
}

// Register in routes.php
$router->registerClass(UserController::class);
```

### Using Method Calls

```php
// Basic route
$router->get('/hello', function() {
    return 'Hello World!';
});

// Controller method
$router->get('/users', [UserController::class, 'index']);

// Named route
$router->get('/about', function() {
    return 'About Page';
}, 'about');
```

## Middleware

### Creating Middleware

```php
use Piri\Contracts\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            return ['error' => 'Unauthorized'];
        }
        
        return $next($parameters);
    }
}
```

### Applying Middleware

```php
// Global middleware
$router->addGlobalMiddleware(new LoggingMiddleware());

// Group middleware
$router->group(['middleware' => [AuthMiddleware::class]], function(Router $router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

// Route-specific middleware
#[Route('/admin', middleware: [AdminMiddleware::class])]
public function adminArea() {}
```

## Route Groups

```php
// Basic group
$router->group(['prefix' => '/admin'], function(Router $router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});

// Nested groups
$router->group(['prefix' => '/api', 'middleware' => [ApiMiddleware::class]], function(Router $router) {
    $router->group(['prefix' => '/v1'], function(Router $router) {
        $router->get('/users', [ApiController::class, 'users']);
    });
});
```

## Route Parameters

### Basic Parameters

```php
#[Route('/users/{id}')]
public function show(array $params): array
{
    $userId = $params['id'];
    return ['user' => $userId];
}
```

### Optional Parameters

```php
#[Route('/users/{id}/posts/{postId?}')]
public function posts(array $params): array
{
    $userId = $params['id'];
    $postId = $params['postId'] ?? null;
    return ['user' => $userId, 'post' => $postId];
}
```

### Pattern Constraints

```php
#[Route('/users/{id:\d+}')]
public function numericId(): string
{
    return 'numeric';
}

#[Route('/users/{username:[a-zA-Z]+}')]
public function alphaUsername(): string
{
    return 'alpha';
}
```

## Named Routes

### Defining Named Routes

```php
#[Route('/users/{id}', name: 'users.show')]
public function show(array $params): array
{
    return ['user' => $params['id']];
}
```

### Generating URLs

```php
use Piri\Core\Route;

$url = Route::url('users.show', ['id' => 123]);
// Output: /users/123
```

## Advanced Features

### HTTP Method Support

```php
// Single method
#[Route('/users', methods: ['POST'])]
public function create(): array {}

// Multiple methods
#[Route('/users/{id}', methods: ['GET', 'POST', 'PUT'])]
public function handle(): array {}
```

### Response Types

```php
// HTML Response
public function index(): string
{
    return '<h1>Welcome</h1>';
}

// JSON Response
public function api(): array
{
    return ['status' => 'success'];
}
```

### Route Caching

Route caching improves performance by storing compiled routes in a cache file. This eliminates the need to parse attributes and build routes on every request.

```php
// Enable route caching
if (getenv('APP_ENV') !== 'local') {
    $router->enableCache(__DIR__ . '/cache');
}

// Track route files for cache invalidation
$router->addRouteFile(__FILE__);
```

## Best Practices

1. **Organize Routes**
   - Keep routes in a separate file
   - Group related routes together
   - Use meaningful route names

2. **Use Attributes**
   - Prefer attribute-based routing over method calls
   - Keep route definitions close to their handlers

3. **Middleware**
   - Keep middleware focused and single-purpose
   - Use global middleware sparingly
   - Order middleware from most general to most specific

4. **Error Handling**
   - Always return appropriate HTTP status codes
   - Provide meaningful error messages
   - Log errors appropriately

## Examples

### Complete Application Structure

```
your-app/
├── public/
│   ├── index.php
│   └── .htaccess
├── src/
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   └── UserController.php
│   └── Middleware/
│       ├── AuthMiddleware.php
│       └── LoggingMiddleware.php
├── routes.php
└── composer.json
```

### Basic API Example

```php
#[Route(prefix: '/api/v1')]
class ApiController
{
    #[Route('/users', name: 'api.users.list')]
    public function list(): array
    {
        return ['users' => $this->getUsers()];
    }

    #[Route('/users', methods: ['POST'], name: 'api.users.create')]
    public function create(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return ['user' => $this->createUser($data)];
    }

    #[Route('/users/{id}', name: 'api.users.show')]
    public function show(array $params): array
    {
        return ['user' => $this->getUser($params['id'])];
    }
}
```

### Web Application Example

```php
#[Route(middleware: [WebMiddleware::class])]
class HomeController
{
    #[Route('/', name: 'home')]
    public function index(): string
    {
        return $this->view->render('home');
    }

    #[Route('/about', name: 'about')]
    public function about(): string
    {
        return $this->view->render('about');
    }

    #[Route('/contact', methods: ['GET', 'POST'], name: 'contact')]
    public function contact(): string|array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleContactForm();
        }
        return $this->view->render('contact');
    }
}
```

## Performance Considerations

### Route Caching

The router includes a caching mechanism that can significantly improve performance in production:

1. **When to Use Caching**
   - Always enable caching in production
   - Disable caching during development for easier debugging
   - Clear cache after deploying new routes

2. **Cache Configuration**
   ```php
   // In production
   if (getenv('APP_ENV') === 'production') {
       $router->enableCache(__DIR__ . '/cache')
              ->addRouteFile(__DIR__ . '/routes.php');
   }
   ```

3. **Cache Invalidation**
   - Cache is automatically invalidated when route files change
   - Manually clear cache after deploying: `$router->clearCache()`
   - Keep cache directory writable by web server

### Memory Usage

1. **Route Registration**
   - Group related routes to reduce memory overhead
   - Use controller classes instead of closures for better memory management
   - Avoid registering unused routes

2. **Middleware**
   - Keep middleware classes lightweight
   - Use global middleware sparingly
   - Consider lazy loading for heavy middleware

## Troubleshooting

### Common Issues

1. **404 Not Found Errors**
   - Check route registration order
   - Verify URL patterns and parameters
   - Ensure base path is correctly configured
   - Check if routes are properly cached

2. **Middleware Issues**
   - Verify middleware order
   - Check for infinite loops in middleware chain
   - Ensure all middleware implements MiddlewareInterface
   - Debug middleware execution with logging

3. **Caching Problems**
   - Verify cache directory permissions
   - Clear cache after route changes
   - Check cache file timestamps
   - Enable debug logging in development

### Debug Mode

Enable debug mode to get detailed error information:

```php
$router->setDebug(true);  // Enable in development only
```

Debug output includes:
- Route matching details
- Middleware execution order
- Cache status
- Pattern matching results

### Logging

Add logging middleware for debugging:

```php
class DebugMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        error_log(sprintf(
            "Route: %s %s\nParameters: %s",
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            json_encode($parameters)
        ));
        
        return $next($parameters);
    }
}
```

## Security Best Practices

1. **Input Validation**
   - Always validate route parameters
   - Use pattern constraints for sensitive routes
   - Sanitize output in handlers

2. **Middleware Security**
   - Implement authentication middleware
   - Add CSRF protection for forms
   - Use rate limiting for API routes

3. **Error Handling**
   - Don't expose internal errors in production
   - Log security-related issues
   - Implement proper HTTP status codes 

## Advanced Request Handling

### Type-Safe Parameters

```php
#[Route('/users/{id:\d+}/{status:active|inactive}')]
public function show(array $params): array
{
    // Parameters are automatically validated against patterns
    $id = (int)$params['id'];  // Guaranteed to be numeric
    $status = $params['status'];  // Guaranteed to be 'active' or 'inactive'
    return ['user' => $id, 'status' => $status];
}
```

### Request Validation

```php
class UserController
{
    #[Route('/users', methods: ['POST'])]
    public function create(): array
    {
        $data = $this->validateRequest([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'required|integer|min:18'
        ]);
        
        return ['user' => $this->createUser($data)];
    }
    
    private function validateRequest(array $rules): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $errors = [];
        
        foreach ($rules as $field => $ruleSet) {
            $rules = explode('|', $ruleSet);
            foreach ($rules as $rule) {
                if (!$this->validateField($data[$field] ?? null, $rule)) {
                    $errors[$field][] = "Failed {$rule} validation";
                }
            }
        }
        
        if (!empty($errors)) {
            http_response_code(422);
            throw new ValidationException($errors);
        }
        
        return $data;
    }
}
```

### Domain-Specific Routes

```php
#[Route(domain: 'api.example.com', prefix: '/v1')]
class ApiController
{
    #[Route('/status')]
    public function status(): array
    {
        return ['status' => 'ok'];
    }
}

#[Route(domain: '{tenant}.example.com')]
class TenantController
{
    #[Route('/dashboard')]
    public function dashboard(array $params): string
    {
        $tenant = $params['tenant'];
        return "Dashboard for {$tenant}";
    }
}
```

### Response Transformers

```php
#[Route('/users/{id}')]
#[Transform(UserTransformer::class)]
public function show(array $params): User
{
    return $this->userRepository->find($params['id']);
}

class UserTransformer
{
    public function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->format('Y-m-d')
        ];
    }
}
```

### Rate Limiting

```php
#[Route('/api/status')]
#[RateLimit(max: 60, period: 60)] // 60 requests per minute
public function status(): array
{
    return ['status' => 'ok'];
}
```

### Conditional Routes

```php
#[Route('/admin/settings', middleware: [AdminMiddleware::class])]
#[RouteCondition('isFeatureEnabled', 'advanced_settings')]
public function advancedSettings(): string
{
    return 'Advanced Settings Page';
}

// In your Router class
private function isFeatureEnabled(string $feature): bool
{
    return in_array($feature, $this->enabledFeatures);
}
```

### Error Handling with Custom Responses

```php
class ErrorHandler
{
    public function handle(\Throwable $e): mixed
    {
        if ($e instanceof ValidationException) {
            return [
                'error' => 'Validation failed',
                'details' => $e->getErrors()
            ];
        }
        
        if ($e instanceof RouteNotFoundException) {
            return [
                'error' => 'Not Found',
                'message' => 'The requested resource was not found'
            ];
        }
        
        // Log unexpected errors
        error_log($e->getMessage());
        
        return [
            'error' => 'Internal Server Error',
            'message' => $e->getMessage()
        ];
    }
}

// In your index.php
$router->setErrorHandler(new ErrorHandler());
```

### Route Caching with Versioning

```php
// Enable versioned cache
$router->enableCache(__DIR__ . '/cache', [
    'version' => '1.0.0',
    'environment' => 'production'
]);

// Cache is automatically invalidated when:
// - Application version changes
// - Environment changes
// - Route files are modified
``` 