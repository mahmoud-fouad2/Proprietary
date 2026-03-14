<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
$mode = $mode ?? 'create';

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">الحقول الأساسية: الاسم + الرقم الوظيفي.</div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/employees')) ?>">
      <i class="bi bi-arrow-return-right me-1"></i>
      رجوع
    </a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= Http::e((string)$error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= Http::e(Http::url($mode === 'edit' ? '/employees/edit' : '/employees/create')) ?>" data-loading enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
      <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= (int)($it['id'] ?? 0) ?>" />
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">الاسم الكامل *</label>
          <input class="form-control" name="full_name" required value="<?= Http::e((string)($it['full_name'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">الرقم الوظيفي *</label>
          <input class="form-control" name="employee_no" required value="<?= Http::e((string)($it['employee_no'] ?? '')) ?>" />
        </div>

        <?php if (!empty($orgEnabled)): ?>
          <div class="col-md-6">
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
          <label class="form-label">القسم</label>
          <input class="form-control" name="department" value="<?= Http::e((string)($it['department'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">المسمى الوظيفي</label>
          <input class="form-control" name="job_title" value="<?= Http::e((string)($it['job_title'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">تاريخ التوظيف</label>
          <input class="form-control" type="date" name="hire_date" value="<?= Http::e((string)($it['hire_date'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">نوع العقد</label>
          <?php $ct = (string)($it['contract_type'] ?? 'permanent'); ?>
          <select class="form-select" name="contract_type">
            <option value="permanent" <?= $ct==='permanent'?'selected':'' ?>>دائم</option>
            <option value="temporary" <?= $ct==='temporary'?'selected':'' ?>>مؤقت</option>
            <option value="parttime" <?= $ct==='parttime'?'selected':'' ?>>جزئي</option>
            <option value="freelance" <?= $ct==='freelance'?'selected':'' ?>>Freelance</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">الراتب</label>
          <input class="form-control" type="number" step="0.01" name="salary" value="<?= Http::e((string)($it['salary'] ?? '0')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">البدلات</label>
          <input class="form-control" type="number" step="0.01" name="allowances" value="<?= Http::e((string)($it['allowances'] ?? '0')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">الاستقطاعات</label>
          <input class="form-control" type="number" step="0.01" name="deductions" value="<?= Http::e((string)($it['deductions'] ?? '0')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">الحالة</label>
          <?php $st = (string)($it['emp_status'] ?? 'active'); ?>
          <select class="form-select" name="emp_status">
            <option value="active" <?= $st==='active'?'selected':'' ?>>نشط</option>
            <option value="suspended" <?= $st==='suspended'?'selected':'' ?>>موقوف</option>
            <option value="resigned" <?= $st==='resigned'?'selected':'' ?>>مستقيل</option>
            <option value="terminated" <?= $st==='terminated'?'selected':'' ?>>منتهي</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">الهاتف</label>
          <input class="form-control" name="phone" value="<?= Http::e((string)($it['phone'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">البريد</label>
          <input class="form-control" type="email" name="email" value="<?= Http::e((string)($it['email'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">رقم الهوية</label>
          <input class="form-control" name="national_id" value="<?= Http::e((string)($it['national_id'] ?? '')) ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">كلمة مرور الجهاز (Device Password)</label>
          <input class="form-control" name="device_password" value="<?= Http::e((string)($it['device_password'] ?? '')) ?>" />
        </div>

        <div class="col-md-6">
          <label class="form-label">صورة الموظف (Photo)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" />
          <?php if ($mode === 'edit' && !empty($it['photo'])): ?>
            <div class="form-text mb-2">الصورة الحالية:</div>
            <img class="zaco-avatar-lg rounded border" alt="" src="<?= Http::e(Http::url('/employees/photo?id=' . (int)($it['id'] ?? 0))) ?>" loading="lazy" />
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">ملاحظات</label>
          <textarea class="form-control" name="notes" rows="3" style="resize:vertical"><?= Http::e((string)($it['notes'] ?? '')) ?></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-check2-circle me-1"></i>
            حفظ
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = ($mode === 'edit') ? 'تعديل موظف' : 'إضافة موظف';
require __DIR__ . '/../shell.php';
