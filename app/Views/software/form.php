<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
$mode = $mode ?? 'create';

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <div class="text-muted">يمكن رفع ملف أو وضع رابط تحميل خارجي.</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/software')) ?>">
    <i class="bi bi-arrow-return-right me-1"></i>
    رجوع
  </a>
</div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= Http::e((string)$error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div class="fw-bold"><?= ($mode === 'edit') ? 'تعديل برنامج' : 'إضافة برنامج' ?></div>
    </div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= Http::e(Http::url($mode === 'edit' ? '/software/edit' : '/software/create')) ?>" class="row g-3" data-loading>
        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
        <?php if ($mode === 'edit'): ?>
          <input type="hidden" name="id" value="<?= (int)($it['id'] ?? 0) ?>" />
        <?php endif; ?>

        <div class="col-md-6">
          <label class="form-label">اسم البرنامج *</label>
          <input class="form-control" name="name" required value="<?= Http::e((string)($it['name'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">الفئة</label>
          <input class="form-control" name="category" value="<?= Http::e((string)($it['category'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">الإصدار</label>
          <input class="form-control" name="version" value="<?= Http::e((string)($it['version'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label d-block">مجاني؟</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_free" name="is_free" <?= ((int)($it['is_free'] ?? 1) === 1) ? 'checked' : '' ?> />
            <label class="form-check-label" for="is_free">نعم/لا</label>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">الوصف</label>
          <textarea class="form-control" name="description" rows="3"><?= Http::e((string)($it['description'] ?? '')) ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">رفع ملف (اختياري)</label>
          <input class="form-control" type="file" name="file" />
          <?php if (!empty($it['file_path'])): ?>
            <div class="form-text">يوجد ملف محفوظ.</div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">رابط تحميل (اختياري)</label>
          <input class="form-control" name="download_url" value="<?= Http::e((string)($it['download_url'] ?? '')) ?>" placeholder="https://..." />
        </div>

        <div class="col-12">
          <label class="form-label">مفتاح الترخيص (اختياري)</label>
          <textarea class="form-control" name="license_key" rows="2"><?= Http::e((string)($it['license_key'] ?? '')) ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-check2 me-1"></i>
            حفظ
          </button>
        </div>
      </form>
    </div>
  </div>

<?php
$content = ob_get_clean();
$title = ($mode === 'edit') ? 'تعديل برنامج' : 'إضافة برنامج';
require __DIR__ . '/../shell.php';
