<?php
declare(strict_types=1);

namespace Zaco\Core;

final class View
{
    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars = []): void
    {
        $viewFile = __DIR__ . '/../../Views/' . ltrim($template, '/') . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        extract($vars, EXTR_SKIP);
        require $viewFile;
    }
}
