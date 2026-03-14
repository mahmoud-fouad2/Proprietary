<?php
declare(strict_types=1);
use Zaco\Core\Http;
use Zaco\Core\I18n;
use Zaco\Security\Csrf;

$title = $title ?? 'ZACO';
$content = $content ?? '';
$user = $user ?? null;
$currentYear = date('Y');
$lang = I18n::locale();
$dir = I18n::dir();

// Determine current path for active nav
$basePath = $GLOBALS['basePath'] ?? '';
$requestUri = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
$currentPath = $basePath !== '' ? substr($requestUri, strlen($basePath)) : $requestUri;
if ($currentPath === '' || $currentPath === false) $currentPath = '/';

$breadcrumbsComputed = [];
if (isset($breadcrumbs) && is_array($breadcrumbs)) {
  $breadcrumbsComputed = $breadcrumbs;
} elseif ($currentPath !== '/') {
  $breadcrumbsComputed[] = ['label' => I18n::t('nav.dashboard'), 'href' => Http::url('/')];

  $sectionMap = [
    '/inventory' => ['label' => I18n::t('nav.inventory'), 'href' => Http::url('/inventory')],
    '/custody' => ['label' => I18n::t('nav.custody'), 'href' => Http::url('/custody')],
    '/employees' => ['label' => I18n::t('nav.employees'), 'href' => Http::url('/employees')],
    '/software' => ['label' => I18n::t('nav.software'), 'href' => Http::url('/software')],
    '/cleaning' => ['label' => I18n::t('nav.cleaning'), 'href' => Http::url('/cleaning')],
    '/users' => ['label' => I18n::t('nav.users'), 'href' => Http::url('/users')],
    '/settings' => ['label' => I18n::t('nav.settings'), 'href' => Http::url('/settings')],
  ];

  $section = null;
  foreach ($sectionMap as $prefix => $info) {
    if (str_starts_with($currentPath, $prefix)) {
      $section = $info;
      break;
    }
  }

  if ($section) {
    $breadcrumbsComputed[] = $section;
  }

  $titleStr = trim((string)$title);
  $dashLabel = (string)I18n::t('nav.dashboard');
  $sectionLabel = $section ? (string)($section['label'] ?? '') : '';
  if ($titleStr !== '' && $titleStr !== 'ZACO' && $titleStr !== $dashLabel && $titleStr !== $sectionLabel) {
    $breadcrumbsComputed[] = ['label' => $titleStr];
  }
}

function navActive(string $href, string $cur): bool {
  if ($href === '/') return ($cur === '/');
  return (str_starts_with($cur, $href));
}

$adminlteCss = ($dir === 'rtl')
  ? Http::asset('vendor/adminlte/css/adminlte.rtl.min.css')
  : Http::asset('vendor/adminlte/css/adminlte.min.css');

