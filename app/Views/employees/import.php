<?php
declare(strict_types=1);

use Zaco\Core\Http;

$rawData = $data ?? '';
$data = (is_string($rawData) || is_numeric($rawData)) ? (string)$rawData : '';

$rawContacts = $contacts_html ?? '';
$contactsHtml = (is_string($rawContacts) || is_numeric($rawContacts)) ? (string)$rawContacts : '';

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">استيراد جماعي مع منع التكرار (بالاسم + المنظمة أو البريد إن وُجد).</div>
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
    <form method="post" action="<?= Http::e(Http::url('/employees/import')) ?>" data-loading>
      <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />

      <div class="mb-3">
        <label class="form-label">بيانات الموظفين (الصق من Excel/Sheets)</label>
        <textarea class="form-control" name="data" rows="10" style="resize:vertical" placeholder="يدعم 3 صيغ:\n1) بوجود Header: org,name,job_title,department,email,phone,employee_no\n2) بدون Header (لقطة Excel): title_en\ttitle_ar\torg\tname_en\tname_ar\tindex\n3) بدون Header (مبسطة): org\tfull_name\tjob_title\tdepartment\temail\tphone\temployee_no"><?= Http::e($data) ?></textarea>
        <div class="form-text">لو ما عندك رقم وظيفي، النظام يولّد رقم تلقائيًا.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">(اختياري) HTML من صفحة إدارة المستخدمين لاستخراج الإيميلات/الأرقام</label>
        <textarea class="form-control" name="contacts_html" rows="6" style="resize:vertical" placeholder="الصق هنا كود HTML (أو جزء من الجدول) من صفحة المستخدمين. سيتم مطابقة الاسم وملء البريد/الهاتف إن كانت فارغة."><?= Http::e($contactsHtml) ?></textarea>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2-circle me-1"></i>
        تنفيذ الاستيراد
      </button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'استيراد موظفين';
require __DIR__ . '/../shell.php';
