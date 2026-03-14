<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\I18n;

$checks = $checks ?? [];
$tables = $tables ?? [];
$appLog = (string)($appLog ?? '');
$phpLog = (string)($phpLog ?? '');
$appLogExists = !empty($appLogExists);
$phpLogExists = !empty($phpLogExists);
$appLogSize = (int)($appLogSize ?? 0);
$phpLogSize = (int)($phpLogSize ?? 0);

$lang = I18n::locale();

ob_start();
?>
<div>
  <ul class="nav nav-pills nav-sm mb-3">
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings')) ?>">الإعدادات العامة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" href="<?= Http::e(Http::url('/settings/tools')) ?>">أدوات الصيانة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings/audit')) ?>">سجل العمليات</a>
    </li>
  </ul>

  <?php if (($_GET['log_ok'] ?? null) === '1'): ?>
    <div class="alert alert-success" data-toast>تم تنفيذ العملية بنجاح.</div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="fw-bold">فاحص النظام</div>
          <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/settings/tools')) ?>">تحديث</a>
        </div>
        <div class="card-body">
          <div class="list-group">
            <?php foreach ($checks as $c): ?>
              <?php
                $ok = (bool)($c['ok'] ?? false);
                $label = (string)($c['label'] ?? '');
                $details = (string)($c['details'] ?? '');
              ?>
              <div class="list-group-item d-flex align-items-start justify-content-between gap-3">
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?= Http::e($label) ?></div>
                  <?php if ($details !== ''): ?>
                    <div class="text-muted small" style="word-break: break-word"><?= Http::e($details) ?></div>
                  <?php endif; ?>
                </div>
                <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= Http::e($lang === 'ar' ? ($ok ? 'سليم' : 'فشل') : ($ok ? 'OK' : 'FAIL')) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>

          <hr />
          <div class="fw-semibold mb-2">فحص الجداول الأساسية</div>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th><?= Http::e($lang === 'ar' ? 'الجدول' : 'Table') ?></th>
                  <th style="width:120px"><?= Http::e($lang === 'ar' ? 'الحالة' : 'Status') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tables as $name => $ok): ?>
                  <tr>
                    <td><?= Http::e((string)$name) ?></td>
                    <td>
                      <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>">
                        <?= Http::e($lang === 'ar' ? ($ok ? 'سليم' : 'مفقود') : ($ok ? 'OK' : 'MISSING')) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header">
          <div class="fw-bold"><?= Http::e($lang === 'ar' ? 'سجل الأخطاء' : 'Logs') ?></div>
        </div>
        <div class="card-body">
          <div class="vstack gap-3">
            <div>
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold">app.log</div>
                <div class="d-flex align-items-center gap-2">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/settings/tools/logs/download?which=app')) ?>">تحميل</a>
                  <form method="post" action="<?= Http::e(Http::url('/settings/tools/logs/clear')) ?>" data-loading data-confirm="هل تريد مسح سجل app.log؟">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="which" value="app" />
                    <button class="btn btn-outline-danger btn-sm" type="submit">مسح</button>
                  </form>
                </div>
              </div>
              <div class="text-muted small mb-2">
                <?php if ($appLogExists): ?>
                  <?= Http::e(($lang === 'ar' ? 'الحجم: ' : 'Size: ') . number_format($appLogSize) . ($lang === 'ar' ? ' بايت' : ' bytes')) ?>
                <?php else: ?>
                  <?= Http::e($lang === 'ar' ? 'غير موجود بعد (سيتم إنشاؤه عند أول خطأ)' : 'Not created yet (will be created on first error)') ?>
                <?php endif; ?>
              </div>
              <pre class="bg-body-tertiary border rounded p-2" style="max-height: 280px; overflow:auto; white-space: pre-wrap;"><?= Http::e($appLog !== '' ? $appLog : '—') ?></pre>
            </div>

            <div>
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold">php-error.log</div>
                <div class="d-flex align-items-center gap-2">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= Http::e(Http::url('/settings/tools/logs/download?which=php')) ?>">تحميل</a>
                  <form method="post" action="<?= Http::e(Http::url('/settings/tools/logs/clear')) ?>" data-loading data-confirm="هل تريد مسح سجل php-error.log؟">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="which" value="php" />
                    <button class="btn btn-outline-danger btn-sm" type="submit">مسح</button>
                  </form>
                </div>
              </div>
              <div class="text-muted small mb-2">
                <?php if ($phpLogExists): ?>
                  <?= Http::e(($lang === 'ar' ? 'الحجم: ' : 'Size: ') . number_format($phpLogSize) . ($lang === 'ar' ? ' بايت' : ' bytes')) ?>
                <?php else: ?>
                  <?= Http::e($lang === 'ar' ? 'غير موجود بعد' : 'Not created yet') ?>
                <?php endif; ?>
              </div>
              <pre class="bg-body-tertiary border rounded p-2" style="max-height: 280px; overflow:auto; white-space: pre-wrap;"><?= Http::e($phpLog !== '' ? $phpLog : '—') ?></pre>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Database Maintenance Section -->
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="fw-bold"><i class="bi bi-database-gear me-2"></i>صيانة قاعدة البيانات</div>
        </div>
        <div class="card-body">
          <?php if (($_GET['maint_ok'] ?? null) === '1'): ?>
            <div class="alert alert-success" data-toast>تم تنفيذ عمليات الصيانة بنجاح.</div>
          <?php elseif (($_GET['maint_err'] ?? null) === 'forbidden'): ?>
            <div class="alert alert-danger">غير مصرح لك بتنفيذ عمليات الصيانة.</div>
          <?php endif; ?>

          <p class="text-muted mb-3">تقوم عمليات الصيانة بتنظيف البيانات المؤقتة والسجلات القديمة للحفاظ على أداء النظام.</p>

          <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-4">
              <div class="border rounded p-3 h-100">
                <h6 class="fw-semibold mb-2"><i class="bi bi-shield-x me-2 text-warning"></i>محاولات الدخول الفاشلة</h6>
                <p class="text-muted small mb-0">حذف سجلات محاولات الدخول الفاشلة الأقدم من 24 ساعة</p>
              </div>
            </div>
            <div class="col-md-6 col-lg-4">
              <div class="border rounded p-3 h-100">
                <h6 class="fw-semibold mb-2"><i class="bi bi-speedometer me-2 text-info"></i>تحديد المعدل</h6>
                <p class="text-muted small mb-0">حذف سجلات تحديد المعدل الأقدم من ساعة</p>
              </div>
            </div>
            <div class="col-md-6 col-lg-4">
              <div class="border rounded p-3 h-100">
                <h6 class="fw-semibold mb-2"><i class="bi bi-bell me-2 text-primary"></i>الإشعارات القديمة</h6>
                <p class="text-muted small mb-0">حذف الإشعارات المقروءة الأقدم من 30 يوم</p>
              </div>
            </div>
          </div>

          <form method="post" action="<?= Http::e(Http::url('/settings/maintenance/run')) ?>" data-loading data-confirm="هل تريد تنفيذ عمليات الصيانة؟ هذا سيحذف البيانات المؤقتة القديمة.">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-play-circle me-2"></i>تشغيل عمليات الصيانة
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'أدوات الصيانة';
require __DIR__ . '/../shell.php';