?><!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="author" content="Mahmoud Fouad" />
  <meta name="developer" content="Mahmoud Fouad - mahmoud.a.fouad2@gmail.com" />
  <meta name="contact" content="+966530047640, +20-1116588189" />
  <meta name="copyright" content="<?= (int)date('Y') ?> Mahmoud Fouad. All rights reserved." />
  <meta name="designer" content="ma-fo.info" />
  <!-- 
    System Developed by: Mahmoud Fouad
    Email: mahmoud.a.fouad2@gmail.com
    Portfolio: ma-fo.info
    Mobile: +966 530047640 | +20 1116588189
    Copyright © <?= (int)date('Y') ?> Mahmoud Fouad. All Rights Reserved.
  -->
  <title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="<?= Http::e(Http::asset('vendor/overlayscrollbars/css/overlayscrollbars.min.css')) ?>" />
  <link rel="stylesheet" href="<?= Http::e(Http::asset('vendor/bootstrap-icons/css/bootstrap-icons.min.css')) ?>" />
  <link rel="stylesheet" href="<?= Http::e($adminlteCss) ?>" />
  <link rel="stylesheet" href="<?= Http::e(Http::asset('zaco-adminlte.css')) ?>" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
              <i class="bi bi-list"></i>
            </a>
          </li>
        </ul>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="<?= Http::e($lang === 'ar' ? 'بحث' : 'Search') ?>">
              <i class="bi bi-search"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 320px">
              <form id="zacoNavSearchForm" method="get" action="<?= Http::e(Http::url('/inventory')) ?>" class="vstack gap-2">
                <div>
                  <label class="form-label small mb-1" for="zacoNavSearchModule"><?= Http::e($lang === 'ar' ? 'بحث في' : 'Search in') ?></label>
                  <select id="zacoNavSearchModule" class="form-select" name="_module">
                    <option value="inventory"><?= Http::e($lang === 'ar' ? 'الأصول' : 'Assets') ?></option>
                    <option value="employees"><?= Http::e($lang === 'ar' ? 'الموظفون' : 'Employees') ?></option>
                    <option value="custody"><?= Http::e($lang === 'ar' ? 'العُهد' : 'Custody') ?></option>
                    <option value="software"><?= Http::e($lang === 'ar' ? 'البرامج' : 'Software') ?></option>
                  </select>
                </div>
                <div>
                  <label class="form-label small mb-1" for="zacoNavSearchQ"><?= Http::e($lang === 'ar' ? 'كلمة البحث' : 'Query') ?></label>
                  <input id="zacoNavSearchQ" class="form-control" name="q" placeholder="<?= Http::e($lang === 'ar' ? 'ابحث...' : 'Search...') ?>" />
                </div>
                <div class="d-grid">
                  <button class="btn btn-primary" type="submit"><?= Http::e($lang === 'ar' ? 'بحث' : 'Search') ?></button>
                </div>
              </form>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-lang-switch href="<?= Http::e(Http::url('/lang?set=' . ($lang === 'ar' ? 'en' : 'ar') . '&r=' . urlencode($currentPath))) ?>" role="button" title="<?= Http::e($lang === 'ar' ? 'تغيير اللغة' : 'Change language') ?>">
              <i class="bi bi-translate"></i>
              <span class="d-none d-sm-inline ms-1"><?= Http::e($lang === 'ar' ? I18n::t('ui.lang_en') : I18n::t('ui.lang_ar')) ?></span>
            </a>
          </li>

          <?php if ($user): ?>
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown" id="notificationsDropdown">
              <a class="nav-link position-relative" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="<?= Http::e($lang === 'ar' ? 'الإشعارات' : 'Notifications') ?>">
                <i class="bi bi-bell"></i>
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill d-none" id="notifBadge" style="font-size: 0.65rem">0</span>
              </a>
              <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 340px; max-width: 400px;">
                <div class="dropdown-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                  <strong><?= Http::e($lang === 'ar' ? 'الإشعارات' : 'Notifications') ?></strong>
                  <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="markAllReadBtn" style="font-size: 0.8rem">
                    <?= Http::e($lang === 'ar' ? 'قراءة الكل' : 'Mark all read') ?>
                  </button>
                </div>
                <div class="notifications-list" id="notificationsList" style="max-height: 350px; overflow-y: auto;">
                  <div class="text-center text-muted py-4" id="notifLoading">
                    <div class="spinner-border spinner-border-sm"></div>
                  </div>
                  <div class="text-center text-muted py-4 d-none" id="notifEmpty">
                    <i class="bi bi-bell-slash" style="font-size: 2rem"></i>
                    <div class="mt-2 small"><?= Http::e($lang === 'ar' ? 'لا توجد إشعارات جديدة' : 'No new notifications') ?></div>
                  </div>
                </div>
              </div>
            </li>

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline ms-1"><?= htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
              <div class="dropdown-menu dropdown-menu-end">
                <div class="dropdown-item-text">
                  <div class="fw-bold"><?= htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="text-muted small"><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <form method="post" action="<?= Http::e(Http::url('/logout')) ?>" class="px-3 py-1" data-loading data-confirm="<?= Http::e(I18n::t('common.confirm')) ?>">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
                  <button class="btn btn-outline-danger w-100" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    <?= Http::e(I18n::t('nav.logout')) ?>
                  </button>
                </form>
              </div>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="<?= Http::e(Http::url('/')) ?>" class="brand-link">
          <img
            src="<?= Http::e(Http::url('/branding/logo')) ?>"
            alt="<?= Http::e(I18n::t('app.name')) ?>"
            class="brand-image app-logo"
          />
        </a>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <?php $auth = $GLOBALS['auth'] ?? null; ?>
          <ul
            class="nav nav-pills nav-sidebar flex-column"
            data-lte-toggle="treeview"
            role="navigation"
            aria-label="<?= Http::e($lang === 'ar' ? 'التنقل الرئيسي' : 'Main navigation') ?>"
            data-accordion="true"
            id="navigation"
          >
            <!-- Dashboard -->
            <li class="nav-item">
              <a class="nav-link<?= navActive('/', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/')) ?>">
                <i class="nav-icon bi bi-speedometer"></i>
                <p><?= Http::e(I18n::t('nav.dashboard')) ?></p>
              </a>
            </li>

            <!-- Asset Management Group -->
            <?php 
              $showAssetGroup = ($auth && (
                ($auth->can('view_assets') && $auth->navVisible('inventory')) ||
                ($auth->can('view_custody') && $auth->navVisible('custody'))
              ));
              $assetGroupOpen = (navActive('/inventory', $currentPath) || navActive('/custody', $currentPath));
            ?>
            <?php if ($showAssetGroup): ?>
              <li class="nav-item<?= $assetGroupOpen ? ' menu-open' : '' ?>">
                <a href="#" class="nav-link<?= $assetGroupOpen ? ' active' : '' ?>">
                  <i class="nav-icon bi bi-archive"></i>
                  <p>
                    <?= Http::e($lang === 'ar' ? 'إدارة الأصول' : 'Asset Management') ?>
                    <i class="nav-arrow bi bi-chevron-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <?php if ($auth && $auth->can('view_assets') && $auth->navVisible('inventory')): ?>
                    <li class="nav-item">
                      <a class="nav-link<?= navActive('/inventory', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/inventory')) ?>">
                        <i class="nav-icon bi bi-box-seam"></i>
                        <p><?= Http::e(I18n::t('nav.inventory')) ?></p>
                      </a>
                    </li>
                  <?php endif; ?>
                  <?php if ($auth && $auth->can('view_custody') && $auth->navVisible('custody')): ?>
                    <li class="nav-item">
                      <a class="nav-link<?= navActive('/custody', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/custody')) ?>">
                        <i class="nav-icon bi bi-clipboard-check"></i>
                        <p><?= Http::e(I18n::t('nav.custody')) ?></p>
                      </a>
                    </li>
                  <?php endif; ?>
                </ul>
              </li>
            <?php endif; ?>

            <!-- Employees -->
            <?php if ($auth && $auth->can('view_employees') && $auth->navVisible('employees')): ?>
              <li class="nav-item">
                <a class="nav-link<?= navActive('/employees', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/employees')) ?>">
                  <i class="nav-icon bi bi-people"></i>
                  <p><?= Http::e(I18n::t('nav.employees')) ?></p>
                </a>
              </li>
            <?php endif; ?>

            <!-- Software -->
            <?php if ($auth && $auth->can('view_software') && $auth->navVisible('software')): ?>
              <li class="nav-item">
                <a class="nav-link<?= navActive('/software', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/software')) ?>">
                  <i class="nav-icon bi bi-window"></i>
                  <p><?= Http::e(I18n::t('nav.software')) ?></p>
                </a>
              </li>
            <?php endif; ?>

            <!-- Cleaning -->
            <?php if ($auth && $auth->can('cleaning') && $auth->navVisible('cleaning')): ?>
              <li class="nav-item">
                <a class="nav-link<?= navActive('/cleaning', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/cleaning')) ?>">
                  <i class="nav-icon bi bi-stars"></i>
                  <p><?= Http::e(I18n::t('nav.cleaning')) ?></p>
                </a>
              </li>
            <?php endif; ?>

            <!-- Admin Group -->
            <?php 
              $showAdminGroup = ($auth && (
                $auth->can('manage_users') && $auth->navVisible('users')
              ));
              $adminGroupOpen = navActive('/users', $currentPath);
            ?>
            <?php if ($showAdminGroup): ?>
              <li class="nav-header"><?= Http::e($lang === 'ar' ? 'الإدارة' : 'Administration') ?></li>
              <li class="nav-item">
                <a class="nav-link<?= navActive('/users', $currentPath) ? ' active' : '' ?>" href="<?= Http::e(Http::url('/users')) ?>">
                  <i class="nav-icon bi bi-person-gear"></i>
                  <p><?= Http::e(I18n::t('nav.users')) ?></p>
                </a>
              </li>
            <?php endif; ?>

            <!-- Settings Group -->
            <?php 
              $showSettingsGroup = ($auth && $auth->can('settings') && $auth->navVisible('settings'));
              $settingsGroupOpen = (navActive('/settings', $currentPath));
            ?>
            <?php if ($showSettingsGroup): ?>
              <li class="nav-item<?= $settingsGroupOpen ? ' menu-open' : '' ?>">
                <a href="#" class="nav-link<?= $settingsGroupOpen ? ' active' : '' ?>">
                  <i class="nav-icon bi bi-gear"></i>
                  <p>
                    <?= Http::e(I18n::t('nav.settings')) ?>
                    <i class="nav-arrow bi bi-chevron-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a class="nav-link<?= ($currentPath === '/settings') ? ' active' : '' ?>" href="<?= Http::e(Http::url('/settings')) ?>">
                      <i class="nav-icon bi bi-sliders"></i>
                      <p><?= Http::e($lang === 'ar' ? 'الإعدادات العامة' : 'General Settings') ?></p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link<?= ($currentPath === '/settings/tools') ? ' active' : '' ?>" href="<?= Http::e(Http::url('/settings/tools')) ?>">
                      <i class="nav-icon bi bi-tools"></i>
                      <p><?= Http::e($lang === 'ar' ? 'أدوات الصيانة' : 'Maintenance Tools') ?></p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link<?= ($currentPath === '/settings/audit') ? ' active' : '' ?>" href="<?= Http::e(Http::url('/settings/audit')) ?>">
                      <i class="nav-icon bi bi-clock-history"></i>
                      <p><?= Http::e($lang === 'ar' ? 'سجل العمليات' : 'Audit Log') ?></p>
                    </a>
                  </li>
                </ul>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer mt-auto p-3 border-top border-secondary">
          <div class="d-flex align-items-center gap-2 text-light opacity-75">
            <i class="bi bi-shield-check"></i>
            <small><?= Http::e($lang === 'ar' ? 'نسخة آمنة' : 'Secure Version') ?></small>
          </div>
          <div class="text-muted small mt-1">
            v2.0.0
          </div>
        </div>
      </div>
    </aside>

    <main class="app-main">
      <div class="app-content-header">
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-6">
              <h3 class="mb-0"><?= Http::e((string)$title) ?></h3>
            </div>
            <div class="col-sm-6">
              <?php if (!empty($breadcrumbsComputed)): ?>
                <ol class="breadcrumb float-sm-end">
                  <?php foreach ($breadcrumbsComputed as $i => $bc): ?>
                    <?php
                      $isLast = ($i === (count($breadcrumbsComputed) - 1));
                      $label = (string)($bc['label'] ?? '');
                      $href = $bc['href'] ?? null;
                    ?>
                    <?php if (!$isLast && is_string($href) && $href !== ''): ?>
                      <li class="breadcrumb-item"><a href="<?= Http::e($href) ?>"><?= Http::e($label) ?></a></li>
                    <?php else: ?>
                      <li class="breadcrumb-item active" aria-current="page"><?= Http::e($label) ?></li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="app-content">
        <div class="container-fluid">
          <?= $content ?>
        </div>
      </div>

      <footer class="app-footer">
        <strong>
          &copy; <?= htmlspecialchars((string)$currentYear, ENT_QUOTES, 'UTF-8') ?>
          <?= Http::e(I18n::t('app.name')) ?>
        </strong>
        <span class="ms-1"><?= Http::e(I18n::t('app.rights')) ?></span>
      </footer>
    </main>
  </div>

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

  <!-- 
    ═══════════════════════════════════════════════════════════════
    ZACO Assets Management System
    Developed with ❤ by Mahmoud Fouad
    
    Contact: mahmoud.a.fouad2@gmail.com
    Portfolio: https://ma-fo.info
    Phone: +966 530047640 | +20 1116588189
    
    Copyright © <?= (int)date('Y') ?> Mahmoud Fouad
    All Rights Reserved - Proprietary Software
    ═══════════════════════════════════════════════════════════════
  -->
</body>
</html>
