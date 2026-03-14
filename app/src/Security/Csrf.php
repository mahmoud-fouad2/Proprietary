<?php
declare(strict_types=1);

namespace Zaco\Security;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        if (!isset($_SESSION['_csrf'])) {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals((string)$_SESSION['_csrf'], $token);
    }
}
