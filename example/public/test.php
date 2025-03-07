<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Piri\Core\Router;
use Example\Controller\HomeController;

// Create a new router
$router = new Router();

// Register the HomeController
$router->registerClass(HomeController::class);

// Define a group
$router->group(['prefix' => '/api', 'name' => 'api_root'], function(Router $router) {
    $router->get('/test', function() {
        return ['message' => 'Test route'];
    });
    
    // Add a route for the root of the group
    $router->get('', function() {
        return ['message' => 'API Root'];
    });
    
    // Add a route for /api/status
    $router->get('/status', function() {
        return ['message' => 'API Status'];
    });
});

// Print all routes for debugging
echo "All Routes:\n";
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
    }
}

// Print groups registry
echo "\nGroups Registry:\n";
$reflection = new ReflectionClass($router);
$groupsProperty = $reflection->getProperty('groups');
$groupsProperty->setAccessible(true);
$groups = $groupsProperty->getValue($router);

foreach ($groups as $name => $data) {
    echo "Group: $name\n";
    echo "  Prefix: '" . $data['prefix'] . "'\n";
}

// Test matching routes
$tests = [
    ['GET', '/'],
    ['GET', '/about'],
    ['GET', '/api'],
    ['GET', '/api/status'],
    ['GET', '/api/test']
];

echo "\nRoute Matching Tests:\n";
foreach ($tests as [$method, $path]) {
    try {
        $route = $router->match($method, $path);
        echo "$method $path: MATCHED\n";
        
        // Execute the route
        $result = $router->execute($route);
        echo "  Result: " . (is_string($result) ? $result : json_encode($result)) . "\n";
    } catch (Exception $e) {
        echo "$method $path: NOT MATCHED - " . $e->getMessage() . "\n";
    }
} 