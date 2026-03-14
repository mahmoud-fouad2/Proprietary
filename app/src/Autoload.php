<?php
declare(strict_types=1);

// Minimal PSR-4 style autoloader for this project.

spl_autoload_register(static function (string $class): void {
    $prefix = 'Zaco\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
    $file = $baseDir . $relativePath;

    if (is_file($file)) {
        require $file;
    }
});
