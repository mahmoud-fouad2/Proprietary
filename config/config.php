<?php
declare(strict_types=1);

// Load defaults, then allow local override via config.local.php
$config = require __DIR__ . '/config.sample.php';

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;
