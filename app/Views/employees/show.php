<?php
declare(strict_types=1);

use Zaco\Core\Http;

$it = $item ?? [];
ob_start();
?>
<?php
$empStatusLabel = static function (string $raw): string {
  return match ($raw) {
    'active' => 'نشط',
    'suspended' => 'موقوف',
    'resigned' => 'مستقيل',
    'terminated' => 'منتهي',
    default => $raw,
  };
};

$empStatusBadge = static function (string $raw): string {
  return match ($raw) {
    'active' => 'text-bg-success',
    'suspended' => 'text-bg-warning',
    'resigned' => 'text-bg-secondary',
    'terminated' => 'text-bg-danger',
    default => 'text-bg-light border',
  };
};
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <div class="h4 mb-0"><?= Http::e((string)($it['full_name'] ?? '')) ?></div>
    <div class="text-muted small"><?= Http::e((string)($it['employee_no'] ?? '')) ?> • <?= Http::e((string)($it['department'] ?? '')) ?> • <?= Http::e((string)($it['job_title'] ?? '')) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/employees')) ?>">
      <i class="bi bi-arrow-return-right me-1"></i>
      رجوع
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/employees/edit?id=' . (int)($it['id'] ?? 0))) ?>">
        <i class="bi bi-pencil-square me-1"></i>
        تعديل
      </a>
    <?php endif; ?>
  </div>
