<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\Status;
use Zaco\Core\Flash;
use Zaco\Core\I18n;

// Initialize flash messages from URL params
Flash::fromUrl('custody');
$flashMessages = Flash::render(I18n::locale());

$pageUrl = static function (int $targetPage) use ($org_id, $employee_id, $q, $status, $view): string {
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'employee_id' => (int)($employee_id ?? 0),
    'q' => (string)($q ?? ''),
    'status' => (string)($status ?? ''),
    'view' => (string)($view ?? 'list'),
    'page' => max(1, $targetPage),
  ];
  return Http::url('/custody?' . http_build_query($params));
};

$sortUrl = static function (string $key) use ($org_id, $employee_id, $q, $status, $view, $sort, $dir): string {
  $nextDir = ((string)($sort ?? '') === $key && (string)($dir ?? 'desc') === 'asc') ? 'desc' : 'asc';
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'employee_id' => (int)($employee_id ?? 0),
    'q' => (string)($q ?? ''),
    'status' => (string)($status ?? ''),
    'view' => (string)($view ?? 'list'),
    'sort' => $key,
    'dir' => $nextDir,
    'page' => 1,
  ];
  return Http::url('/custody?' . http_build_query($params));
};

$sortInd = static function (string $key) use ($sort, $dir): string {
  if ((string)($sort ?? '') !== $key) return '';
  return ((string)($dir ?? 'desc') === 'asc') ? '↑' : '↓';
};

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">تسجيل وتسليم وإرجاع العُهد حسب الموظف والحالة.</div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/custody/export?' . http_build_query(['org_id' => (int)($org_id ?? 0), 'employee_id' => (int)($employee_id ?? 0), 'q' => (string)($q ?? ''), 'status' => (string)($status ?? '')]))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>
      تصدير Excel
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/custody/create?org_id=' . (int)($org_id ?? 0))) ?>">
        <i class="bi bi-plus-lg me-1"></i>
        إضافة عهدة
      </a>
    <?php endif; ?>
  </div>
