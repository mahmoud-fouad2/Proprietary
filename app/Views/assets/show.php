<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
ob_start();
?>
<?php
$condLabel = static function (string $raw): string {
  return match ($raw) {
    'excellent' => 'ممتاز',
    'good' => 'جيد',
    'fair' => 'مقبول',
    'poor' => 'سيئ',
    'disposed' => 'مستبعد',
    default => $raw,
  };
};

$condBadge = static function (string $raw): string {
  return match ($raw) {
    'excellent' => 'text-bg-success',
    'good' => 'text-bg-primary',
    'fair' => 'text-bg-warning',
    'poor' => 'text-bg-danger',
    'disposed' => 'text-bg-secondary',
    default => 'text-bg-light border',
  };
};
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <div class="h5 mb-1"><?= Http::e((string)($it['name'] ?? '')) ?></div>
    <div class="text-muted">
      <?= Http::e((string)($it['code'] ?? '')) ?> • <?= Http::e((string)($it['category'] ?? '')) ?>
      <?php if (!empty($it['section_name'])): ?>
        • <?= Http::e((string)($it['section_name'] ?? '')) ?><?= !empty($it['subsection_name']) ? (' / ' . Http::e((string)($it['subsection_name'] ?? ''))) : '' ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/inventory')) ?>">
      <i class="bi bi-arrow-return-right me-1"></i>
      رجوع
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/inventory/edit?id=' . (int)($it['id'] ?? 0))) ?>">
        <i class="bi bi-pencil-square me-1"></i>
        تعديل
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><div class="fw-bold">الصورة</div></div>
      <div class="card-body">
        <?php if (!empty($it['image_path'])): ?>
          <img class="img-fluid rounded" src="<?= Http::e(Http::url('/inventory/image?id=' . (int)($it['id'] ?? 0))) ?>" alt="<?= Http::e((string)($it['name'] ?? '')) ?>" />
        <?php else: ?>
          <div class="text-muted">لا توجد صورة مرفوعة لهذا الأصل.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><div class="fw-bold">بيانات الأصل</div></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">الاسم</dt><dd class="col-sm-8"><?= Http::e((string)($it['name'] ?? '')) ?></dd>
          <dt class="col-sm-4 text-muted">الفئة</dt><dd class="col-sm-8"><?= Http::e((string)($it['category'] ?? '')) ?></dd>
          <?php if (!empty($it['section_name'])): ?>
            <dt class="col-sm-4 text-muted">القسم</dt><dd class="col-sm-8"><?= Http::e((string)($it['section_name'] ?? '')) ?></dd>
            <dt class="col-sm-4 text-muted">القسم الفرعي</dt><dd class="col-sm-8"><?= Http::e((string)($it['subsection_name'] ?? '')) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4 text-muted">الكود</dt><dd class="col-sm-8"><?= Http::e((string)($it['code'] ?? '')) ?></dd>
          <dt class="col-sm-4 text-muted">الكمية</dt><dd class="col-sm-8"><?= (int)($it['quantity'] ?? 0) ?></dd>
          <dt class="col-sm-4 text-muted">أقل كمية</dt><dd class="col-sm-8"><?= (int)($it['min_quantity'] ?? 0) ?></dd>
          <dt class="col-sm-4 text-muted">التكلفة</dt><dd class="col-sm-8"><?= Http::e((string)($it['cost'] ?? '0')) ?></dd>
          <?php $stRaw = (string)($it['asset_condition'] ?? ''); ?>
          <dt class="col-sm-4 text-muted">الحالة</dt>
          <dd class="col-sm-8">
            <span class="badge <?= Http::e($condBadge($stRaw)) ?>"><?= Http::e($condLabel($stRaw)) ?></span>
          </dd>
          <dt class="col-sm-4 text-muted">الموقع</dt><dd class="col-sm-8"><?= Http::e((string)($it['location'] ?? '')) ?></dd>
          <dt class="col-sm-4 text-muted">تاريخ الشراء</dt><dd class="col-sm-8"><?= Http::e((string)($it['purchase_date'] ?? '')) ?></dd>
          <dt class="col-sm-4 text-muted">المورّد</dt><dd class="col-sm-8"><?= Http::e((string)($it['supplier'] ?? '')) ?></dd>
          <dt class="col-sm-4 text-muted">ملاحظات</dt><dd class="col-sm-8"><?= Http::e((string)($it['notes'] ?? '')) ?></dd>
        </dl>
      </div>

      <?php if (($it['asset_type'] ?? '') === 'vehicle'): ?>
        <div class="card-header border-top"><div class="fw-bold">بيانات المركبة</div></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted">لوحة</dt><dd class="col-sm-8"><?= Http::e((string)($it['plate_number'] ?? '')) ?></dd>
            <dt class="col-sm-4 text-muted">الموديل</dt><dd class="col-sm-8"><?= Http::e((string)($it['vehicle_model'] ?? '')) ?></dd>
            <dt class="col-sm-4 text-muted">السنة</dt><dd class="col-sm-8"><?= Http::e((string)($it['vehicle_year'] ?? '')) ?></dd>
            <dt class="col-sm-4 text-muted">الممشى</dt><dd class="col-sm-8"><?= Http::e((string)($it['mileage'] ?? '')) ?></dd>
          </dl>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$title = (string)($it['name'] ?? 'الأصل');
require __DIR__ . '/../shell.php';
