<?php

use Piri\Core\Router;
use Example\Controller\HomeController;
use Example\Controller\UserController;
use Example\Middleware\AuthMiddleware;
use Example\Middleware\LoggingMiddleware;

// If router is not passed, create a new instance
if (!isset($router)) {
    $router = new Router();
}

// Add global middleware
$router->addGlobalMiddleware(new LoggingMiddleware());

// Register controllers with attributes
$router->registerClass(HomeController::class);
$router->registerClass(UserController::class);

// Define routes
$router->get('/', function() {
    return 'Welcome to Piri Router!';
});

// API routes with middleware
$router->group(['prefix' => '/api'], function(Router $router) {
    $router->get('/status', function() {
        return ['status' => 'OK', 'timestamp' => time()];
    });
});

// Add this file to route cache tracking
if (method_exists($router, 'addRouteFile')) {
    $router->addRouteFile(__FILE__);
}

// Return router instance
return $router;
