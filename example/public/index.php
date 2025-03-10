<?php

// Try composer autoloader first
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',    // If example has its own vendor
    __DIR__ . '/../../vendor/autoload.php', // If using main project's vendor
    __DIR__ . '/../../autoload.php'         // For manual installation
];

$loaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die('Autoloader not found. Please run composer install in the example directory.');
}

// Register example-specific autoloader as fallback
spl_autoload_register(function ($class) {
    // Example namespace prefix
    $prefix = 'Example\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/../../vendor/autoload.php';

use Piri\Core\Router;

$router = new Router();

// Load routes
require __DIR__ . '/routes.php';

// Handle the request with configuration options
$router->handle([
    'cache_dir' => getenv('APP_ENV') !== 'local' ? __DIR__ . '/../cache' : null,
    'debug' => getenv('APP_ENV') === 'local',
    'json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
]); 