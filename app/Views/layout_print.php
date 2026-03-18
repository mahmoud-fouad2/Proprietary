<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

$GLOBALS['__layoutRendered'] = true;

$title = $title ?? 'ZACO';
$content = $content ?? '';

$lang = I18n::locale();
$dir = I18n::dir();

?><!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="light dark" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="<?= Http::e(Http::asset('app.css')) ?>" />
</head>
<body>
  <?= $content ?>
</body>
</html>
