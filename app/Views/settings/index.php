<?php
declare(strict_types=1);

use Zaco\Core\Http;
use Zaco\Core\Avatar;

$settingsRow = $settings ?? [];
ob_start();
?>
<div class="settings-page">
  <!-- Settings Navigation -->
  <ul class="nav nav-pills nav-sm mb-3">
    <li class="nav-item">
      <a class="nav-link active" href="<?= Http::e(Http::url('/settings')) ?>">الإعدادات العامة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings/tools')) ?>">أدوات الصيانة</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= Http::e(Http::url('/settings/audit')) ?>">سجل العمليات</a>
    </li>
  </ul>

  <?php if (isset($success)): ?>
    <div class="alert alert-success" data-toast>تم حفظ الإعدادات بنجاح.</div>
  <?php endif; ?>
  <?php if (($_GET['org_ok'] ?? null) === 'created'): ?>
    <div class="alert alert-success" data-toast>تم إضافة المنظمة بنجاح.</div>
  <?php elseif (($_GET['org_ok'] ?? null) === 'updated'): ?>
    <div class="alert alert-success" data-toast>تم تحديث حالة المنظمة.</div>
  <?php endif; ?>
  <?php
    $orgErr = $_GET['org_err'] ?? null;
    if ($orgErr === 'empty'):
  ?>
    <div class="alert alert-danger">الرجاء إدخال اسم المنظمة.</div>
  <?php elseif ($orgErr === 'missing'): ?>
    <div class="alert alert-danger">جدول المنظمات غير موجود بقاعدة البيانات.</div>
  <?php elseif ($orgErr === 'fail'): ?>
    <div class="alert alert-danger">تعذر حفظ المنظمة. ربما الاسم مكرر.</div>
  <?php endif; ?>
  <?php if (($_GET['pw_ok'] ?? null)): ?>
    <div class="alert alert-success" data-toast>تم تغيير كلمة المرور بنجاح.</div>
  <?php endif; ?>
  <?php
    $pwErr = $_GET['pw_err'] ?? null;
    if ($pwErr === 'empty'):
  ?>
    <div class="alert alert-danger">الرجاء إدخال جميع حقول كلمة المرور.</div>
  <?php elseif ($pwErr === 'mismatch'): ?>
    <div class="alert alert-danger">كلمة المرور الجديدة وتأكيدها غير متطابقتين.</div>
  <?php elseif ($pwErr === 'short'): ?>
    <div class="alert alert-danger">كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.</div>
  <?php elseif ($pwErr === 'wrong'): ?>
    <div class="alert alert-danger">كلمة المرور الحالية غير صحيحة.</div>
  <?php endif; ?>

  <?php
    $structOk = $_GET['struct_ok'] ?? null;
    $structErr = $_GET['struct_err'] ?? null;
    $structSuccessMap = [
      'cat_created' => 'تم إضافة الفئة بنجاح.',
      'cat_deleted' => 'تم حذف الفئة بنجاح.',
      'sec_created' => 'تم إضافة القسم بنجاح.',
      'sec_deleted' => 'تم حذف القسم بنجاح.',
      'sub_created' => 'تم إضافة القسم الفرعي بنجاح.',
      'sub_deleted' => 'تم حذف القسم الفرعي بنجاح.',
    ];
    $structErrorMap = [
      'missing' => 'جداول الهيكلة غير موجودة. شغّل ملف migration: scripts/migrate_asset_structure.mysql.sql',
      'cat_empty' => 'الرجاء إدخال اسم الفئة.',
      'cat_fail' => 'تعذر حفظ الفئة. ربما الاسم مكرر.',
      'cat_del_fail' => 'تعذر حذف الفئة.',
      'sec_empty' => 'الرجاء إدخال اسم القسم.',
      'sec_fail' => 'تعذر حفظ القسم. ربما الاسم مكرر.',
      'sec_del_fail' => 'تعذر حذف القسم.',
      'sub_empty' => 'الرجاء اختيار القسم وإدخال اسم القسم الفرعي.',
      'sub_fail' => 'تعذر حفظ القسم الفرعي. ربما الاسم مكرر داخل نفس القسم.',
      'sub_del_fail' => 'تعذر حذف القسم الفرعي.',
    ];
  ?>
  <?php if (isset($structOk) && isset($structSuccessMap[$structOk])): ?>
    <div class="alert alert-success" data-toast><?= Http::e((string)$structSuccessMap[$structOk]) ?></div>
  <?php endif; ?>
  <?php if (isset($structErr) && isset($structErrorMap[$structErr])): ?>
    <div class="alert alert-danger"><?= Http::e((string)$structErrorMap[$structErr]) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Organization Info Section -->
    <div class="col-12 col-xl-6">
      <div class="card settings-table-card h-100">
        <div class="card-header bg-light">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-building text-primary"></i>
            <span class="fw-bold">بيانات المنظمة</span>
          </div>
        </div>
        <div class="card-body">
          <form method="post" action="<?= Http::e(Http::url('/settings/save')) ?>" class="row g-3" data-loading enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

            <div class="col-12">
              <label class="form-label fw-semibold" for="orgNameAr">اسم المنظمة (عربي)</label>
              <input class="form-control" id="orgNameAr" name="org_name" value="<?= htmlspecialchars((string)($settingsRow['org_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="orgNameEn">اسم المنظمة (إنجليزي)</label>
              <input class="form-control" id="orgNameEn" name="org_name_en" value="<?= htmlspecialchars((string)($settingsRow['org_name_en'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="orgPhone">الهاتف</label>
              <input class="form-control" id="orgPhone" name="org_phone" value="<?= htmlspecialchars((string)($settingsRow['org_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="orgEmail">البريد الإلكتروني</label>
              <input class="form-control" id="orgEmail" type="email" name="org_email" value="<?= htmlspecialchars((string)($settingsRow['org_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="orgAddress">العنوان</label>
              <input class="form-control" id="orgAddress" name="org_address" value="<?= htmlspecialchars((string)($settingsRow['org_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="lowStock">تنبيه المخزون المنخفض (عدد)</label>
              <input class="form-control" id="lowStock" type="number" name="low_stock_alert" min="0" value="<?= htmlspecialchars((string)($settingsRow['low_stock_alert'] ?? '5'), ENT_QUOTES, 'UTF-8') ?>" />
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold" for="appLogo">شعار النظام</label>
              <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light">
                <img src="<?= Http::e(Http::url('/branding/logo')) ?>" alt="Logo" style="height:44px; width:auto" />
                <input class="form-control" id="appLogo" type="file" name="app_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" />
              </div>
              <div class="form-text">الصيغ المدعومة: PNG/JPG/SVG/WebP. الحد الأقصى: 2MB.</div>
            </div>

            <div class="col-12">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2 me-1"></i>
                حفظ التغييرات
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Password Change Section -->
    <div class="col-12 col-xl-6">
      <div class="card settings-table-card h-100">
        <div class="card-header bg-light">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-key text-warning"></i>
            <span class="fw-bold">تغيير كلمة المرور</span>
          </div>
        </div>
        <div class="card-body">
          <form method="post" action="<?= Http::e(Http::url('/settings/password')) ?>" class="row g-3" data-loading>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

            <div class="col-12">
              <label class="form-label fw-semibold" for="pwCurrent">كلمة المرور الحالية</label>
              <input class="form-control" id="pwCurrent" type="password" name="current_password" required />
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="pwNew">كلمة المرور الجديدة</label>
              <input class="form-control" id="pwNew" type="password" name="new_password" required minlength="8" />
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="pwConfirm">تأكيد كلمة المرور الجديدة</label>
              <input class="form-control" id="pwConfirm" type="password" name="confirm_password" required minlength="8" />
            </div>
            <div class="col-12">
              <button class="btn btn-warning" type="submit">
                <i class="bi bi-shield-lock me-1"></i>
                تغيير كلمة المرور
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Asset Structure Section -->
    <div class="col-12">
      <div class="card settings-table-card">
        <div class="card-header bg-light">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-diagram-3 text-info"></i>
            <span class="fw-bold">هيكلة الأصول (فئات / أقسام / أقسام فرعية)</span>
          </div>
        </div>
        <div class="card-body">
          <?php if (empty($assetStructureEnabled)): ?>
            <div class="alert alert-danger mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              الميزة غير مفعّلة لأن جداول الهيكلة غير موجودة. شغّل ملف migration: <strong>scripts/migrate_asset_structure.mysql.sql</strong>
            </div>
          <?php else: ?>
            <div class="text-muted mb-3">
              <i class="bi bi-info-circle me-1"></i>
              استخدمها لتوحيد الفئات والأقسام داخل فورم إضافة الأصول وتمكين نقل الأصول بين الأقسام.
            </div>

            <div class="row g-4">
              <!-- Categories -->
              <div class="col-12 col-lg-6">
                <div class="border rounded p-3 bg-light">
                  <div class="fw-semibold mb-3 text-primary">
                    <i class="bi bi-collection me-1"></i>
                    الفئات
                  </div>
                  <form method="post" action="<?= Http::e(Http::url('/settings/asset-categories/create')) ?>" class="row g-2 align-items-end mb-3" data-loading>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                    <div class="col-12 col-md-8">
                      <label class="form-label" for="catName">اسم الفئة</label>
                      <input class="form-control" id="catName" name="name" required placeholder="مثال: أجهزة - أثاث" />
                    </div>
                    <div class="col-12 col-md-4">
                      <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-plus-lg me-1"></i>
                        إضافة
                      </button>
                    </div>
                  </form>

                  <?php if (!empty($assetCategories)): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th class="text-center" style="width:60px">#</th>
                            <th>الاسم</th>
                            <th class="text-center" style="width:80px">حذف</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (($assetCategories ?? []) as $c): ?>
                            <tr>
                              <td class="text-center text-muted"><?= (int)($c['id'] ?? 0) ?></td>
                              <td><?= Http::e((string)($c['name'] ?? '')) ?></td>
                              <td class="text-center">
                                <form method="post" action="<?= Http::e(Http::url('/settings/asset-categories/delete')) ?>" data-loading data-confirm="هل تريد حذف هذه الفئة؟ سيتم إزالة ربطها من الأصول.">
                                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                                  <input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>" />
                                  <button class="btn btn-outline-danger btn-sm" type="submit">
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="text-muted text-center py-2">لا توجد فئات بعد.</div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Sections & Subsections -->
              <div class="col-12 col-lg-6">
                <div class="border rounded p-3 bg-light mb-3">
                  <div class="fw-semibold mb-3 text-success">
                    <i class="bi bi-grid me-1"></i>
                    الأقسام
                  </div>
                  <form method="post" action="<?= Http::e(Http::url('/settings/asset-sections/create')) ?>" class="row g-2 align-items-end mb-3" data-loading>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                    <div class="col-12 col-md-8">
                      <label class="form-label" for="secName">اسم القسم</label>
                      <input class="form-control" id="secName" name="name" required placeholder="مثال: تقنية المعلومات" />
                    </div>
                    <div class="col-12 col-md-4">
                      <button class="btn btn-success w-100" type="submit">
                        <i class="bi bi-plus-lg me-1"></i>
                        إضافة
                      </button>
                    </div>
                  </form>

                  <?php if (!empty($assetSections)): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th class="text-center" style="width:60px">#</th>
                            <th>الاسم</th>
                            <th class="text-center" style="width:80px">حذف</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (($assetSections ?? []) as $sec): ?>
                            <tr>
                              <td class="text-center text-muted"><?= (int)($sec['id'] ?? 0) ?></td>
                              <td><?= Http::e((string)($sec['name'] ?? '')) ?></td>
                              <td class="text-center">
                                <form method="post" action="<?= Http::e(Http::url('/settings/asset-sections/delete')) ?>" data-loading data-confirm="هل تريد حذف هذا القسم؟ سيتم حذف الأقسام الفرعية التابعة وإزالة ربطه من الأصول.">
                                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                                  <input type="hidden" name="id" value="<?= (int)($sec['id'] ?? 0) ?>" />
                                  <button class="btn btn-outline-danger btn-sm" type="submit">
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="text-muted text-center py-2">لا توجد أقسام بعد.</div>
                  <?php endif; ?>
                </div>

                <div class="border rounded p-3 bg-light">
                  <div class="fw-semibold mb-3 text-secondary">
                    <i class="bi bi-grid-3x3-gap me-1"></i>
                    الأقسام الفرعية
                  </div>
                  <form method="post" action="<?= Http::e(Http::url('/settings/asset-subsections/create')) ?>" class="row g-2 align-items-end mb-3" data-loading>
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

                    <div class="col-12 col-md-5">
                      <label class="form-label" for="subSection">القسم</label>
                      <select class="form-select" id="subSection" name="section_id" required>
                        <option value="">اختر القسم</option>
                        <?php foreach (($assetSections ?? []) as $sec): ?>
                          <option value="<?= (int)($sec['id'] ?? 0) ?>"><?= Http::e((string)($sec['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-12 col-md-5">
                      <label class="form-label" for="subName">اسم القسم الفرعي</label>
                      <input class="form-control" id="subName" name="name" required placeholder="مثال: مخزن 1" />
                    </div>

                    <div class="col-12 col-md-2">
                      <button class="btn btn-secondary w-100" type="submit">
                        <i class="bi bi-plus-lg"></i>
                      </button>
                    </div>
                  </form>

                  <?php if (!empty($assetSubsections)): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th class="text-center" style="width:60px">#</th>
                            <th>القسم</th>
                            <th>الاسم</th>
                            <th class="text-center" style="width:80px">حذف</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach (($assetSubsections ?? []) as $ss): ?>
                            <tr>
                              <td class="text-center text-muted"><?= (int)($ss['id'] ?? 0) ?></td>
                              <td><?= Http::e((string)($ss['section_name'] ?? '')) ?></td>
                              <td><?= Http::e((string)($ss['name'] ?? '')) ?></td>
                              <td class="text-center">
                                <form method="post" action="<?= Http::e(Http::url('/settings/asset-subsections/delete')) ?>" data-loading data-confirm="هل تريد حذف هذا القسم الفرعي؟ سيتم إزالة ربطه من الأصول.">
                                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                                  <input type="hidden" name="id" value="<?= (int)($ss['id'] ?? 0) ?>" />
                                  <button class="btn btn-outline-danger btn-sm" type="submit">
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="text-muted text-center py-2">لا توجد أقسام فرعية بعد.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($orgsEnabled)): ?>
      <!-- Organizations Section -->
      <div class="col-12">
        <div class="card settings-table-card">
          <div class="card-header bg-light">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-buildings text-success"></i>
              <span class="fw-bold">المنظمات</span>
            </div>
          </div>
          <div class="card-body">
            <form method="post" action="<?= Http::e(Http::url('/settings/orgs/create')) ?>" class="row g-2 align-items-end mb-3" data-loading>
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
              <div class="col-12 col-md-7">
                <label class="form-label fw-semibold" for="orgName">اسم المنظمة</label>
                <input class="form-control" id="orgName" name="name" placeholder="مثال: مؤسسة ..." required />
              </div>
              <div class="col-12 col-md-3">
                <div class="form-check mt-4">
                  <input type="hidden" name="is_active" value="0" />
                  <input class="form-check-input" type="checkbox" id="orgActive" name="is_active" value="1" checked />
                  <label class="form-check-label" for="orgActive">منظمة نشطة</label>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <button class="btn btn-success w-100" type="submit">
                  <i class="bi bi-plus-lg me-1"></i>
                  إضافة
                </button>
              </div>
            </form>

            <?php if (!empty($orgs)): ?>
              <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center" style="width:60px">#</th>
                      <th>الاسم</th>
                      <th class="text-center" style="width:100px">الحالة</th>
                      <th class="text-center" style="width:100px">تحكم</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (($orgs ?? []) as $o): ?>
                      <?php $active = ((int)($o['is_active'] ?? 0) === 1); ?>
                      <tr>
                        <td class="text-center text-muted"><?= (int)($o['id'] ?? 0) ?></td>
                        <td>
                          <span class="d-inline-flex align-items-center gap-2">
                            <?= Avatar::html((string)($o['name'] ?? ''), (string)($o['id'] ?? ($o['name'] ?? '')), 'zaco-avatar-xs', 'border') ?>
                            <span><?= Http::e((string)($o['name'] ?? '')) ?></span>
                          </span>
                        </td>
                        <td class="text-center">
                          <span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $active ? 'نشطة' : 'موقوفة' ?></span>
                        </td>
                        <td class="text-center">
                          <form method="post" action="<?= Http::e(Http::url('/settings/orgs/toggle')) ?>" data-loading>
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" name="id" value="<?= (int)($o['id'] ?? 0) ?>" />
                            <input type="hidden" name="is_active" value="<?= $active ? '0' : '1' ?>" />
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                              <?= $active ? '<i class="bi bi-pause"></i> تعطيل' : '<i class="bi bi-play"></i> تفعيل' ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-muted text-center py-3">لا توجد منظمات بعد.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'الإعدادات';
require __DIR__ . '/../shell.php';
