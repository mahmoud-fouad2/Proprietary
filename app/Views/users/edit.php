<?php
declare(strict_types=1);

use Zaco\Core\Http;

$u = $userRow ?? [];
ob_start();
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div class="text-muted">تعديل بيانات الحساب والصلاحيات.</div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/users')) ?>">
      <i class="bi bi-arrow-return-right me-1"></i>
      رجوع
    </a>
  </div>
</div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= Http::e(Http::url('/users/edit')) ?>" data-loading>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">الاسم</label>
          <input class="form-control" name="name" required value="<?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
        </div>
        <div class="col-md-6">
          <label class="form-label">البريد</label>
          <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
        </div>

        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="fw-semibold">الصلاحيات المخصصة لهذا المستخدم</div>
              <div class="text-muted small">إذا اخترت صلاحيات هنا، سيتم تطبيقها لهذا المستخدم. (الأدمن لديه كل شيء).</div>
            </div>
            <div class="card-body">
              <?php $custom = $customPerms ?? []; ?>
              <div class="row g-2">
                <?php foreach (($permLabels ?? []) as $key => $label): ?>
                  <div class="col-md-6">
                    <div class="form-check">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        id="perm_<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"
                        name="custom_perms[]"
                        value="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"
                        <?= in_array((string)$key, (array)$custom, true) ? 'checked' : '' ?>
                      />
                      <label class="form-check-label" for="perm_<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="fw-semibold">إخفاء التابات لهذا المستخدم</div>
              <div class="text-muted small">إخفاء التاب يعمل على واجهة القائمة فقط. (الأدمن مستثنى ويظهر له كل شيء).</div>
            </div>
            <div class="card-body">
              <?php $hidden = $hiddenTabs ?? []; ?>
              <div class="row g-2">
                <?php foreach (($navTabs ?? []) as $key => $label): ?>
                  <div class="col-md-6">
                    <div class="form-check">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        id="tab_<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"
                        name="hide_tabs[]"
                        value="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"
                        <?= in_array((string)$key, (array)$hidden, true) ? 'checked' : '' ?>
                      />
                      <label class="form-check-label" for="tab_<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">كلمة المرور (اتركه فارغًا للإبقاء عليها)</label>
          <input class="form-control" type="password" name="password" placeholder="••••••••" />
        </div>
        <div class="col-md-6">
          <label class="form-label">الدور</label>
          <select class="form-select" name="role">
            <?php $r = (string)($u['role'] ?? 'user'); $r = in_array($r, ['admin','user'], true) ? $r : 'user'; ?>
            <option value="user"  <?= $r === 'user'  ? 'selected' : '' ?>>مستخدم</option>
            <option value="admin" <?= $r === 'admin' ? 'selected' : '' ?>>أدمن</option>
          </select>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-check2-circle me-1"></i>
            حفظ التعديلات
          </button>
        </div>
      </div>
    </form>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'تعديل المستخدم';
require __DIR__ . '/../shell.php';
