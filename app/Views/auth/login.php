<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

ob_start();
?>
<div class="login-box">
  <div class="login-logo">
    <a href="<?= Http::e(Http::url('/')) ?>">
      <img src="<?= Http::e(Http::url('/branding/logo')) ?>" alt="<?= Http::e(I18n::t('app.name')) ?>" style="height:48px; width:auto;" />
    </a>
  </div>

  <div class="card">
    <div class="card-body login-card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="login-box-msg mb-0"><?= Http::e(I18n::t('auth.login_sub')) ?></p>
        <a class="btn btn-outline-secondary btn-sm" data-lang-switch href="<?= Http::e(Http::url('/lang?set=' . (I18n::locale() === 'ar' ? 'en' : 'ar') . '&r=' . urlencode('/login'))) ?>">
          <i class="bi bi-translate"></i>
          <span class="ms-1"><?= Http::e(I18n::locale() === 'ar' ? I18n::t('ui.lang_en') : I18n::t('ui.lang_ar')) ?></span>
        </a>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="<?= Http::e(Http::url('/login')) ?>" data-loading>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

        <div class="input-group mb-3">
          <input class="form-control" type="email" name="email" required autocomplete="username" placeholder="<?= Http::e(I18n::t('auth.email')) ?>" />
          <div class="input-group-text"><span class="bi bi-envelope"></span></div>
        </div>

        <div class="input-group mb-3">
          <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="<?= Http::e(I18n::t('auth.password')) ?>" />
          <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit"><?= Http::e(I18n::t('auth.submit')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'تسجيل الدخول';
require __DIR__ . '/../layout_min.php';
