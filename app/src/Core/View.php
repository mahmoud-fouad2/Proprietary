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

        // Some views render a full document by requiring shell/layout themselves.
        // Others may output only the page body. To avoid unstyled pages, we wrap
        // body-only output into the main shell when needed.
        $GLOBALS['__layoutRendered'] = false;

        extract($vars, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $out = (string)ob_get_clean();

        $layoutRendered = (bool)($GLOBALS['__layoutRendered'] ?? false);
        $looksLikeFullDoc = (stripos($out, '<!doctype') !== false) || (stripos($out, '<html') !== false);
        if ($layoutRendered || $looksLikeFullDoc) {
            echo $out;
            return;
        }

        // Wrap raw output in the standard app chrome.
        $content = $out;
        /** @var mixed $title */
        $title = $title ?? '';
        require __DIR__ . '/../../Views/shell.php';
    }
}
