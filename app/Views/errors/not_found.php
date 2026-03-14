<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>
<div class="card">
  <div class="card-body">
    <div class="alert alert-warning mb-3">
      <div class="fw-bold">الصفحة غير موجودة</div>
      <div class="small">تعذر العثور على الصفحة المطلوبة.</div>
    </div>
    <a class="btn btn-primary" href="<?= Http::e(Http::url('/')) ?>">
      <i class="bi bi-house-door me-1"></i>
      العودة للوحة التحكم
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = '404';
require __DIR__ . '/../shell.php';
