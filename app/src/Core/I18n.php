<?php
declare(strict_types=1);

namespace Zaco\Core;

final class I18n
{
    /** @var array<string, array<string,string>> */
    private static array $dict = [];

    public static function locale(): string
    {
        $lang = (string)($GLOBALS['lang'] ?? 'ar');
        return in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
    }

    public static function dir(): string
    {
        return self::locale() === 'ar' ? 'rtl' : 'ltr';
    }

    public static function boot(): void
    {
        $lang = 'ar';
        $cookie = (string)($_COOKIE['lang'] ?? '');
        if (in_array($cookie, ['ar', 'en'], true)) {
            $lang = $cookie;
        }
        $session = $_SESSION['lang'] ?? null;
        if (is_string($session) && in_array($session, ['ar', 'en'], true)) {
            $lang = $session;
        }
        $GLOBALS['lang'] = $lang;

        if (self::$dict === []) {
            self::$dict = [
                'ar' => require __DIR__ . '/../../i18n/ar.php',
                'en' => require __DIR__ . '/../../i18n/en.php',
            ];
        }
    }

    /** @param array<string,string|int|float> $params */
    public static function t(string $key, array $params = []): string
    {
        $lang = self::locale();
        $fallback = self::$dict['ar'][$key] ?? $key;
        $value = self::$dict[$lang][$key] ?? $fallback;

        foreach ($params as $k => $v) {
            $value = str_replace('{' . $k . '}', (string)$v, $value);
        }
        return $value;
    }
}
