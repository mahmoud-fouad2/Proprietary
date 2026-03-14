<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
$mode = $mode ?? 'create';
$employees = $employees ?? [];

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">الحقول المطلوبة: الموظف + اسم العهدة + تاريخ التسليم.</div>
  <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/custody')) ?>">
    <i class="bi bi-arrow-return-right me-1"></i>
    رجوع
  </a>
</div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= Http::e((string)$error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div class="fw-bold"><?= ($mode === 'edit') ? 'تعديل عهدة' : 'إضافة عهدة' ?></div>
    </div>
    <div class="card-body">
    <form method="post" enctype="multipart/form-data" action="<?= Http::e(Http::url($mode === 'edit' ? '/custody/edit' : '/custody/create')) ?>" class="row g-3" data-loading>
      <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
      <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= (int)($it['id'] ?? 0) ?>" />
      <?php endif; ?>

      <?php if (!empty($orgEnabled)): ?>
        <div class="col-12">
          <label class="form-label">المنظمة</label>
          <?php $oid = (int)($it['org_id'] ?? 0); ?>
          <select class="form-select" name="org_id">
            <option value="">اختر المنظمة</option>
            <?php foreach (($orgs ?? []) as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= ((int)$o['id'] === $oid) ? 'selected' : '' ?>><?= Http::e((string)$o['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($orgs)): ?>
            <div class="form-text text-danger">لا توجد منظمات مُفعّلة. أضِف/فعّل منظمة من <a href="<?= Http::e(Http::url('/settings')) ?>">الإعدادات</a>.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label">الموظف *</label>
        <?php $eid = (int)($it['employee_id'] ?? 0); ?>
        <select class="form-select" name="employee_id" required>
          <option value="">اختر الموظف</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= ((int)$e['id'] === $eid) ? 'selected' : '' ?>><?= Http::e((string)$e['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">اسم العهدة *</label>
        <input class="form-control" name="item_name" required value="<?= Http::e((string)($it['item_name'] ?? '')) ?>" placeholder="مثال: لابتوب / هاتف / مفتاح" />
      </div>

      <div class="col-md-6">
        <label class="form-label">السيريال</label>
        <input class="form-control" name="serial_number" value="<?= Http::e((string)($it['serial_number'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">مرفقات العهدة (اختياري)</label>
        <input class="form-control" type="file" name="attachment" accept="application/pdf,image/*" />
        <div class="form-text">ارفق صورة أو PDF للورقة الموقّعة (حتى 12MB).</div>
        <?php if (!empty($it['attachment_path']) && !empty($it['id'])): ?>
          <div class="form-text">
            يوجد مرفق محفوظ — <a href="<?= Http::e(Http::url('/custody/attachment?id=' . (int)$it['id'])) ?>">تحميل</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <label class="form-label">الوصف</label>
        <textarea class="form-control" name="description" rows="3"><?= Http::e((string)($it['description'] ?? '')) ?></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">تاريخ التسليم *</label>
        <input class="form-control" type="date" name="date_assigned" required value="<?= Http::e((string)($it['date_assigned'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">الحالة</label>
        <?php $st = (string)($it['custody_status'] ?? 'active'); ?>
        <select class="form-select" name="custody_status">
          <option value="active" <?= $st==='active'?'selected':'' ?>>فعالة</option>
          <option value="returned" <?= $st==='returned'?'selected':'' ?>>مُسترجعة</option>
          <option value="damaged" <?= $st==='damaged'?'selected':'' ?>>تالفة</option>
          <option value="lost" <?= $st==='lost'?'selected':'' ?>>مفقودة</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">تاريخ الإرجاع (عند الاسترجاع)</label>
        <input class="form-control" type="date" name="date_returned" value="<?= Http::e((string)($it['date_returned'] ?? '')) ?>" />
      </div>
      <div class="col-md-6">
        <label class="form-label">ملاحظات</label>
        <input class="form-control" name="notes" value="<?= Http::e((string)($it['notes'] ?? '')) ?>" />
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
$title = ($mode === 'edit') ? 'تعديل عهدة' : 'إضافة عهدة';
require __DIR__ . '/../shell.php';
