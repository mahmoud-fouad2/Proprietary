<?php
declare(strict_types=1);

use Zaco\Core\Http;

$logs = $logs ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? 0;
$actions = $actions ?? [];
$tables = $tables ?? [];
$filterAction = $filterAction ?? '';
$filterTable = $filterTable ?? '';
$filterActor = $filterActor ?? '';

$actionLabels = [
    'Login' => 'تسجيل دخول',
    'LoginFailed' => 'فشل دخول',
    'Logout' => 'تسجيل خروج',
    'Create' => 'إضافة',
    'Update' => 'تعديل',
    'Delete' => 'حذف',
    'Restore' => 'استعادة',
    'ChangePassword' => 'تغيير كلمة مرور',
    'RunMaintenance' => 'صيانة',
];

$tableLabels = [
    'users' => 'المستخدمون',
    'employees' => 'الموظفون',
    'assets' => 'الأصول',
    'custody' => 'العُهد',
    'software_library' => 'البرامج',
    'organizations' => 'المنظمات',
    'settings' => 'الإعدادات',
    'system' => 'النظام',
];

ob_start();
?>
<div>
  <ul class="nav nav-pills nav-sm mb-3">
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings')) ?>">الإعدادات العامة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings/tools')) ?>">أدوات الصيانة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" href="<?= Http::e(Http::url('/settings/audit')) ?>">سجل العمليات</a>
    </li>
  </ul>

  <div class="card">
    <div class="card-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-bold">
          <i class="bi bi-clock-history me-2"></i>سجل العمليات
          <span class="badge text-bg-secondary ms-2"><?= number_format($total) ?></span>
        </div>
      </div>
    </div>
    <div class="card-body">
      <!-- Filters -->
      <form method="get" action="<?= Http::e(Http::url('/settings/audit')) ?>" class="row g-2 mb-3">
        <div class="col-auto">
          <select name="action" class="form-select form-select-sm">
            <option value="">جميع العمليات</option>
            <?php foreach ($actions as $a): ?>
              <option value="<?= Http::e($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                <?= Http::e($actionLabels[$a] ?? $a) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <select name="table" class="form-select form-select-sm">
            <option value="">جميع الجداول</option>
            <?php foreach ($tables as $t): ?>
              <option value="<?= Http::e($t) ?>" <?= $filterTable === $t ? 'selected' : '' ?>>
                <?= Http::e($tableLabels[$t] ?? $t) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <input type="text" name="actor" class="form-control form-control-sm" placeholder="اسم المستخدم..." value="<?= Http::e($filterActor) ?>" />
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">بحث</button>
          <?php if ($filterAction !== '' || $filterTable !== '' || $filterActor !== ''): ?>
            <a href="<?= Http::e(Http::url('/settings/audit')) ?>" class="btn btn-outline-secondary btn-sm">مسح</a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 50px">#</th>
              <th>المستخدم</th>
              <th>العملية</th>
              <th>الجدول</th>
              <th>التفاصيل</th>
              <th style="width: 120px">IP</th>
              <th style="width: 150px">التاريخ</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="7" class="p-0">
                  <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <div class="empty-state-title">لا توجد سجلات</div>
                    <p class="empty-state-text">لم يتم العثور على أي عمليات مطابقة للبحث.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <?php
                  $action = (string)($log['action'] ?? '');
                  $actionBadge = match($action) {
                      'Create' => 'text-bg-success',
                      'Update' => 'text-bg-info',
                      'Delete' => 'text-bg-danger',
                      'Restore' => 'text-bg-warning',
                      'Login' => 'text-bg-primary',
                      'LoginFailed' => 'text-bg-danger',
                      'Logout' => 'text-bg-secondary',
                      default => 'text-bg-light text-dark'
                  };
                  $tableName = (string)($log['table_name'] ?? '');
                ?>
                <tr>
                  <td class="text-muted small"><?= (int)$log['id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= Http::e((string)($log['actor_name'] ?? '-')) ?></div>
                  </td>
                  <td>
                    <span class="badge <?= $actionBadge ?>"><?= Http::e($actionLabels[$action] ?? $action) ?></span>
                  </td>
                  <td>
                    <span class="text-muted small"><?= Http::e($tableLabels[$tableName] ?? $tableName) ?></span>
                  </td>
                  <td>
                    <?php
                      $details = (string)($log['details'] ?? '');
                      if (mb_strlen($details) > 50) {
                          $details = mb_substr($details, 0, 50) . '...';
                      }
                    ?>
                    <span class="small text-muted" title="<?= Http::e((string)($log['details'] ?? '')) ?>">
                      <?= Http::e($details) ?>
                    </span>
                  </td>
                  <td>
                    <code class="small"><?= Http::e((string)($log['ip'] ?? '-')) ?></code>
                  </td>
                  <td>
                    <span class="small text-muted"><?= Http::e((string)($log['created_at'] ?? '')) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="<?= Http::e(Http::url('/settings/audit?page=' . ($page - 1) . '&action=' . urlencode($filterAction) . '&table=' . urlencode($filterTable) . '&actor=' . urlencode($filterActor))) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            <?php endif; ?>
            
            <?php
              $startPage = max(1, $page - 2);
              $endPage = min($totalPages, $page + 2);
            ?>
            
            <?php if ($startPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="<?= Http::e(Http::url('/settings/audit?page=1&action=' . urlencode($filterAction) . '&table=' . urlencode($filterTable) . '&actor=' . urlencode($filterActor))) ?>">1</a>
              </li>
              <?php if ($startPage > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= Http::e(Http::url('/settings/audit?page=' . $i . '&action=' . urlencode($filterAction) . '&table=' . urlencode($filterTable) . '&actor=' . urlencode($filterActor))) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="<?= Http::e(Http::url('/settings/audit?page=' . $totalPages . '&action=' . urlencode($filterAction) . '&table=' . urlencode($filterTable) . '&actor=' . urlencode($filterActor))) ?>"><?= $totalPages ?></a>
              </li>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
              <li class="page-item">
                <a class="page-link" href="<?= Http::e(Http::url('/settings/audit?page=' . ($page + 1) . '&action=' . urlencode($filterAction) . '&table=' . urlencode($filterTable) . '&actor=' . urlencode($filterActor))) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'سجل العمليات';
require __DIR__ . '/../shell.php';
