<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

$report = $report ?? [];
$checks = $checks ?? [];

ob_start();
?>
<div class="printdoc">
  <div class="printdoc__head">
    <div class="printdoc__brand">
      <img class="printdoc__logo" src="<?= Http::e(Http::url('/branding/logo')) ?>" alt="<?= Http::e(I18n::t('app.name')) ?>" />
      <div>
        <div class="printdoc__name"><?= Http::e(I18n::t('app.name')) ?></div>
        <div class="printdoc__sub"><?= Http::e(I18n::t('app.subtitle')) ?></div>
      </div>
    </div>
    <div class="printdoc__meta">
      <div class="printdoc__title">تقرير النظافة اليومي</div>
      <div class="muted">التاريخ: <?= Http::e((string)($report['report_date'] ?? '')) ?></div>
      <div class="muted">الموظف: <?= Http::e((string)($report['cleaner_name'] ?? '')) ?></div>
      <div class="muted">وقت الإرسال: <?= Http::e((string)($report['submitted_at'] ?? '')) ?></div>
    </div>
  </div>

  <div class="card" style="margin-top:14px">
    <div class="card__title">تعليق الموظف</div>
    <?php if (!empty($report['comment'])): ?>
      <div class="note">
        <?= nl2br(Http::e((string)$report['comment'])) ?>
      </div>
    <?php else: ?>
      <div class="muted">لا يوجد تعليق.</div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:14px">
    <div class="card__title">الصور</div>
    <div class="photogrid">
      <?php foreach ($checks as $c): ?>
        <div class="photogrid__item">
          <div class="photogrid__top">
            <div style="font-weight:900"><?= Http::e((string)($c['place_name'] ?? '')) ?></div>
            <div class="muted"><?= Http::e((string)($c['checked_at'] ?? '')) ?></div>
          </div>
          <img class="photogrid__img" src="<?= Http::e(Http::url('/cleaning/photo?check_id=' . (int)($c['id'] ?? 0))) ?>" alt="<?= Http::e((string)($c['place_name'] ?? '')) ?>" />
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($checks)): ?>
      <div class="muted">لا توجد صور مسجلة لهذا الموظف في نفس التاريخ.</div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'تقرير النظافة';
require __DIR__ . '/../layout_print.php';
