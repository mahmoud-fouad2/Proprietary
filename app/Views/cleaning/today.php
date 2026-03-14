<?php
declare(strict_types=1);

use Zaco\Core\Http;

/** @var array<int,array{id:int,place_name:string,is_active:int}> $places */
/** @var array<int,list<array<string,mixed>>> $checksByPlace */

ob_start();
?>
<div data-cleaning>
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1">النظافة</h1>
      <div class="text-muted">Checklist يومي بتاريخ: <?= Http::e((string)$today) ?></div>
    </div>
    <?php if (!empty($isAdmin)): ?>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/cleaning/reports')) ?>">تقارير الإدارة</a>
        <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/cleaning/places')) ?>">إدارة الأماكن</a>
      </div>
    <?php endif; ?>
  </div>

  <?php $msg = $_GET['msg'] ?? null; ?>
  <?php if ($msg === 'sent'): ?>
    <div class="alert alert-success" data-toast>تم إرسال التقرير اليومي للإدارة.</div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">
      <div class="fw-bold">اليوم</div>
      <div class="text-muted small">اضغط “تصوير” لكل مكان ثم التقط صورة مباشرة بالكاميرا.</div>
    </div>
    <div class="card-body">
      <div class="cleaning">
        <div class="cleaning__list list-group">
          <?php foreach (($places ?? []) as $p): ?>
            <?php if ((int)$p['is_active'] !== 1) continue; ?>
            <?php $pid = (int)$p['id']; ?>
            <?php $checks = $checksByPlace[$pid] ?? []; ?>

            <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div style="min-width: 220px">
                <div class="fw-semibold"><?= Http::e((string)$p['place_name']) ?></div>
                <div class="text-muted small">
                  <?php if (!empty($checks)): ?>
                    <span class="badge text-bg-success">تم</span>
                    <?php $last = $checks[0]; ?>
                    <span class="ms-2">آخر تنفيذ: <?= Http::e((string)($last['checked_at'] ?? '—')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">لم يتم</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex gap-2">
                <button
                  class="btn btn-primary"
                  type="button"
                  data-capture
                  data-place-id="<?= (int)$pid ?>"
                  data-csrf="<?= Http::e((string)$csrf) ?>"
                  data-endpoint="<?= Http::e(Http::url('/cleaning/check')) ?>"
                >
                  تصوير
                </button>

                <?php if (!empty($checks)): ?>
                  <?php $cid = (int)$checks[0]['id']; ?>
                  <a class="btn btn-outline-secondary" href="<?= Http::e(Http::url('/cleaning/photo?check_id=' . $cid)) ?>" target="_blank" rel="noopener">عرض الصورة</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="cameraPanel" hidden class="mt-3">
          <div class="card border-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div class="fw-bold">تصوير مباشر</div>
              <button class="btn btn-outline-secondary btn-sm" type="button" id="cameraClose">إغلاق</button>
            </div>
            <div class="card-body">
              <video id="cameraVideo" class="w-100 rounded" autoplay playsinline muted></video>
              <canvas id="cameraCanvas" class="w-100 rounded" hidden></canvas>

              <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="button" id="cameraShot">التقاط</button>
                <button class="btn btn-outline-secondary" type="button" id="cameraRetake" hidden>إعادة</button>
                <button class="btn btn-primary" type="button" id="cameraSend" hidden>إرسال</button>
              </div>

              <div class="alert alert-info mt-3 mb-0">
                ملاحظة: لا يوجد رفع ملفات — الالتقاط يتم من الكاميرا مباشرة.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="fw-bold">تعليق الموظف</div>
    </div>
    <div class="card-body">
      <form method="post" action="<?= Http::e(Http::url('/cleaning/report')) ?>" data-loading>
        <input type="hidden" name="_csrf" value="<?= Http::e((string)$csrf) ?>" />

        <div class="mb-3">
          <label class="form-label" for="comment">التعليق</label>
          <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="اكتب ملاحظتك عن اليوم..." style="resize:vertical"></textarea>
        </div>

        <button class="btn btn-primary" type="submit">إرسال التقرير اليومي</button>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = 'النظافة';
require __DIR__ . '/../shell.php';
