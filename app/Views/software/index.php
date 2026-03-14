<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\Flash;
use Zaco\Core\I18n;

// Initialize flash messages from URL params
Flash::fromUrl('software');
$flashMessages = Flash::render(I18n::locale());

$pageUrl = static function (int $targetPage) use ($q, $cat): string {
  $params = [
    'q' => (string)($q ?? ''),
    'cat' => (string)($cat ?? ''),
    'page' => max(1, $targetPage),
  ];
  return Http::url('/software?' . http_build_query($params));
};

ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">إضافة برامج ورفع ملفات وتحميلها للمستخدمين.</div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/software/export?' . http_build_query(['q' => (string)($q ?? ''), 'cat' => (string)($cat ?? '')]))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>
      تصدير Excel
    </a>
    <?php if (!empty($canEdit)): ?>
      <a class="btn btn-primary" href="<?= Http::e(Http::url('/software/create')) ?>">
        <i class="bi bi-plus-lg me-1"></i>
        إضافة برنامج
      </a>
    <?php endif; ?>
  </div>
</div>

  <?= $flashMessages ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" action="<?= Http::e(Http::url('/software')) ?>" class="row g-2 align-items-end" data-loading>
        <div class="col-md-6">
          <label class="form-label">بحث</label>
          <input class="form-control" name="q" placeholder="بحث بالاسم أو الإصدار..." value="<?= Http::e((string)$q) ?>" />
        </div>
        <div class="col-md-4">
          <label class="form-label">الفئة</label>
          <select class="form-select" name="cat">
            <option value="">كل الفئات</option>
            <?php foreach (($cats ?? []) as $c): ?>
              <option value="<?= Http::e((string)$c) ?>" <?= ((string)$cat === (string)$c) ? 'selected' : '' ?>><?= Http::e((string)$c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary" type="submit">تطبيق</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach (($items ?? []) as $it): ?>
      <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="card h-100">
          <div class="card-header">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="min-w-0">
                <div class="fw-bold text-truncate" title="<?= Http::e((string)$it['name']) ?>"><?= Http::e((string)$it['name']) ?></div>
                <?php if (!empty($it['version'])): ?>
                  <div class="text-muted small"><?= Http::e((string)$it['version']) ?></div>
                <?php endif; ?>
              </div>
              <i class="bi bi-box" aria-hidden="true"></i>
            </div>
          </div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-2">
              <?php if (!empty($it['category'])): ?>
                <span class="badge text-bg-secondary"><?= Http::e((string)$it['category']) ?></span>
              <?php endif; ?>
              <span class="badge <?= ((int)$it['is_free'] === 1) ? 'text-bg-success' : 'text-bg-warning' ?>"><?= ((int)$it['is_free'] === 1) ? 'مجاني' : 'مدفوع' ?></span>
            </div>

            <?php if (!empty($it['description'])): ?>
              <div class="text-muted small" style="line-height:1.7">
                <?= Http::e(mb_strlen((string)$it['description']) > 140 ? (mb_substr((string)$it['description'], 0, 140) . '…') : (string)$it['description']) ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer d-flex flex-wrap gap-2">
            <?php if (!empty($it['file_path'])): ?>
              <a class="btn btn-success btn-sm" href="<?= Http::e(Http::url('/software/download?id=' . (int)$it['id'])) ?>">
                <i class="bi bi-download me-1"></i>
                تحميل
              </a>
            <?php endif; ?>
            <?php if (!empty($it['download_url'])): ?>
              <a class="btn btn-success btn-sm" href="<?= Http::e((string)$it['download_url']) ?>" target="_blank" rel="noopener">
                <i class="bi bi-link-45deg me-1"></i>
                رابط
              </a>
            <?php endif; ?>
            <?php if (empty($it['file_path']) && empty($it['download_url'])): ?>
              <span class="text-muted small"><i class="bi bi-ban me-1"></i>لا يوجد تحميل</span>
            <?php endif; ?>
          </div>

          <?php if (!empty($canEdit) || !empty($canDelete)): ?>
            <div class="card-footer d-flex flex-wrap gap-2">
              <?php if (!empty($canEdit)): ?>
                <a class="btn btn-primary btn-sm" href="<?= Http::e(Http::url('/software/edit?id=' . (int)$it['id'])) ?>">
                  <i class="bi bi-pencil-square me-1"></i>
                  تعديل
                </a>
              <?php endif; ?>
              <?php if (!empty($canDelete)): ?>
                <form method="post" action="<?= Http::e(Http::url('/software/delete')) ?>" data-loading data-confirm="حذف البرنامج؟" class="m-0">
                  <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>" />
                  <button class="btn btn-danger btn-sm" type="submit">
                    <i class="bi bi-trash me-1"></i>
                    حذف
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($items)): ?>
      <div class="col-12">
        <div class="empty-state">
          <i class="bi bi-display"></i>
          <div class="empty-state-title">لا توجد برمجيات</div>
          <p class="empty-state-text">لم يتم العثور على أي برمجيات مطابقة لخيارات الفرز الحالية.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

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
$title = 'البرامج';
require __DIR__ . '/../shell.php';
