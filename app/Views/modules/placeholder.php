<?php
declare(strict_types=1);

ob_start();
?>
<div>
  <div class="mb-3">
    <h1 class="h3 mb-1"><?= htmlspecialchars((string)($title ?? '—'), ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="text-muted"><?= htmlspecialchars((string)($subtitle ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="fw-bold mb-2">قيد التنفيذ</div>
      <div class="text-muted">سيتم استكمال هذا القسم بالكامل (قائمة/جدول + بحث + فلاتر + إضافة/تعديل/حذف + تصدير) ضمن المرحلة 2.</div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = (string)($title ?? '');
require __DIR__ . '/../shell.php';
