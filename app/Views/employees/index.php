<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\Status;
use Zaco\Core\Flash;
use Zaco\Core\I18n;
use Zaco\Core\Avatar;

// Initialize flash messages from URL params
Flash::fromUrl('employee');
$flashMessages = Flash::render(I18n::locale());

$pageUrl = static function (int $targetPage) use ($org_id, $q, $dep, $status): string {
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'q' => (string)($q ?? ''),
    'dep' => (string)($dep ?? ''),
    'status' => (string)($status ?? ''),
    'page' => max(1, $targetPage),
  ];
  return Http::url('/employees?' . http_build_query($params));
};

$sortUrl = static function (string $key) use ($org_id, $q, $dep, $status, $sort, $dir): string {
  $nextDir = ((string)($sort ?? '') === $key && (string)($dir ?? 'desc') === 'asc') ? 'desc' : 'asc';
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'q' => (string)($q ?? ''),
    'dep' => (string)($dep ?? ''),
    'status' => (string)($status ?? ''),
    'sort' => $key,
    'dir' => $nextDir,
    'page' => 1,
  ];
  return Http::url('/employees?' . http_build_query($params));
};

$sortInd = static function (string $key) use ($sort, $dir): string {
  if ((string)($sort ?? '') !== $key) return '';
  return ((string)($dir ?? 'desc') === 'asc') ? '↑' : '↓';
};

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">بحث سريع وتصدير وإدارة بيانات الموظفين.</div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/employees/import')) ?>">
        <i class="bi bi-upload me-1"></i>
        استيراد
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/employees/export?' . http_build_query(['org_id' => (int)($org_id ?? 0), 'q' => (string)($q ?? ''), 'dep' => (string)($dep ?? ''), 'status' => (string)($status ?? '')]))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>
      تصدير Excel
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/employees/create?org_id=' . (int)($org_id ?? 0))) ?>">
        <i class="bi bi-plus-lg me-1"></i>
        إضافة موظف
      </a>
    <?php endif; ?>
  </div>
</div>

  <?= $flashMessages ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= Http::e(Http::url('/employees')) ?>" class="row g-2 align-items-end" data-loading>
        <div class="col-lg-4">
          <label class="form-label">بحث</label>
          <input class="form-control" name="q" placeholder="بحث بالاسم/الرقم/الهاتف/البريد..." value="<?= Http::e((string)$q) ?>" />
        </div>

        <div class="col-lg-3">
          <label class="form-label">المنظمة</label>
          <select class="form-select" name="org_id">
            <option value="">كل المنظمات</option>
            <?php foreach (($orgs ?? []) as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= ((int)($org_id ?? 0) === (int)$o['id']) ? 'selected' : '' ?>><?= Http::e((string)$o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-3">
          <label class="form-label">القسم</label>
          <select class="form-select" name="dep">
            <option value="">كل الأقسام</option>
            <?php foreach (($deps ?? []) as $d): ?>
              <option value="<?= Http::e((string)$d) ?>" <?= ((string)$dep === (string)$d) ? 'selected' : '' ?>><?= Http::e((string)$d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2">
          <label class="form-label">الحالة</label>
          <select class="form-select" name="status">
            <option value="">كل الحالات</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>نشط</option>
            <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>موقوف</option>
            <option value="resigned" <?= $status==='resigned'?'selected':'' ?>>مستقيل</option>
            <option value="terminated" <?= $status==='terminated'?'selected':'' ?>>منتهي</option>
          </select>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-funnel me-1"></i>
            تطبيق
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>
              <?php if (!empty($orgEnabled)): ?>
                <a class="text-decoration-none" href="<?= Http::e($sortUrl('org')) ?>">المنظمة <?= Http::e($sortInd('org')) ?></a>
              <?php else: ?>
                المنظمة
              <?php endif; ?>
            </th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('name')) ?>">الاسم <?= Http::e($sortInd('name')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('no')) ?>">الرقم <?= Http::e($sortInd('no')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('department')) ?>">القسم <?= Http::e($sortInd('department')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('job')) ?>">الوظيفة <?= Http::e($sortInd('job')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('status')) ?>">الحالة <?= Http::e($sortInd('status')) ?></a></th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($items ?? []) as $it): ?>
            <tr>
              <td><?= Http::e((string)($it['org_name'] ?? '')) ?></td>
              <td>
                <a href="<?= Http::e(Http::url('/employees/show?id=' . (int)$it['id'])) ?>" class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-2">
                  <?php if (!empty($it['photo'])): ?>
                    <img class="zaco-avatar-sm rounded-circle" alt="" src="<?= Http::e(Http::url('/employees/photo?id=' . (int)$it['id'])) ?>" loading="lazy" />
                  <?php else: ?>
                    <?= Avatar::html((string)($it['full_name'] ?? ''), (string)($it['id'] ?? ($it['employee_no'] ?? $it['full_name'] ?? '')), 'zaco-avatar-sm', 'border') ?>
                  <?php endif; ?>
                  <span><?= Http::e((string)$it['full_name']) ?></span>
                </a>
              </td>
              <td><?= Http::e((string)$it['employee_no']) ?></td>
              <td><?= Http::e((string)($it['department'] ?? '')) ?></td>
              <td><?= Http::e((string)($it['job_title'] ?? '')) ?></td>
              <?php $stRaw = (string)($it['emp_status'] ?? ''); ?>
              <td>
                <?= Status::employeeStatusHtml($stRaw) ?>
              </td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <?php if (!empty($canEdit)): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/employees/edit?id=' . (int)$it['id'])) ?>">تعديل</a>
                  <?php endif; ?>
                  <?php if (!empty($canDelete)): ?>
                    <form method="post" action="<?= Http::e(Http::url('/employees/delete')) ?>" data-loading data-confirm="حذف الموظف؟">
                      <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                      <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                      <button class="btn btn-outline-danger btn-sm" type="submit">حذف</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty-state">
      <i class="bi bi-people"></i>
      <div class="empty-state-title">لا توجد نتائج</div>
      <p class="empty-state-text">لم يتم العثور على موظفين مطابقين للبحث.</p>
    </div>
  <?php endif; ?>

  <?php
    $pageNum = (int)($page ?? 1);
    if ($pageNum < 1) $pageNum = 1;
    $pages = (int)($totalPages ?? 1);
    if ($pages < 1) $pages = 1;
  ?>
  <?php if ($pages > 1): ?>
    <div class="card">
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted">صفحة <?= $pageNum ?> من <?= $pages ?></div>
        <div class="d-flex gap-2">
          <?php if ($pageNum > 1): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e($pageUrl($pageNum - 1)) ?>">السابق</a>
          <?php endif; ?>
          <?php if ($pageNum < $pages): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e($pageUrl($pageNum + 1)) ?>">التالي</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'الموظفون';
require __DIR__ . '/../shell.php';
