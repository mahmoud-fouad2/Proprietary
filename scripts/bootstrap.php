<?php
declare(strict_types=1);

use Zaco\Core\Config;
use Zaco\Core\Db;

// Project autoload
require __DIR__ . '/../app/src/Autoload.php';

// Optional Composer autoload (mPDF, PHPMailer, ...)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

$configArr = require __DIR__ . '/../config/config.php';
$config = new Config($configArr);
$GLOBALS['config'] = $config;

date_default_timezone_set((string)$config->get('app.timezone', 'Asia/Riyadh'));
mb_internal_encoding('UTF-8');

// Base path (used for building URLs in emails)
$basePath = (string)$config->get('app.base_path', '');
$GLOBALS['basePath'] = $basePath;

$db = Db::fromConfig($config);
$GLOBALS['db'] = $db;

return [
    'config' => $config,
    'db' => $db,
];
