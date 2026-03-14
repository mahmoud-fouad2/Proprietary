<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>
<div class="login-box">
  <div class="login-logo">
    <a href="<?= Http::e(Http::url('/')) ?>">
      <img src="<?= Http::e(Http::url('/branding/logo')) ?>" alt="Logo" style="height:42px; width:auto; vertical-align:middle;" />
      <span class="ms-2"><b>ZACO</b></span>
    </a>
  </div>

  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">لا يوجد مستخدمون بعد — أنشئ حساب الأدمن</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="<?= Http::e(Http::url('/setup')) ?>" data-loading>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

        <div class="input-group mb-3">
          <input class="form-control" type="text" name="name" required placeholder="اسم الأدمن" />
          <div class="input-group-text"><span class="bi bi-person"></span></div>
        </div>

        <div class="input-group mb-3">
          <input class="form-control" type="email" name="email" required autocomplete="username" placeholder="البريد الإلكتروني" />
          <div class="input-group-text"><span class="bi bi-envelope"></span></div>
        </div>

        <div class="input-group mb-3">
          <input class="form-control" type="password" name="password" required autocomplete="new-password" placeholder="كلمة المرور" />
          <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit">إنشاء الأدمن</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'إعداد أول أدمن';
require __DIR__ . '/../layout_min.php';
