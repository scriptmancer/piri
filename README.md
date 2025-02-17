# Piri Router

A modern, attribute-based PHP router with powerful features for building web applications.

## Requirements

- PHP 8.1 or higher
- `ext-mbstring` extension
- PSR-4 autoloading

## Features

- 🎯 Attribute-based routing
- 🔄 Support for all HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD)
- 🎭 Middleware support (global, group, and route-specific)
- 🌳 Route groups with prefix and middleware
- 🔍 Pattern-based route parameters with regex support
- 🎯 Named routes with URL generation
- ⚡ Route priority ordering
- 🔄 Optional parameters
- 🛡️ Type-safe implementation

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

### 1. Using Attributes (Recommended)

```php
use Piri\Attributes\Route;
use Piri\Core\Router;

#[Route(prefix: '/api', middleware: [AuthMiddleware::class])]
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

// Match a route
$route = $router->match('GET', '/api/users/123');
$result = $router->execute($route);
```

### 2. Using Method Calls

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

// Route groups
$router->group(['prefix' => '/admin', 'middleware' => [AdminAuth::class]], function(Router $router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});
```

### 3. Basic Integration Example

```php
// index.php
<?php

require_once 'vendor/autoload.php'; // or your custom autoloader

use Piri\Core\Router;
use Piri\Exceptions\RouteNotFoundException;

$router = new Router();

// Register your routes
$router->get('/', function() {
    return 'Welcome!';
});

$router->get('/hello/{name}', function(array $params) {
    return 'Hello, ' . $params['name'];
});

// Handle the request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    $route = $router->match($method, $path);
    $result = $router->execute($route);
    
    // Handle the response
    if (is_array($result)) {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        echo $result;
    }
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    echo '404 Not Found';
} catch (\Exception $e) {
    http_response_code(500);
    echo 'Internal Server Error';
}
```

### 4. Middleware Example

```php
use Piri\Contracts\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            return 'Unauthorized';
        }
        
        return $next($parameters);
    }
}

// Apply globally
$router->addGlobalMiddleware(new LoggingMiddleware());

// Apply to specific routes
$router->get('/profile', [UserController::class, 'profile'])
    ->middleware(new AuthMiddleware());
```

## Route Patterns

You can use regex patterns in your route parameters:

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

// Using method calls
$router->get('/posts/{id:\d+}', function(array $params) {
    return 'Post ID: ' . $params['id'];
});
```

## Middleware

Middleware can be added at different levels:

```php
// Global middleware
$router->addGlobalMiddleware(new LoggingMiddleware());

// Group middleware
#[Route(prefix: '/admin', middleware: [AuthMiddleware::class])]
class AdminController
{
    // Route middleware
    #[Route('/dashboard', middleware: [DashboardMiddleware::class])]
    public function dashboard(): string
    {
        return 'dashboard';
    }
}
```

## Named Routes

Generate URLs for named routes:

```php
use Piri\Core\Route;

$url = Route::url('users.show', ['id' => 123]);
// Output: /api/users/123
```

## Testing

```bash
./vendor/bin/phpunit
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/scriptmancer/piri.git
cd piri

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse

# Check coding standards
composer cs-check
```

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/scriptmancer/piri/tags).

## Security

If you discover any security related issues, please email ben@gokhansarigul.com instead of using the issue tracker.

## Credits

- [Gokhan SARIGUL](https://github.com/gsarigul84)

## License
MIT
