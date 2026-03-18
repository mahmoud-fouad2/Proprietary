<?php
declare(strict_types=1);

namespace Zaco\Core;

final class Avatar
{
    /**
     * Bootstrap badge classes (no custom colors).
     * Picked to keep good contrast in light/dark themes.
     */
    private const BADGE_CLASSES = [
        'text-bg-primary',
        'text-bg-secondary',
        'text-bg-success',
        'text-bg-danger',
        'text-bg-info',
        'text-bg-dark',
    ];

    public static function badgeClass(string|int $seed): string
    {
        $hash = crc32((string)$seed);
        $idx = (int)($hash % count(self::BADGE_CLASSES));
        return self::BADGE_CLASSES[$idx];
    }

    public static function initials(string $name, int $maxChars = 2): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '؟';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = (string)($parts[0] ?? $name);
        $second = (string)($parts[1] ?? '');

        $i1 = mb_substr($first, 0, 1, 'UTF-8');
        $i2 = $second !== '' ? mb_substr($second, 0, 1, 'UTF-8') : '';

        $out = $i1 . $i2;
        $out = trim($out);

        if ($out === '') {
            $out = mb_substr($name, 0, 1, 'UTF-8');
        }

        if (mb_strlen($out, 'UTF-8') > $maxChars) {
            $out = mb_substr($out, 0, $maxChars, 'UTF-8');
        }

        return mb_strtoupper($out, 'UTF-8');
    }

    /**
     * Render a deterministic avatar bubble.
     * Designed to work for both <img> (using size classes) and <span> fallback.
     */
    public static function html(string $name, string|int $seed, string $sizeClass = 'zaco-avatar-sm', string $extraClasses = ''): string
    {
        $initials = htmlspecialchars(self::initials($name), ENT_QUOTES, 'UTF-8');
        $badge = self::badgeClass($seed);
        $sizeClass = htmlspecialchars($sizeClass, ENT_QUOTES, 'UTF-8');
        $extraClasses = htmlspecialchars(trim($extraClasses), ENT_QUOTES, 'UTF-8');

        $classes = trim($sizeClass . ' rounded-circle d-inline-flex align-items-center justify-content-center fw-semibold ' . $badge . ' ' . $extraClasses);

        return '<span class="' . $classes . '" aria-hidden="true">' . $initials . '</span>';
    }
}
