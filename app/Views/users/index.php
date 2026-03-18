<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\Flash;
use Zaco\Core\Status;
use Zaco\Core\I18n;
use Zaco\Core\Avatar;
use Zaco\Security\Csrf;

// Initialize flash messages from URL params
Flash::fromUrl('user');
$flashMessages = Flash::render(I18n::locale());

$sort = $sort ?? '';
$dir = $dir ?? 'desc';

$pageUrl = static function (int $targetPage) use ($sort, $dir): string {
  $params = [
    'page' => max(1, $targetPage),
  ];
  if (is_string($sort) && $sort !== '') {
    $params['sort'] = $sort;
  }
  if (is_string($dir) && $dir !== '') {
    $params['dir'] = $dir;
  }
  return Http::url('/users?' . http_build_query($params));
};

$sortUrl = static function (string $key) use ($sort, $dir): string {
  $nextDir = ((string)($sort ?? '') === $key && (string)($dir ?? 'desc') === 'asc') ? 'desc' : 'asc';
  $params = [
    'sort' => $key,
    'dir' => $nextDir,
    'page' => 1,
  ];
  return Http::url('/users?' . http_build_query($params));
};

$sortInd = static function (string $key) use ($sort, $dir): string {
  if ((string)($sort ?? '') !== $key) return '';
  return ((string)($dir ?? 'desc') === 'asc') ? '↑' : '↓';
};
ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">إضافة وتعديل وإدارة صلاحيات الحسابات.</div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-primary" href="<?= Http::e(Http::url('/users/create')) ?>">
      <i class="bi bi-person-plus me-1"></i>
      إضافة مستخدم
    </a>
  </div>
</div>

  <?= $flashMessages ?>

  <div class="card mb-3">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('name')) ?>">الاسم <?= Http::e($sortInd('name')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('email')) ?>">البريد <?= Http::e($sortInd('email')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('role')) ?>">الدور <?= Http::e($sortInd('role')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('active')) ?>">الحالة <?= Http::e($sortInd('active')) ?></a></th>
            <th><a class="text-decoration-none" href="<?= Http::e($sortUrl('last')) ?>">آخر دخول <?= Http::e($sortInd('last')) ?></a></th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php $currentUserId = (int)(($currentUser ?? [])['id'] ?? 0); ?>

          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="p-4">
                <div class="empty-state">
                  <i class="bi bi-people"></i>
                  <div class="empty-state-title">لا يوجد مستخدمون</div>
                  <p class="empty-state-text">لم يتم العثور على أي مستخدمين.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach (($users ?? []) as $u): $isSelf = ((int)$u['id'] === $currentUserId); ?>
            <tr>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <?= Avatar::html((string)($u['name'] ?? ''), (string)($u['id'] ?? ($u['email'] ?? $u['name'] ?? '')), 'zaco-avatar-sm', 'border') ?>
                  <span><?= htmlspecialchars((string)$u['name'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
              </td>
              <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <?php $roleRaw = (string)($u['role'] ?? ''); $roleLabel = in_array($roleRaw, ['admin','superadmin'], true) ? 'أدمن' : 'مستخدم'; ?>
              <td><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <?php if ((int)$u['is_active'] === 1): ?>
                  <span class="badge text-bg-success">نشط</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">معطل</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)($u['last_login_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/users/edit?id=' . (int)$u['id'])) ?>">
                    <i class="bi bi-pencil-square me-1"></i>
                    تعديل
                  </a>
                  <?php if (!$isSelf): ?>
                    <form method="post" action="<?= Http::e(Http::url('/users/toggle')) ?>" data-loading>
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                      <button class="btn btn-outline-warning btn-sm" type="submit"><?= ((int)$u['is_active'] === 1) ? 'تعطيل' : 'تفعيل' ?></button>
                    </form>
                    <form method="post" action="<?= Http::e(Http::url('/users/delete')) ?>" data-confirm="هل أنت متأكد من حذف هذا المستخدم؟" data-loading>
                      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
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
$title = 'المستخدمون';
require __DIR__ . '/../shell.php';
