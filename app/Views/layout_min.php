<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

$title = $title ?? 'ZACO';
$content = $content ?? '';
$currentYear = date('Y');

$lang = I18n::locale();
$dir = I18n::dir();

$adminlteCss = ($dir === 'rtl')
  ? Http::asset('vendor/adminlte/css/adminlte.rtl.min.css')
  : Http::asset('vendor/adminlte/css/adminlte.min.css');

?><!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="color-scheme" content="light dark" />

  <link rel="stylesheet" href="<?= Http::e(Http::asset('vendor/overlayscrollbars/css/overlayscrollbars.min.css')) ?>" />
  <link rel="stylesheet" href="<?= Http::e(Http::asset('vendor/bootstrap-icons/css/bootstrap-icons.min.css')) ?>" />
  <link rel="stylesheet" href="<?= Http::e($adminlteCss) ?>" />
  <link rel="stylesheet" href="<?= Http::e(Http::asset('zaco-adminlte.css')) ?>" />
</head>
<body class="login-page bg-body-secondary">
  <?= $content ?>

  <div id="zacoLoading" class="zaco-loading" hidden>
    <div class="zaco-loading__card">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="zaco-loading__text"><?= Http::e(I18n::t('loading.title')) ?></div>
    </div>
  </div>

  <div id="zacoToasts" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2050"></div>

  <script src="<?= Http::e(Http::asset('vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js')) ?>"></script>
  <script src="<?= Http::e(Http::asset('vendor/popper/popper.min.js')) ?>"></script>
  <script src="<?= Http::e(Http::asset('vendor/bootstrap/js/bootstrap.min.js')) ?>"></script>
  <script src="<?= Http::e(Http::asset('vendor/adminlte/js/adminlte.min.js')) ?>"></script>
  <script src="<?= Http::e(Http::asset('zaco-adminlte.js')) ?>"></script>
</body>
</html>
