<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>
<div>
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1">إدارة أماكن النظافة</h1>
      <div class="text-muted">تعديل أسماء الأماكن (10 أماكن) وتفعيل/تعطيل.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/cleaning')) ?>">رجوع</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= Http::e(Http::url('/cleaning/places/save')) ?>" data-loading>
        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />

        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:80px;">#</th>
                <th>اسم المكان</th>
                <th style="width:120px;">نشط</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($places ?? []) as $p): ?>
                <?php $pid = (int)$p['id']; ?>
                <tr>
                  <td><?= $pid ?></td>
                  <td>
                    <input class="form-control" name="places[<?= $pid ?>][name]" value="<?= Http::e((string)$p['place_name']) ?>" />
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" role="switch" name="places[<?= $pid ?>][active]" <?= ((int)$p['is_active'] === 1) ? 'checked' : '' ?> />
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-3">
          <button class="btn btn-primary" type="submit">حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'إدارة الأماكن';
require __DIR__ . '/../shell.php';
