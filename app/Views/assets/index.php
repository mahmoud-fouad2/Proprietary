<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;
use Zaco\Core\Status;
use Zaco\Core\Flash;

// Initialize flash messages from URL params (backward compatibility)
Flash::fromUrl('asset');
$flashMessages = Flash::render(I18n::locale());
$undoData = Flash::getUndo();

$pageUrl = static function (int $targetPage) use ($org_id, $q, $cat, $cond, $view): string {
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'q' => (string)($q ?? ''),
    'cat' => (string)($cat ?? ''),
    'cond' => (string)($cond ?? ''),
    'view' => (string)($view ?? 'list'),
    'page' => max(1, $targetPage),
  ];
  return Http::url('/inventory?' . http_build_query($params));
};

$sortUrl = static function (string $key) use ($org_id, $q, $cat, $cond, $view, $sort, $dir): string {
  $nextDir = ((string)($sort ?? '') === $key && (string)($dir ?? 'desc') === 'asc') ? 'desc' : 'asc';
  $params = [
    'org_id' => (int)($org_id ?? 0),
    'q' => (string)($q ?? ''),
    'cat' => (string)($cat ?? ''),
    'cond' => (string)($cond ?? ''),
    'view' => (string)($view ?? 'list'),
    'sort' => $key,
    'dir' => $nextDir,
    'page' => 1,
  ];
  return Http::url('/inventory?' . http_build_query($params));
};

$sortInd = static function (string $key) use ($sort, $dir): string {
  if ((string)($sort ?? '') !== $key) return '';
  return ((string)($dir ?? 'desc') === 'asc') ? '↑' : '↓';
};

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">
    <?= I18n::locale() === 'ar'
      ? ('عدد الأصول: ' . (int)$count . ' | إجمالي الكميات: ' . (int)($qty ?? 0) . ' | إجمالي القيمة: ' . number_format((float)$value, 2) . ' ر.س | منخفض المخزون: ' . (int)($low ?? 0))
      : ('Assets: ' . (int)$count . ' | Total qty: ' . (int)($qty ?? 0) . ' | Total value: ' . number_format((float)$value, 2) . ' SAR | Low stock: ' . (int)($low ?? 0))
    ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/inventory/export?' . http_build_query(['org_id' => (int)($org_id ?? 0), 'q' => (string)($q ?? ''), 'cat' => (string)($cat ?? ''), 'cond' => (string)($cond ?? '')]))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>
      <?= Http::e(I18n::t('common.export_excel')) ?>
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/inventory/create?org_id=' . (int)($org_id ?? 0))) ?>">
        <i class="bi bi-plus-lg me-1"></i>
        <?= Http::e(I18n::t('inv.add')) ?>
      </a>
    <?php endif; ?>
  </div>
