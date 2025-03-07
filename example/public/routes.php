<?php

use Piri\Core\Router;
use Example\Middleware\LoggingMiddleware;
use Example\Middleware\AuthMiddleware;
use Example\Controller\HomeController;

// If router is not passed, create a new instance
if (!isset($router)) {
    $router = new Router();
}

// Add global middleware
$router->addGlobalMiddleware(new LoggingMiddleware());

// Register controllers
$router->registerClass(HomeController::class);

// Define the API group with explicit routes for api_root group
$router->group(['prefix' => '/api', 'name' => 'api_root'], function(Router $router) {
});

// Register other controllers by namespace
$router->registerNamespace('Example\Controller');

// Additional nested group example
$router->group(['prefix' => 'something'], function(Router $router) {
    $router->group(['prefix' => '/api', 'name' => 'api_root'], function(Router $router) {
        $router->get('/status', function() {
            return ['status' => 'OK', 'timestamp' => time()];
        });
    });
});

$router->get('/test', function() {
    return 'This path is from the routes.php file and not from the controller';
});

// Add this file to route cache tracking
if (method_exists($router, 'addRouteFile')) {
    $router->addRouteFile(__FILE__);
}

// Return router instance
return $router;
