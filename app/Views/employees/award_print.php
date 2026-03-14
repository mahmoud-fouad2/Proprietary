<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

$a = $award ?? [];

ob_start();
?>
<div class="cert">
  <div class="cert__head">
    <img class="cert__logo" src="<?= Http::e(Http::url('/branding/logo')) ?>" alt="<?= Http::e(I18n::t('app.name')) ?>" />
    <div class="cert__brand">
      <div class="cert__name"><?= Http::e(I18n::t('app.name')) ?></div>
      <div class="cert__sub"><?= Http::e(I18n::t('app.subtitle')) ?></div>
    </div>
  </div>

  <div class="cert__title"><?= Http::e((string)($a['award_title'] ?? 'شهادة تقدير')) ?></div>
  <div class="cert__to">تُمنح إلى</div>
  <div class="cert__person"><?= Http::e((string)($a['full_name'] ?? '')) ?></div>
  <div class="cert__meta">رقم الموظف: <?= Http::e((string)($a['employee_no'] ?? '')) ?></div>

  <div class="cert__body">
    <?= nl2br(Http::e((string)($a['award_text'] ?? ''))) ?>
  </div>

  <div class="cert__footer">
    <div>
      <div class="muted">تاريخ الإصدار</div>
      <div style="font-weight:900"><?= Http::e((string)($a['issue_date'] ?? '')) ?></div>
    </div>
    <div style="text-align:center">
      <div class="muted">التوقيع</div>
      <div style="height:34px"></div>
      <div style="font-weight:900">الإدارة</div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'شهادة تقدير';
require __DIR__ . '/../layout_print.php';
