<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>
<div>
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1">تقارير النظافة (للإدارة)</h1>
      <div class="text-muted">تقارير يومية مرسلة من موظفي النظافة — قابلة للطباعة.</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/cleaning')) ?>">رجوع</a>
      <button class="btn btn-outline-secondary" type="button" data-print>طباعة PDF</button>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= Http::e(Http::url('/cleaning/reports')) ?>" class="row g-2 align-items-end" data-loading>
        <div class="col-12 col-md-4">
          <label class="form-label" for="date">التاريخ</label>
          <input class="form-control" id="date" type="date" name="date" value="<?= Http::e((string)($date ?? '')) ?>" />
        </div>
        <div class="col-12 col-md-auto">
          <button class="btn btn-primary" type="submit">عرض</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($reports)): ?>
    <div class="row row-cols-1 row-cols-md-3 g-3">
      <?php foreach (($reports ?? []) as $r): ?>
        <?php $uid = (int)($r['cleaner_user_id'] ?? 0); ?>
        <div class="col">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div style="min-width:0">
                  <div class="fw-bold text-truncate"><?= Http::e((string)($r['cleaner_name'] ?? '')) ?></div>
                  <div class="text-muted small text-truncate"><?= Http::e((string)($r['cleaner_email'] ?? '')) ?></div>
                </div>
                <span class="badge text-bg-secondary"><?= (int)($photoCounts[$uid] ?? 0) ?> صور</span>
              </div>

              <div class="text-muted small mt-3">التاريخ: <?= Http::e((string)($r['report_date'] ?? '')) ?></div>
              <div class="text-muted small">وقت الإرسال: <?= Http::e((string)($r['submitted_at'] ?? '')) ?></div>

              <?php if (!empty($r['comment'])): ?>
                <div class="border rounded p-2 bg-body-tertiary mt-3" style="white-space:pre-wrap"><?= Http::e((string)$r['comment']) ?></div>
              <?php else: ?>
                <div class="text-muted small mt-3">بدون تعليق.</div>
              <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
              <a class="btn btn-primary w-100" href="<?= Http::e(Http::url('/cleaning/reports/print?id=' . (int)($r['id'] ?? 0))) ?>">فتح التقرير</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info">لا توجد تقارير مرسلة في هذا التاريخ.</div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title = 'تقارير النظافة';
require __DIR__ . '/../shell.php';
