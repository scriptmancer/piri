<?php

/**
 * Simple PSR-4 Autoloader
 */
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'Piri\\';

    // Base directory for the namespace prefix
    $baseDir = __DIR__ . '/src/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators
    // and append with .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
}); 