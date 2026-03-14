<?php
declare(strict_types=1);

use Zaco\Core\Http;

ob_start();
?>

<?php
$m = $metrics ?? [];
$c = $charts ?? [];
$canAudits = !empty($canSeeAudits);

function dash_rows_to_map(array $rows): array {
  $out = [];
  foreach ($rows as $r) {
    $k = (string)($r['k'] ?? '');
    $v = (int)($r['c'] ?? 0);
    if ($k === '') continue;
    $out[$k] = $v;
  }
  arsort($out);
  return $out;
}

$assetsByCondition = dash_rows_to_map((array)($c['assetsByCondition'] ?? []));
$custodyByStatus = dash_rows_to_map((array)($c['custodyByStatus'] ?? []));
$cleaningTrend = (array)($c['cleaningLast7Days'] ?? []);

// Prepare chart data as JSON
$assetsChartLabels = json_encode(array_keys($assetsByCondition), JSON_UNESCAPED_UNICODE);
$assetsChartData = json_encode(array_values($assetsByCondition));

$custodyChartLabels = json_encode(array_keys($custodyByStatus), JSON_UNESCAPED_UNICODE);
$custodyChartData = json_encode(array_values($custodyByStatus));

$cleaningLabels = [];
$cleaningData = [];
foreach ($cleaningTrend as $r) {
  $d = $r['d'] ?? '';
  $cleaningLabels[] = date('m/d', strtotime($d));
  $cleaningData[] = (int)($r['c'] ?? 0);
}
$cleaningChartLabels = json_encode($cleaningLabels, JSON_UNESCAPED_UNICODE);
$cleaningChartData = json_encode($cleaningData);
?>