</div>

  <?php $msg = $_GET['msg'] ?? null; ?>
  <?php if ($msg === 'note_added'): ?><div class="alert alert-success" data-toast>تم إضافة سجل سلوك/ملاحظة.</div><?php endif; ?>
  <?php if ($msg === 'report_added'): ?><div class="alert alert-success" data-toast>تم إضافة تقرير للموظف.</div><?php endif; ?>
  <?php if ($msg === 'award_added'): ?><div class="alert alert-success" data-toast>تم إنشاء شهادة تقدير.</div><?php endif; ?>
  <?php if (in_array($msg, ['note_err','report_err','award_err'], true)): ?><div class="alert alert-danger">تأكد من إدخال البيانات المطلوبة.</div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <div class="fw-semibold">بيانات الموظف</div>
      </div>
      <div class="card-body">
        <div class="d-flex gap-3 align-items-start mb-3">
          <?php if (!empty($it['photo'])): ?>
            <img class="zaco-avatar-xl rounded border" alt="" src="<?= Http::e(Http::url('/employees/photo?id=' . (int)($it['id'] ?? 0))) ?>" loading="lazy" />
          <?php else: ?>
            <span class="zaco-avatar-xl rounded bg-body-tertiary border d-inline-block"></span>
          <?php endif; ?>

          <div class="flex-grow-1">
            <dl class="row mb-0">
              <dt class="col-sm-4 text-muted">الهاتف</dt><dd class="col-sm-8"><?= Http::e((string)($it['phone'] ?? '')) ?></dd>
              <dt class="col-sm-4 text-muted">البريد</dt><dd class="col-sm-8"><?= Http::e((string)($it['email'] ?? '')) ?></dd>
              <?php $stRaw = (string)($it['emp_status'] ?? ''); ?>
              <dt class="col-sm-4 text-muted">الحالة</dt>
              <dd class="col-sm-8">
                <span class="badge <?= Http::e($empStatusBadge($stRaw)) ?>"><?= Http::e($empStatusLabel($stRaw)) ?></span>
              </dd>
              <dt class="col-sm-4 text-muted">تاريخ التوظيف</dt><dd class="col-sm-8"><?= Http::e((string)($it['hire_date'] ?? '')) ?></dd>
              <?php if (isset($it['device_password'])): ?>
                <dt class="col-sm-4 text-muted">باسورد الجهاز</dt><dd class="col-sm-8"><?= Http::e((string)($it['device_password'] ?? '')) ?></dd>
              <?php endif; ?>
              <dt class="col-sm-4 text-muted">ملاحظات عامة</dt><dd class="col-sm-8"><?= Http::e((string)($it['notes'] ?? '')) ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <div class="fw-semibold">إجراءات سريعة</div>
      </div>
      <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/employees/export')) ?>">
            <i class="bi bi-file-earmark-excel me-1"></i>
            تصدير Excel
          </a>
          <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/custody?employee_id=' . (int)($it['id'] ?? 0))) ?>">
            <i class="bi bi-clipboard-check me-1"></i>
            العُهد (إن وجدت)
          </a>
        </div>
        <div class="text-muted small mt-2">يمكنك إضافة سجل سلوك وتقارير دورية وشهادات تقدير من الأقسام التالية.</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><div class="fw-semibold">سجل السلوك / الملاحظات</div></div>
      <div class="card-body">
        <?php if (!empty($canEdit)): ?>
          <form method="post" action="<?= Http::e(Http::url('/employees/note')) ?>" data-loading>
            <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
            <input type="hidden" name="employee_id" value="<?= (int)($it['id'] ?? 0) ?>" />
            <div class="row g-2">
              <div class="col-md-5">
                <label class="form-label">التاريخ (اختياري)</label>
                <input class="form-control" type="date" name="note_date" />
              </div>
              <div class="col-md-7">
                <label class="form-label">ملاحظة / سلوك</label>
                <input class="form-control" name="note_text" placeholder="اكتب ملاحظة مختصرة..." required />
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-plus-circle me-1"></i>
                  إضافة للسجل
                </button>
              </div>
            </div>
          </form>
                      <?php if (!empty($it['photo'])): ?>
                        <div class="mb-3">
                          <img class="zaco-avatar-xl rounded border" alt="" src="<?= Http::e(Http::url('/employees/photo?id=' . (int)($it['id'] ?? 0))) ?>" loading="lazy" />
                        </div>
                      <?php endif; ?>
          <hr />
        <?php endif; ?>

        <?php foreach (($notes ?? []) as $n): ?>
          <div class="border rounded p-2 mb-2">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <div class="fw-semibold"><?= Http::e((string)($n['created_by_name'] ?? '')) ?></div>
              <div class="text-muted small"><?= Http::e((string)($n['note_date'] ?? $n['created_at'] ?? '')) ?></div>
            </div>
            <div class="mt-2"><?= nl2br(Http::e((string)($n['note_text'] ?? ''))) ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($notes)): ?>
          <div class="text-muted">لا يوجد سجلات بعد.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><div class="fw-semibold">التقارير الدورية</div></div>
      <div class="card-body">
        <?php if (!empty($canEdit)): ?>
          <form method="post" action="<?= Http::e(Http::url('/employees/report')) ?>" data-loading>
            <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
            <input type="hidden" name="employee_id" value="<?= (int)($it['id'] ?? 0) ?>" />

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">من</label>
                <input class="form-control" type="date" name="period_from" />
              </div>
              <div class="col-md-6">
                <label class="form-label">إلى</label>
                <input class="form-control" type="date" name="period_to" />
              </div>
              <div class="col-12">
                <label class="form-label">عنوان التقرير</label>
                <input class="form-control" name="title" required placeholder="مثال: تقييم أداء شهر مارس" />
              </div>
              <div class="col-12">
                <label class="form-label">نص التقرير</label>
                <textarea class="form-control" name="report_text" rows="5" required placeholder="اكتب تفاصيل التقرير..." style="resize:vertical"></textarea>
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-check2-circle me-1"></i>
                  حفظ التقرير
                </button>
              </div>
            </div>
          </form>
          <hr />
        <?php endif; ?>

        <?php foreach (($reports ?? []) as $r): ?>
          <div class="border rounded p-2 mb-2">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <div class="fw-semibold"><?= Http::e((string)($r['title'] ?? '')) ?></div>
              <div class="text-muted small"><?= Http::e((string)($r['period_from'] ?? '')) ?> <?= !empty($r['period_to']) ? ('→ ' . Http::e((string)$r['period_to'])) : '' ?></div>
            </div>
            <div class="text-muted small mt-1">بواسطة: <?= Http::e((string)($r['created_by_name'] ?? '')) ?> • <?= Http::e((string)($r['created_at'] ?? '')) ?></div>
            <div class="mt-2"><?= nl2br(Http::e((string)($r['report_text'] ?? ''))) ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($reports)): ?>
          <div class="text-muted">لا توجد تقارير بعد.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><div class="fw-semibold">شهادات التقدير</div></div>
  <div class="card-body">
    <?php if (!empty($canEdit)): ?>
      <form method="post" action="<?= Http::e(Http::url('/employees/award')) ?>" data-loading>
        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
        <input type="hidden" name="employee_id" value="<?= (int)($it['id'] ?? 0) ?>" />
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">تاريخ الإصدار (اختياري)</label>
            <input class="form-control" type="date" name="issue_date" />
          </div>
          <div class="col-md-8">
            <label class="form-label">عنوان الشهادة</label>
            <input class="form-control" name="award_title" required placeholder="مثال: شهادة تقدير" />
          </div>
          <div class="col-12">
            <label class="form-label">نص الشهادة</label>
            <textarea class="form-control" name="award_text" rows="4" required placeholder="مثال: تقديرًا لجهوده المتميزة والتزامه..." style="resize:vertical"></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-award me-1"></i>
              إنشاء الشهادة
            </button>
          </div>
        </div>
      </form>
      <hr />
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>العنوان</th>
            <th>التاريخ</th>
            <th>بواسطة</th>
            <th>طباعة</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($awards ?? []) as $a): ?>
            <tr>
              <td><?= Http::e((string)($a['award_title'] ?? '')) ?></td>
              <td><?= Http::e((string)($a['issue_date'] ?? $a['created_at'] ?? '')) ?></td>
              <td><?= Http::e((string)($a['created_by_name'] ?? '')) ?></td>
              <td>
                <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/employees/award/print?id=' . (int)($a['id'] ?? 0))) ?>" target="_blank" rel="noopener">
                  <i class="bi bi-printer me-1"></i>
                  طباعة
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (empty($awards)): ?>
      <div class="text-muted mt-2">لا توجد شهادات بعد.</div>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'الموظف';
require __DIR__ . '/../shell.php';
