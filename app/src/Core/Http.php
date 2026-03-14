<?php
declare(strict_types=1);

namespace Zaco\Core;

use Zaco\Security\Csrf;

final class Http
{
    public static function basePath(): string
    {
        $bp = (string)($GLOBALS['basePath'] ?? '');
        if ($bp === '' || $bp === '/') {
            return '';
        }
        return rtrim($bp, '/');
    }

    public static function url(string $path): string
    {
        $p = '/' . ltrim($path, '/');
        $bp = self::basePath();
        return $bp === '' ? $p : ($bp . $p);
    }

    public static function asset(string $path): string
    {
        return self::url('/assets/' . ltrim($path, '/'));
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate CSRF hidden input field
     */
    public static function csrfInput(): string
    {
        $token = self::e(Csrf::token());
        return '<input type="hidden" name="_csrf" value="' . $token . '" />';
    }

    /**
     * Get value from GET parameters with sanitization
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get value from POST parameters
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get integer from GET
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = $_GET[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return (int)$value;
    }

    /**
     * Get string from GET with sanitization
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? null;
        if ($value === null || !is_string($value)) {
            return $default;
        }
        return trim($value);
    }

    /**
     * Set flash message in session
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Get and clear flash message
     */
    public static function getFlash(string $type): ?string
    {
        $message = $_SESSION['_flash'][$type] ?? null;
        if ($message !== null) {
            unset($_SESSION['_flash'][$type]);
        }
        return $message;
    }

    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && mb_strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request method is POST
     */
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Check if request method is GET
     */
    public static function isGet(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }

    /**
     * Send JSON response and exit
     */
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect with flash message
     */
    public static function redirectWithMessage(string $path, string $type, string $message): never
    {
        self::flash($type, $message);
        self::redirect($path);
    }

    /**
     * Build URL with query parameters
     */
    public static function buildUrl(string $path, array $params = []): string
    {
        $filtered = array_filter($params, fn($v) => $v !== '' && $v !== null);
        $query = http_build_query($filtered);
        $url = self::url($path);
        
        if ($query !== '') {
            $url .= '?' . $query;
        }
        
        return $url;
    }

    /**
     * Generate sort URL for table headers
     */
    public static function sortUrl(
        string $baseUrl,
        string $column,
        string $currentSort,
        string $currentDir,
        array $preserveParams = []
    ): string {
        $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = array_merge($preserveParams, ['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
        return self::buildUrl($baseUrl, $params);
    }

    /**
     * Get sort indicator
     */
    public static function sortIndicator(string $column, string $currentSort, string $currentDir): string
    {
        if ($currentSort !== $column) {
            return '';
        }
        return $currentDir === 'asc' ? '↑' : '↓';
    }

    /**
     * Get current path
     */
    public static function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        return (string)$path;
    }

    /**
     * Get current full URL
     */
    public static function currentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $protocol . '://' . $host . $uri;
    }

    /**
     * Get client IP address
     */
    public static function clientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? null;
            if ($ip !== null && $ip !== '') {
                // Handle comma-separated list (X-Forwarded-For)
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }
}