<div class="row g-3">
      <div class="col-12 col-md-6 col-xl-3">
        <div class="small-box text-bg-primary mb-0">
          <div class="inner">
            <h3><?= (int)($m['assets'] ?? 0) ?></h3>
            <p>الأصول</p>
          </div>
          <div class="icon"><i class="bi bi-box-seam"></i></div>
          <a href="<?= Http::e(Http::url('/inventory')) ?>" class="small-box-footer">عرض <i class="bi bi-arrow-left"></i></a>
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-3">
        <div class="small-box text-bg-success mb-0">
          <div class="inner">
            <h3><?= (int)($m['employees'] ?? 0) ?></h3>
            <p>الموظفون</p>
          </div>
          <div class="icon"><i class="bi bi-person-badge"></i></div>
          <a href="<?= Http::e(Http::url('/employees')) ?>" class="small-box-footer">عرض <i class="bi bi-arrow-left"></i></a>
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-3">
        <div class="small-box text-bg-warning mb-0">
          <div class="inner">
            <h3><?= (int)($m['custodyActive'] ?? 0) ?></h3>
            <p>عُهد فعّالة</p>
          </div>
          <div class="icon"><i class="bi bi-shield-check"></i></div>
          <a href="<?= Http::e(Http::url('/custody')) ?>" class="small-box-footer">عرض <i class="bi bi-arrow-left"></i></a>
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-3">
        <div class="small-box text-bg-info mb-0">
          <div class="inner">
            <h3><?= (int)($m['cleaningTodayDone'] ?? 0) ?> / <?= (int)($m['cleaningPlaces'] ?? 0) ?></h3>
            <p>النظافة (اليوم)</p>
          </div>
          <div class="icon"><i class="bi bi-camera"></i></div>
          <a href="<?= Http::e(Http::url('/cleaning')) ?>" class="small-box-footer">فتح <i class="bi bi-arrow-left"></i></a>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card">
          <div class="card-header">
            <div class="fw-bold">ملخص</div>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-5">قيمة الأصول</dt>
              <dd class="col-sm-7"><?= number_format((float)($m['assetsValue'] ?? 0), 2) ?> ر.س</dd>

              <dt class="col-sm-5">البرامج</dt>
              <dd class="col-sm-7"><?= (int)($m['software'] ?? 0) ?></dd>

              <dt class="col-sm-5">المستخدمون</dt>
              <dd class="col-sm-7"><?= (int)($m['users'] ?? 0) ?></dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card">
          <div class="card-header">
            <div class="fw-bold">مستخدمك الحالي</div>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">الاسم</dt>
              <dd class="col-sm-8"><?= htmlspecialchars((string)($user['name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

              <dt class="col-sm-4">البريد</dt>
              <dd class="col-sm-8"><?= htmlspecialchars((string)($user['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

              <dt class="col-sm-4">الدور</dt>
              <dd class="col-sm-8"><?= htmlspecialchars((string)($user['role'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-header">
            <div class="fw-bold"><i class="bi bi-pie-chart me-1"></i> الأصول حسب الحالة</div>
          </div>
          <div class="card-body d-flex flex-column justify-content-center">
            <?php if (empty($assetsByCondition)): ?>
              <div class="empty-state py-4">
                <i class="bi bi-pie-chart text-muted mb-2" style="font-size: 2rem;"></i>
                <div class="empty-state-text text-muted">لا توجد بيانات مخطط الأصول</div>
              </div>
            <?php else: ?>
              <div class="zaco-chart-container">
                <canvas id="assetsConditionChart"></canvas>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-header">
            <div class="fw-bold"><i class="bi bi-pie-chart me-1"></i> العهد حسب الحالة</div>
          </div>
          <div class="card-body d-flex flex-column justify-content-center">
            <?php if (empty($custodyByStatus)): ?>
              <div class="empty-state py-4">
                <i class="bi bi-pie-chart text-muted mb-2" style="font-size: 2rem;"></i>
                <div class="empty-state-text text-muted">لا توجد بيانات مخطط العهد</div>
              </div>
            <?php else: ?>
              <div class="zaco-chart-container">
                <canvas id="custodyStatusChart"></canvas>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-header">
            <div class="fw-bold"><i class="bi bi-graph-up me-1"></i> ترند النظافة (آخر 7 أيام)</div>
          </div>
          <div class="card-body d-flex flex-column justify-content-center">
            <?php if (empty($cleaningTrend)): ?>
              <div class="empty-state py-4">
                <i class="bi bi-graph-up text-muted mb-2" style="font-size: 2rem;"></i>
                <div class="empty-state-text text-muted">لا توجد بيانات مخطط النظافة</div>
              </div>
            <?php else: ?>
              <div class="zaco-chart-container">
                <canvas id="cleaningTrendChart"></canvas>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

<!-- Chart.js (local) -->
<?php $nonce = (string)($GLOBALS['cspNonce'] ?? ''); ?>
<script src="<?= Http::e(Http::asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script<?= $nonce !== '' ? ' nonce="' . Http::e($nonce) . '"' : '' ?>>
document.addEventListener('DOMContentLoaded', function() {
  // Chart color palette
  const chartColors = [
    'rgba(54, 162, 235, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(255, 206, 86, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(153, 102, 255, 0.8)',
    'rgba(255, 159, 64, 0.8)'
  ];
  
  const borderColors = [
    'rgba(54, 162, 235, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)'
  ];

  // Common chart options
  const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        rtl: true,
        labels: {
          padding: 15,
          usePointStyle: true,
          font: { size: 11 }
        }
      }
    }
  };

  // Assets by Condition - Doughnut Chart
  const assetsCtx = document.getElementById('assetsConditionChart');
  if (assetsCtx) {
    new Chart(assetsCtx, {
      type: 'doughnut',
      data: {
        labels: <?= $assetsChartLabels ?>,
        datasets: [{
          data: <?= $assetsChartData ?>,
          backgroundColor: chartColors,
          borderColor: borderColors,
          borderWidth: 2
        }]
      },
      options: {
        ...defaultOptions,
        cutout: '50%'
      }
    });
  }

  // Custody by Status - Doughnut Chart
  const custodyCtx = document.getElementById('custodyStatusChart');
  if (custodyCtx) {
    new Chart(custodyCtx, {
      type: 'doughnut',
      data: {
        labels: <?= $custodyChartLabels ?>,
        datasets: [{
          data: <?= $custodyChartData ?>,
          backgroundColor: chartColors.slice().reverse(),
          borderColor: borderColors.slice().reverse(),
          borderWidth: 2
        }]
      },
      options: {
        ...defaultOptions,
        cutout: '50%'
      }
    });
  }

  // Cleaning Trend - Line Chart
  const cleaningCtx = document.getElementById('cleaningTrendChart');
  if (cleaningCtx) {
    new Chart(cleaningCtx, {
      type: 'line',
      data: {
        labels: <?= $cleaningChartLabels ?>,
        datasets: [{
          label: 'عدد التنظيفات',
          data: <?= $cleaningChartData ?>,
          fill: true,
          backgroundColor: 'rgba(54, 162, 235, 0.2)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 2,
          tension: 0.3,
          pointBackgroundColor: 'rgba(54, 162, 235, 1)',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        ...defaultOptions,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    });
  }
});
</script>
<?php
$content = ob_get_clean();
$title = 'لوحة التحكم';
require __DIR__ . '/../shell.php';