</div>

  <?= $flashMessages ?>

  <?php $moveErr = Http::getString('move_err'); ?>
  <?php if ($moveErr === 'missing'): ?><div class="alert alert-danger">ميزة نقل الأقسام غير مفعّلة. شغّل migration: <strong>scripts/migrate_asset_structure.mysql.sql</strong></div><?php endif; ?>
  <?php if ($moveErr === 'empty'): ?><div class="alert alert-danger">اختر أصولًا للنقل أولاً.</div><?php endif; ?>
  <?php if ($moveErr === 'sub_invalid'): ?><div class="alert alert-danger">القسم الفرعي غير صحيح.</div><?php endif; ?>
  <?php if ($moveErr === 'sub_mismatch'): ?><div class="alert alert-danger">القسم الفرعي لا يتبع القسم المختار.</div><?php endif; ?>

  <?php if (!empty($orgTableExists) && empty($orgColumnExists)): ?>
    <div class="alert alert-danger">تم إنشاء منظمات، لكن جدول الأصول لا يحتوي على عمود org_id لذلك فلتر المنظمات وتسجيل الأصل على منظمة لن يعمل. شغّل ملف migration: <strong>scripts/migrate_orgs.mysql.sql</strong></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= Http::e(Http::url('/inventory')) ?>" class="row g-2 align-items-end" data-loading>
        <div class="col-lg-4">
          <label class="form-label"><?= Http::e(I18n::t('inv.search')) ?></label>
          <input class="form-control" name="q" placeholder="<?= Http::e(I18n::t('inv.search')) ?>" value="<?= Http::e((string)$q) ?>" />
        </div>
        <?php if (!empty($orgEnabled)): ?>
          <div class="col-lg-2">
            <label class="form-label"><?= Http::e(I18n::t('common.organization')) ?></label>
            <select class="form-select" name="org_id">
              <option value=""><?= Http::e(I18n::t('common.all_orgs')) ?></option>
              <?php foreach (($orgs ?? []) as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= ((int)($org_id ?? 0) === (int)$o['id']) ? 'selected' : '' ?>><?= Http::e((string)$o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="col-lg-2">
          <label class="form-label"><?= Http::e(I18n::t('inv.category')) ?></label>
          <select class="form-select" name="cat">
            <option value=""><?= Http::e(I18n::t('inv.all_categories')) ?></option>
            <?php foreach (($cats ?? []) as $c): ?>
              <option value="<?= Http::e((string)$c) ?>" <?= ((string)$cat === (string)$c) ? 'selected' : '' ?>><?= Http::e((string)$c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label"><?= Http::e(I18n::t('inv.condition')) ?></label>
          <select class="form-select" name="cond">
            <option value=""><?= Http::e(I18n::t('inv.all_conditions')) ?></option>
            <option value="excellent" <?= $cond==='excellent'?'selected':'' ?>>ممتاز</option>
            <option value="good" <?= $cond==='good'?'selected':'' ?>>جيد</option>
            <option value="fair" <?= $cond==='fair'?'selected':'' ?>>مقبول</option>
            <option value="poor" <?= $cond==='poor'?'selected':'' ?>>سيئ</option>
            <option value="disposed" <?= $cond==='disposed'?'selected':'' ?>>مستبعد</option>
          </select>
        </div>
        <div class="col-lg-1">
          <label class="form-label"><?= Http::e(I18n::t('inv.view_table')) ?></label>
          <select class="form-select" name="view">
            <option value="list" <?= $view==='list'?'selected':'' ?>><?= Http::e(I18n::t('inv.view_table')) ?></option>
            <option value="cards" <?= $view==='cards'?'selected':'' ?>><?= Http::e(I18n::t('inv.view_cards')) ?></option>
          </select>
        </div>
        <div class="col-lg-1 d-grid">
          <button class="btn btn-primary" type="submit"><?= Http::e(I18n::t('common.apply')) ?></button>
        </div>
      </form>
    </div>
  </div>

  <?php if (($view ?? 'list') === 'cards'): ?>
    <div class="row g-3">
      <?php foreach (($items ?? []) as $it): ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
          <div class="card h-100">
            <a class="text-decoration-none text-reset" href="<?= Http::e(Http::url('/inventory/show?id=' . (int)$it['id'])) ?>">
              <?php if (!empty($it['image_path'])): ?>
                <img class="card-img-top" src="<?= Http::e(Http::url('/inventory/image?id=' . (int)$it['id'])) ?>" alt="<?= Http::e((string)$it['name']) ?>" loading="lazy" style="object-fit:cover; height:160px" />
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center bg-body-tertiary text-muted" style="height:160px"><?= I18n::locale() === 'ar' ? 'لا توجد صورة' : 'No image' ?></div>
              <?php endif; ?>

              <div class="card-body">
                <div class="fw-bold text-truncate" title="<?= Http::e((string)$it['name']) ?>"><?= Http::e((string)$it['name']) ?></div>
                <div class="text-muted small" style="line-height:1.7">
                  <?php if (!empty($it['org_name'])): ?>
                    <div class="text-truncate" title="<?= Http::e((string)$it['org_name']) ?>"><?= Http::e((string)$it['org_name']) ?></div>
                  <?php endif; ?>
                  <div class="text-truncate" title="<?= Http::e((string)($it['location'] ?? '')) ?>">
                    <?= I18n::locale() === 'ar' ? 'الموقع: ' : 'Location: ' ?><?= Http::e((string)($it['location'] ?? '—')) ?>
                  </div>
                  <div class="text-truncate" title="<?= Http::e((string)$it['category']) ?>">
                    <?= I18n::locale() === 'ar' ? 'الفئة: ' : 'Category: ' ?><?= Http::e((string)$it['category']) ?>
                  </div>
                  <div class="text-truncate" title="<?= Http::e((string)$it['code']) ?>">
                    <?= I18n::locale() === 'ar' ? 'الكود: ' : 'Code: ' ?><span class="font-monospace"><?= Http::e((string)$it['code']) ?></span>
                  </div>
                </div>

                <?php $stRaw = (string)($it['asset_condition'] ?? ''); ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                  <span class="badge text-bg-secondary"><?= I18n::locale() === 'ar' ? 'الكمية: ' : 'Qty: ' ?><?= (int)$it['quantity'] ?></span>
                  <span class="badge text-bg-light border"><?= number_format((float)$it['cost'], 2) ?></span>
                  <?= Status::assetConditionHtml($stRaw, I18n::locale()) ?>
                </div>
              </div>
            </a>

            <?php if (!empty($canEdit) || !empty($canDelete)): ?>
              <div class="card-footer d-flex gap-2 flex-wrap">
                <?php if (!empty($canEdit)): ?>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/inventory/edit?id=' . (int)$it['id'])) ?>"><?= Http::e(I18n::t('inv.edit')) ?></a>
                <?php endif; ?>
                <?php if (!empty($canDelete)): ?>
                  <form method="post" action="<?= Http::e(Http::url('/inventory/delete')) ?>" data-loading data-confirm="<?= Http::e(I18n::t('inv.confirm_delete')) ?>" class="m-0">
                    <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                    <button class="btn btn-danger btn-sm" type="submit"><?= Http::e(I18n::t('inv.delete')) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <div class="col-12">
          <div class="alert alert-info mb-0"><?= I18n::locale() === 'ar' ? 'لا توجد نتائج.' : 'No results.' ?></div>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <!-- Move Modal -->
    <?php if (!empty($sectionsEnabled) && !empty($canEdit)): ?>
      <?php $subsJson = json_encode(($subsectionsBySection ?? []), JSON_UNESCAPED_UNICODE); ?>
      <div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="moveModalLabel">
                <i class="bi bi-arrows-move me-2"></i>
                نقل الأصول المختارة
              </h5>
              <?php $lang = \Zaco\Core\I18n::locale(); ?>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= Http::e($lang === 'ar' ? 'إغلاق' : 'Close') ?>"></button>
            </div>
            <form id="bulkMoveForm" method="post" action="<?= Http::e(Http::url('/inventory/move-bulk')) ?>" data-loading data-confirm="هل تريد نقل الأصول المختارة؟">
              <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                <div class="mb-3">
                  <label class="form-label fw-medium">القسم</label>
                  <select class="form-select" name="section_id" id="bulkSection" data-section-select data-subsection-id="bulkSubsection">
                    <option value="">اختر القسم (أو بدون)</option>
                    <?php foreach (($assetSections ?? []) as $s): ?>
                      <option value="<?= (int)($s['id'] ?? 0) ?>"><?= Http::e((string)($s['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium">القسم الفرعي</label>
                  <select class="form-select" name="subsection_id" id="bulkSubsection" data-subsection-select data-subsections-json="<?= Http::e((string)$subsJson) ?>">
                    <option value="">اختر القسم الفرعي (اختياري)</option>
                  </select>
                </div>
                <div class="alert alert-info small mb-0">
                  <i class="bi bi-info-circle me-1"></i>
                  سيتم نقل جميع الأصول المحددة إلى القسم والقسم الفرعي المختار
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-check-lg me-1"></i>
                  نقل المختار
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card zaco-table-card">
      <?php if (!empty($sectionsEnabled) && !empty($canEdit)): ?>
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
          <span class="text-muted small">
            <i class="bi bi-check2-square me-1"></i>
            حدد الأصول من الجدول ثم اضغط نقل
          </span>
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moveModal" disabled>
            <i class="bi bi-arrows-move me-1"></i>
            نقل المختار
            <span class="badge bg-primary rounded-pill ms-1" hidden>0</span>
          </button>
        </div>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 zaco-assets-table">
          <thead class="table-light">
            <tr>
              <?php if (!empty($sectionsEnabled) && !empty($canEdit)): ?>
                <th class="text-center" style="width:40px">
                  <input class="form-check-input" type="checkbox" id="selectAllAssets" title="تحديد الكل" />
                </th>
              <?php endif; ?>
              <th class="fw-semibold">
                <?php if (!empty($orgEnabled)): ?>
                  <a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('org')) ?>"><?= Http::e(I18n::t('common.organization')) ?> <span class="text-muted"><?= Http::e($sortInd('org')) ?></span></a>
                <?php else: ?>
                  <?= Http::e(I18n::t('common.organization')) ?>
                <?php endif; ?>
              </th>
              <th class="fw-semibold"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('name')) ?>"><?= Http::e(I18n::t('inv.name')) ?> <span class="text-muted"><?= Http::e($sortInd('name')) ?></span></a></th>
              <th class="fw-semibold"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('category')) ?>"><?= Http::e(I18n::t('inv.category')) ?> <span class="text-muted"><?= Http::e($sortInd('category')) ?></span></a></th>
              <th class="fw-semibold"><?= I18n::locale() === 'ar' ? 'الموقع' : 'Location' ?></th>
              <th class="fw-semibold"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('code')) ?>"><?= Http::e(I18n::t('inv.code')) ?> <span class="text-muted"><?= Http::e($sortInd('code')) ?></span></a></th>
              <th class="fw-semibold text-center"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('qty')) ?>"><?= Http::e(I18n::t('inv.qty')) ?> <span class="text-muted"><?= Http::e($sortInd('qty')) ?></span></a></th>
              <th class="fw-semibold text-center"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('cost')) ?>"><?= Http::e(I18n::t('inv.cost')) ?> <span class="text-muted"><?= Http::e($sortInd('cost')) ?></span></a></th>
              <th class="fw-semibold text-center"><a class="text-decoration-none text-dark" href="<?= Http::e($sortUrl('condition')) ?>"><?= Http::e(I18n::t('inv.condition')) ?> <span class="text-muted"><?= Http::e($sortInd('condition')) ?></span></a></th>
              <th class="fw-semibold text-center"><?= Http::e(I18n::t('common.actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($items ?? []) as $it): ?>
              <tr>
                <?php if (!empty($sectionsEnabled) && !empty($canEdit)): ?>
                  <td class="text-center">
                    <input class="form-check-input asset-checkbox" type="checkbox" name="ids[]" value="<?= (int)$it['id'] ?>" form="bulkMoveForm" />
                  </td>
                <?php endif; ?>
                <td><?= Http::e((string)($it['org_name'] ?? '—')) ?></td>
                <td>
                  <a href="<?= Http::e(Http::url('/inventory/show?id=' . (int)$it['id'])) ?>" class="text-decoration-none fw-medium"><?= Http::e((string)$it['name']) ?></a>
                </td>
                <td><?= Http::e((string)$it['category']) ?></td>
                <td><?= Http::e((string)($it['location'] ?? '—')) ?></td>
                <td><code class="text-dark"><?= Http::e((string)$it['code']) ?></code></td>
                <td class="text-center"><?= (int)$it['quantity'] ?></td>
                <td class="text-center"><?= number_format((float)$it['cost'], 2) ?></td>
                <?php $stRaw = (string)($it['asset_condition'] ?? ''); ?>
                <td class="text-center">
                  <?= Status::assetConditionHtml($stRaw, I18n::locale()) ?>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <?php if (!empty($canEdit)): ?>
                      <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/inventory/edit?id=' . (int)$it['id'])) ?>" title="<?= Http::e(I18n::t('inv.edit')) ?>">
                        <i class="bi bi-pencil"></i>
                      </a>
                    <?php endif; ?>
                    <?php if (!empty($canDelete)): ?>
                      <form method="post" action="<?= Http::e(Http::url('/inventory/delete')) ?>" data-loading data-confirm="<?= Http::e(I18n::t('inv.confirm_delete')) ?>" class="m-0 d-inline">
                        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                        <button class="btn btn-outline-danger" type="submit" title="<?= Http::e(I18n::t('inv.delete')) ?>">
                          <i class="bi bi-trash"></i>
                        </button>
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
            <div class="empty-state-title"><?= I18n::locale() === 'ar' ? 'لا توجد أصول' : 'No Assets Found' ?></div>
            <p class="empty-state-text"><?= I18n::locale() === 'ar' ? 'لم يتم العثور على أي بيانات مطابقة لخيارات الفرز الحالية.' : 'No data found matching your filter criteria.' ?></p>
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
        <div class="text-muted">
          <?= I18n::locale() === 'ar'
            ? ('صفحة ' . $pageNum . ' من ' . $pages)
            : ('Page ' . $pageNum . ' of ' . $pages) ?>
        </div>
        <div class="d-flex gap-2">
          <?php if ($pageNum > 1): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e($pageUrl($pageNum - 1)) ?>"><?= I18n::locale() === 'ar' ? 'السابق' : 'Prev' ?></a>
          <?php endif; ?>
          <?php if ($pageNum < $pages): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e($pageUrl($pageNum + 1)) ?>"><?= I18n::locale() === 'ar' ? 'التالي' : 'Next' ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php
$content = ob_get_clean();
$title = I18n::t('nav.inventory');
require __DIR__ . '/../shell.php';
