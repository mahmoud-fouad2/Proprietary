<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>
<div class="card">
  <div class="card-body">
    <div class="alert alert-danger mb-3">
      <div class="fw-bold">غير مصرح</div>
      <div class="small">هذه الصفحة تتطلب صلاحيات أعلى.</div>
    </div>
    <a class="btn btn-primary" href="<?= Http::e(Http::url('/')) ?>">
      <i class="bi bi-house-door me-1"></i>
      العودة للوحة التحكم
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = '403';
require __DIR__ . '/../shell.php';