</div>

  <?= $flashMessages ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= Http::e(Http::url('/custody')) ?>" class="row g-2 align-items-end" data-loading>
        <?php if ((int)($employee_id ?? 0) > 0): ?>
          <input type="hidden" name="employee_id" value="<?= (int)$employee_id ?>" />
        <?php endif; ?>
        <div class="col-md-6">
          <label class="form-label">بحث</label>
          <input class="form-control" name="q" placeholder="بحث بالموظف/العهدة/السيريال..." value="<?= Http::e((string)$q) ?>" />
        </div>
        <div class="col-md-3">
          <label class="form-label">المنظمة</label>
          <select class="form-select" name="org_id">
            <option value="">كل المنظمات</option>
            <?php foreach (($orgs ?? []) as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= ((int)($org_id ?? 0) === (int)$o['id']) ? 'selected' : '' ?>><?= Http::e((string)$o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">الحالة</label>
          <select class="form-select" name="status">
            <option value="">كل الحالات</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>فعالة</option>
            <option value="returned" <?= $status==='returned'?'selected':'' ?>>مُسترجعة</option>
            <option value="damaged" <?= $status==='damaged'?'selected':'' ?>>تالفة</option>
            <option value="lost" <?= $status==='lost'?'selected':'' ?>>مفقودة</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label">العرض</label>
          <?php $v = (string)($view ?? 'list'); if (!in_array($v, ['list', 'kanban'], true)) $v = 'list'; ?>
          <select class="form-select" name="view">
            <option value="list" <?= $v==='list'?'selected':'' ?>>جدول</option>
            <option value="kanban" <?= $v==='kanban'?'selected':'' ?>>كانبان</option>
          </select>
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary" type="submit">تطبيق</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($v === 'kanban'): ?>
    <?php
      $cols = [
        'active' => 'فعّالة',
        'returned' => 'مُسترجعة',
        'damaged' => 'تالفة',
        'lost' => 'مفقودة',
      ];
      $grouped = ['active' => [], 'returned' => [], 'damaged' => [], 'lost' => [], 'other' => []];
      foreach (($items ?? []) as $it) {
        $st = (string)($it['custody_status'] ?? '');
        if (!isset($grouped[$st])) $st = 'other';
        $grouped[$st][] = $it;
      }
    ?>
    <div class="row g-3">
      <?php foreach ($cols as $key => $label): ?>
        <div class="col-12 col-lg-3">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="fw-bold"><?= Http::e($label) ?></div>
              <span class="badge text-bg-light border"><?= (int)count($grouped[$key] ?? []) ?></span>
            </div>
            <div class="card-body zaco-kanban-col">
              <?php foreach (($grouped[$key] ?? []) as $it): ?>
                <?php $stRaw = (string)($it['custody_status'] ?? ''); ?>
                <div class="card mb-2">
                  <div class="card-body p-2">
                    <div class="d-flex justify-content-between gap-2">
                      <div class="fw-semibold text-truncate" title="<?= Http::e((string)($it['item_name'] ?? '')) ?>"><?= Http::e((string)($it['item_name'] ?? '')) ?></div>
                      <span class="badge <?= Http::e(Status::custodyStatusBadge($stRaw)) ?>"><?= Http::e(Status::custodyStatus($stRaw, I18n::locale())) ?></span>
                    </div>
                    <div class="text-muted small">
                      <?= Http::e((string)($it['employee_name'] ?? '')) ?>
                      <?php if (!empty($it['serial_number'])): ?> • <?= Http::e((string)$it['serial_number']) ?><?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= Http::e((string)($it['date_assigned'] ?? '')) ?></div>
                    <div class="d-flex gap-2 flex-wrap mt-2">
                      <?php if (!empty($it['attachment_path'])): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/custody/attachment?id=' . (int)$it['id'])) ?>">مرفق</a>
                      <?php endif; ?>
                      <?php if (!empty($canEdit)): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/custody/edit?id=' . (int)$it['id'])) ?>">تعديل</a>
                      <?php endif; ?>
                      <?php if (!empty($canDelete)): ?>
                        <form method="post" action="<?= Http::e(Http::url('/custody/delete')) ?>" data-loading data-confirm="حذف العهدة؟" class="m-0">
                          <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                          <button class="btn btn-outline-danger btn-sm" type="submit">حذف</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($grouped[$key])): ?>
                <div class="text-muted">—</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <div class="col-12">
          <div class="empty-state">
            <i class="bi bi-box-seam"></i>
            <div class="empty-state-title">لا توجد عهد</div>
            <p class="empty-state-text">لم يتم العثور على أي عهد مطابقة لخيارات الفرز الحالية.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>
                <?php if (!empty($orgEnabled)): ?>
                  <a class="text-decoration-none" href="<?= Http::e($sortUrl('org')) ?>">المنظمة <span class="text-muted"><?= Http::e($sortInd('org')) ?></span></a>
                <?php else: ?>
                  المنظمة
                <?php endif; ?>
              </th>
              <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('employee')) ?>">الموظف <span class="text-muted"><?= Http::e($sortInd('employee')) ?></span></a></th>
              <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('item')) ?>">العهدة <span class="text-muted"><?= Http::e($sortInd('item')) ?></span></a></th>
              <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('serial')) ?>">السيريال <span class="text-muted"><?= Http::e($sortInd('serial')) ?></span></a></th>
              <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('date')) ?>">تاريخ التسليم <span class="text-muted"><?= Http::e($sortInd('date')) ?></span></a></th>
              <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('status')) ?>">الحالة <span class="text-muted"><?= Http::e($sortInd('status')) ?></span></a></th>
              <th>مرفق</th>
              <th>إجراء</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($items ?? []) as $it): ?>
              <tr>
                <td><?= Http::e((string)($it['org_name'] ?? '')) ?></td>
                <td><?= Http::e((string)$it['employee_name']) ?></td>
                <td><?= Http::e((string)$it['item_name']) ?></td>
                <td><?= Http::e((string)($it['serial_number'] ?? '')) ?></td>
                <td><?= Http::e((string)$it['date_assigned']) ?></td>
                <?php $stRaw = (string)($it['custody_status'] ?? ''); ?>
                <td>
                  <span class="badge <?= Http::e(Status::custodyStatusBadge($stRaw)) ?>">
                    <?= Http::e(Status::custodyStatus($stRaw, I18n::locale())) ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($it['attachment_path'])): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/custody/attachment?id=' . (int)$it['id'])) ?>">تحميل</a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php if (!empty($canEdit)): ?>
                      <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/custody/edit?id=' . (int)$it['id'])) ?>">تعديل</a>
                    <?php endif; ?>
                    <?php if (!empty($canDelete)): ?>
                      <form method="post" action="<?= Http::e(Http::url('/custody/delete')) ?>" data-loading data-confirm="حذف العهدة؟" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (empty($items)): ?>
        <div class="card-body">
          <div class="empty-state">
            <i class="bi bi-box-seam"></i>
            <div class="empty-state-title">لا توجد عهد</div>
            <p class="empty-state-text">لم يتم العثور على أي عهد مطابقة لخيارات الفرز الحالية.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php
    $pageNum = (int)($page ?? 1);
    if ($pageNum < 1) $pageNum = 1;
    $pages = (int)($totalPages ?? 1);
    if ($pages < 1) $pages = 1;
  ?>
  <?php if ($pages > 1): ?>
    <div class="card mt-3">
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
$title = 'العُهد';
echo $content;
