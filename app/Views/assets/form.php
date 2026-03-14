<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
$mode = $mode ?? 'create';

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">أدخل بيانات الأصل بدقة.</div>
  <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/inventory')) ?>">
    <i class="bi bi-arrow-return-right me-1"></i>
    رجوع
  </a>
</div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= Http::e((string)$error) ?></div>
  <?php endif; ?>

  <?php if (!empty($orgTableExists) && empty($orgColumnExists)): ?>
    <div class="alert alert-danger">تم إنشاء منظمات، لكن جدول الأصول لا يحتوي على عمود org_id لذلك اختيار المنظمة لن يعمل هنا. شغّل ملف migration: <strong>scripts/migrate_orgs.mysql.sql</strong></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div class="fw-bold"><?= ($mode === 'edit') ? 'تعديل أصل' : 'إضافة أصل' ?></div>
    </div>
    <div class="card-body">
    <form method="post" enctype="multipart/form-data" action="<?= Http::e(Http::url($mode === 'edit' ? '/inventory/edit' : '/inventory/create')) ?>" class="row g-3" data-loading>
      <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
      <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= (int)($it['id'] ?? 0) ?>" />
      <?php endif; ?>

      <?php if (!empty($orgEnabled)): ?>
        <div class="col-12">
          <label class="form-label">المنظمة *</label>
          <?php $oid = (int)($it['org_id'] ?? 0); ?>
          <select class="form-select" name="org_id" required>
            <option value="">اختر المنظمة</option>
            <?php foreach (($orgs ?? []) as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= ((int)$o['id'] === $oid) ? 'selected' : '' ?>><?= Http::e((string)$o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label">الاسم *</label>
        <input class="form-control" name="name" required value="<?= Http::e((string)($it['name'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">الفئة *</label>
        <?php if (!empty($categoriesEnabled) && !empty($assetCategories)): ?>
          <?php $cid = (int)($it['category_id'] ?? 0); ?>
          <select class="form-select" name="category_id" required>
            <option value="">اختر الفئة</option>
            <?php foreach (($assetCategories ?? []) as $c): ?>
              <option value="<?= (int)($c['id'] ?? 0) ?>" <?= ((int)($c['id'] ?? 0) === $cid) ? 'selected' : '' ?>><?= Http::e((string)($c['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="form-control" name="category" required value="<?= Http::e((string)($it['category'] ?? '')) ?>" placeholder="مثال: أجهزة / سيارات" />
        <?php endif; ?>
      </div>

      <?php if (!empty($sectionsEnabled)): ?>
        <?php
          $sid = (int)($it['section_id'] ?? 0);
          $ssid = (int)($it['subsection_id'] ?? 0);
          $subsJson = json_encode(($subsectionsBySection ?? []), JSON_UNESCAPED_UNICODE);
        ?>
        <div class="col-md-6">
          <label class="form-label">القسم</label>
          <select class="form-select" name="section_id" id="assetSection" data-section-select data-subsection-id="assetSubsection">
            <option value="">بدون</option>
            <?php foreach (($assetSections ?? []) as $s): ?>
              <option value="<?= (int)($s['id'] ?? 0) ?>" <?= ((int)($s['id'] ?? 0) === $sid) ? 'selected' : '' ?>><?= Http::e((string)($s['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">القسم الفرعي</label>
          <select class="form-select" name="subsection_id" id="assetSubsection" data-subsection-select data-subsections-json="<?= Http::e((string)$subsJson) ?>">
            <option value="">بدون</option>
            <?php if ($sid > 0): ?>
              <?php foreach (($subsectionsBySection[(string)$sid] ?? []) as $ss): ?>
                <option value="<?= (int)($ss['id'] ?? 0) ?>" <?= ((int)($ss['id'] ?? 0) === $ssid) ? 'selected' : '' ?>><?= Http::e((string)($ss['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label">الكود *</label>
        <input class="form-control" name="code" required value="<?= Http::e((string)($it['code'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">نوع الأصل</label>
        <select class="form-select" name="asset_type">
          <option value="general" <?= (($it['asset_type'] ?? '') === 'general') ? 'selected' : '' ?>>أصل عام</option>
          <option value="vehicle" <?= (($it['asset_type'] ?? '') === 'vehicle') ? 'selected' : '' ?>>سيارة</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">الكمية</label>
        <input class="form-control" type="number" name="quantity" min="0" value="<?= (int)($it['quantity'] ?? 1) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">الحد الأدنى</label>
        <input class="form-control" type="number" name="min_quantity" min="0" value="<?= (int)($it['min_quantity'] ?? 1) ?>" />
      </div>

      <div class="col-md-6">
        <label class="form-label">التكلفة</label>
        <input class="form-control" type="number" step="0.01" name="cost" value="<?= Http::e((string)($it['cost'] ?? '0')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">الحالة</label>
        <select class="form-select" name="asset_condition">
          <?php $c = (string)($it['asset_condition'] ?? 'good'); ?>
          <option value="excellent" <?= $c==='excellent'?'selected':'' ?>>ممتاز</option>
          <option value="good" <?= $c==='good'?'selected':'' ?>>جيد</option>
          <option value="fair" <?= $c==='fair'?'selected':'' ?>>مقبول</option>
          <option value="poor" <?= $c==='poor'?'selected':'' ?>>سيئ</option>
          <option value="disposed" <?= $c==='disposed'?'selected':'' ?>>مستبعد</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">تاريخ الشراء</label>
        <input class="form-control" type="date" name="purchase_date" value="<?= Http::e((string)($it['purchase_date'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">المورد</label>
        <input class="form-control" name="supplier" value="<?= Http::e((string)($it['supplier'] ?? '')) ?>" />
      </div>

      <div class="col-md-6">
        <label class="form-label">الموقع</label>
        <input class="form-control" name="location" value="<?= Http::e((string)($it['location'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">صورة الأصل</label>
        <input class="form-control" type="file" name="image" accept="image/*" />
        <?php if (!empty($it['image_path'])): ?>
          <div class="form-text">يوجد صورة حالية.</div>
        <?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label">لوحة السيارة</label>
        <input class="form-control" name="plate_number" value="<?= Http::e((string)($it['plate_number'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">موديل السيارة</label>
        <input class="form-control" name="vehicle_model" value="<?= Http::e((string)($it['vehicle_model'] ?? '')) ?>" />
      </div>

      <div class="col-md-6">
        <label class="form-label">سنة الصنع</label>
        <input class="form-control" type="number" name="vehicle_year" value="<?= Http::e((string)($it['vehicle_year'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">عداد (KM)</label>
        <input class="form-control" type="number" name="mileage" value="<?= Http::e((string)($it['mileage'] ?? '')) ?>" />
      </div>

      <div class="col-12">
        <label class="form-label">ملاحظات</label>
        <textarea class="form-control" name="notes" rows="4"><?= Http::e((string)($it['notes'] ?? '')) ?></textarea>
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
$title = ($mode === 'edit') ? 'تعديل أصل' : 'إضافة أصل';
require __DIR__ . '/../shell.php';
