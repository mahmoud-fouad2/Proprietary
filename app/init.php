<?php
/**
 * ZACO Assets - Application Bootstrap
 * 
 * @author    Mahmoud Fouad <mahmoud.a.fouad2@gmail.com>
 * @copyright Copyright (c) 2024-<?= date('Y') ?> Mahmoud Fouad
 * @link      https://ma-fo.info
 * @license   Proprietary - All Rights Reserved
 */
declare(strict_types=1);

use DateTimeImmutable;
use Zaco\Core\Config;
use Zaco\Core\Db;
use Zaco\Core\I18n;
use Zaco\Core\Router;
use Zaco\Security\Auth;

require __DIR__ . '/src/Autoload.php';

// Optional Composer autoload (e.g., mPDF)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

$configArr = require __DIR__ . '/../config/config.php';
$config = new Config($configArr);
// Make config accessible for controllers/services that need deployment-specific paths.
$GLOBALS['config'] = $config;

date_default_timezone_set((string)$config->get('app.timezone', 'Asia/Riyadh'));
mb_internal_encoding('UTF-8');

$env = (string)$config->get('app.env', 'development');
if ($env === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Centralized logging (keeps display_errors behavior, but always logs to storage/logs)
$projectRoot = rtrim((string)dirname(__DIR__), '/\\');
$storageDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
$logsDir = $storageDir . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0775, true);
}

// Ensure uploads directory exists
$uploadsDir = $storageDir . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}

$phpErrorLogFile = $logsDir . DIRECTORY_SEPARATOR . 'php-error.log';
$appLogFile = $logsDir . DIRECTORY_SEPARATOR . 'app.log';

ini_set('log_errors', '1');
ini_set('error_log', $phpErrorLogFile);

$logApp = static function (string $level, string $message, array $context = []) use ($appLogFile): void {
    $ts = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $req = (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . (string)($_SERVER['REQUEST_URI'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $uid = '';
    try {
        $auth = $GLOBALS['auth'] ?? null;
        if ($auth instanceof Auth) {
            $u = $auth->user();
            if ($u) {
                $uid = (string)($u['id'] ?? '');
            }
        }
    } catch (Throwable) {
        $uid = '';
    }

    $ctx = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    if (!is_string($ctx)) {
        $ctx = '';
    }
    $line = '[' . $ts . '] ' . strtoupper($level) . ' ' . trim($req) . ($ip !== '' ? (' ip=' . $ip) : '') . ($uid !== '' ? (' uid=' . $uid) : '') . ' :: ' . $message . ($ctx !== '' ? (' ' . $ctx) : '') . "\n";
    @file_put_contents($appLogFile, $line, FILE_APPEND);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logApp): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    $logApp('error', $message, ['severity' => $severity, 'file' => $file, 'line' => $line]);
    // Let PHP handle it too (display/log via php-error.log)
    return false;
});

set_exception_handler(static function (Throwable $e) use ($logApp, $env): void {
    $logApp('exception', $e->getMessage(), [
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($env !== 'production') {
        echo '<h1>Unhandled Exception</h1>';
        echo '<pre>' . htmlspecialchars($e->__toString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo 'Server error';
});

register_shutdown_function(static function () use ($logApp): void {
    $err = error_get_last();
    if (!$err || !is_array($err)) {
        return;
    }

    $type = (int)($err['type'] ?? 0);
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($type, $fatalTypes, true)) {
        return;
    }

    $logApp('fatal', (string)($err['message'] ?? 'Fatal error'), [
        'type' => $type,
        'file' => (string)($err['file'] ?? ''),
        'line' => (int)($err['line'] ?? 0),
    ]);
});

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// Secure session defaults
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$forwardedProto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$cookieSecure = $https || (mb_strtolower($forwardedProto) === 'https');

session_name('ZACOSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

I18n::boot();

// CSP nonce (allows safe inline scripts without enabling unsafe-inline)
try {
    $cspNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
} catch (\Throwable) {
    $cspNonce = '';
}
$GLOBALS['cspNonce'] = $cspNonce;

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(self), microphone=()');
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'" . ($cspNonce !== '' ? " 'nonce-" . $cspNonce . "'" : "") . "; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

if ($cookieSecure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-site');

$basePath = (string)$config->get('app.base_path', '');
if ($basePath === '') {
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
}

$db = Db::fromConfig($config);
$GLOBALS['db'] = $db;

// Ensure at least one organization exists (for multi-organization filtering).
// Safe on older databases (table might not exist yet).
try {
    $stmt = $db->query('SELECT COUNT(*) AS c FROM organizations');
    $row = $stmt->fetch();
    $count = (int)($row['c'] ?? 0);
    if ($count === 0) {
        $name = null;
        try {
            $s = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'org_name' LIMIT 1");
            $s->execute();
            $r = $s->fetch();
            $v = $r ? trim((string)($r['setting_value'] ?? '')) : '';
            if ($v !== '') $name = $v;
        } catch (\Throwable) {
            $name = null;
        }
        if ($name === null) {
            $name = 'المنظمة الرئيسية';
        }
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $ins = $db->prepare('INSERT INTO organizations (name,is_active,created_at,updated_at) VALUES (?,?,?,?)');
        $ins->execute([$name, 1, $now, $now]);
    }
} catch (\Throwable) {
    // ignore
}

$auth = new Auth($db);

$router = new Router($basePath);
$GLOBALS['basePath'] = $basePath;
$GLOBALS['router'] = $router;

require __DIR__ . '/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
