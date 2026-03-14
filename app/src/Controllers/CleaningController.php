<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\Mailer;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class CleaningController extends BaseController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function today(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $this->ensureDefaultPlaces();

        $date = (new DateTimeImmutable('today'))->format('Y-m-d');
        $db = $this->db();

        $placesStmt = $db->query('SELECT id, place_name, is_active FROM cleaning_places ORDER BY id ASC');
        $places = $placesStmt->fetchAll();

        $checksStmt = $db->prepare('SELECT id, place_id, cleaner_user_id, check_date, checked_at FROM cleaning_checks WHERE check_date = ?');
        $checksStmt->execute([$date]);
        $allChecks = $checksStmt->fetchAll();

        $byPlace = [];
        foreach ($allChecks as $c) {
            $byPlace[(int)$c['place_id']][] = $c;
        }

        View::render('cleaning/today', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'places' => $places,
            'checksByPlace' => $byPlace,
            'today' => $date,
            'isAdmin' => $this->auth->can('manage_cleaning_places'),
        ]);
    }

    public function reportSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $comment = trim((string)($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 2000) {
            $comment = mb_substr($comment, 0, 2000);
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $db = $this->db();

        // Determine whether this is a new submission or an update (avoid spamming with ambiguous subjects)
        $existingId = 0;
        $existingSubmittedAt = '';
        try {
            $ex = $db->prepare('SELECT id, submitted_at FROM cleaning_daily_reports WHERE cleaner_user_id = ? AND report_date = ? LIMIT 1');
            $ex->execute([(int)$u['id'], $today]);
            $row = $ex->fetch();
            if (is_array($row)) {
                $existingId = (int)($row['id'] ?? 0);
                $existingSubmittedAt = (string)($row['submitted_at'] ?? '');
            }
        } catch (\Throwable) {
            $existingId = 0;
            $existingSubmittedAt = '';
        }

        $stmt = $db->prepare('INSERT INTO cleaning_daily_reports (cleaner_user_id, report_date, comment, submitted_at, created_at, updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE comment=VALUES(comment), submitted_at=VALUES(submitted_at), updated_at=VALUES(updated_at)');
        $stmt->execute([
            (int)$u['id'],
            $today,
            $comment !== '' ? $comment : null,
            $now,
            $now,
            $now,
        ]);

        // Send email notification (non-blocking)
        try {
            $config = $GLOBALS['config'] ?? null;
            $to = '';
            if ($config instanceof \Zaco\Core\Config) {
                $to = trim((string)$config->get('mail.cleaning.daily_to', 'f.waleed@bfi.sa'));
            } else {
                $to = 'f.waleed@bfi.sa';
            }

            // Resolve report id for a stable link
            $reportId = $existingId;
            if ($reportId <= 0) {
                $lid = (int)$db->lastInsertId();
                if ($lid > 0) {
                    $reportId = $lid;
                } else {
                    $ridStmt = $db->prepare('SELECT id FROM cleaning_daily_reports WHERE cleaner_user_id = ? AND report_date = ? LIMIT 1');
                    $ridStmt->execute([(int)$u['id'], $today]);
                    $ridRow = $ridStmt->fetch();
                    $reportId = (int)($ridRow['id'] ?? 0);
                }
            }

            // Counts for a more useful email
            $checksCount = 0;
            $activePlaces = 0;
            try {
                $cStmt = $db->prepare('SELECT COUNT(*) AS c FROM cleaning_checks WHERE cleaner_user_id = ? AND check_date = ?');
                $cStmt->execute([(int)$u['id'], $today]);
                $checksCount = (int)($cStmt->fetch()['c'] ?? 0);
            } catch (\Throwable) {
                $checksCount = 0;
            }
            try {
                $pStmt = $db->query('SELECT COUNT(*) AS c FROM cleaning_places WHERE is_active = 1');
                $activePlaces = (int)($pStmt->fetch()['c'] ?? 0);
            } catch (\Throwable) {
                $activePlaces = 0;
            }

            $isUpdate = $existingId > 0 && $existingSubmittedAt !== '';
            $subject = ($isUpdate ? 'تحديث ' : '') . 'تقرير النظافة اليومي - ' . $today;
            $printUrl = $reportId > 0 ? Mailer::absoluteUrl('/cleaning/reports/print?id=' . $reportId) : '';
            $listUrl = Mailer::absoluteUrl('/cleaning/reports?date=' . urlencode($today));

            $safeName = Http::e((string)($u['name'] ?? ''));
            $safeEmail = Http::e((string)($u['email'] ?? ''));
            $safeComment = $comment !== '' ? nl2br(Http::e($comment)) : '<span style="color:#6c757d">بدون تعليق.</span>';

            $html = ''
                . '<div style="font-family:Arial,Segoe UI,Tahoma,sans-serif; line-height:1.6; direction:rtl">'
                . '<h2 style="margin:0 0 10px 0">' . Http::e($subject) . '</h2>'
                . '<div style="color:#6c757d; margin-bottom:12px">التاريخ: <strong>' . Http::e($today) . '</strong></div>'
                . '<div style="margin-bottom:10px">الموظف: <strong>' . $safeName . '</strong>'
                . ($safeEmail !== '' ? (' &lt;' . $safeEmail . '&gt;') : '')
                . '</div>'
                . '<div style="margin-bottom:10px">عدد الصور/الزيارات المسجلة اليوم: <strong>' . (int)$checksCount . '</strong>'
                . ($activePlaces > 0 ? (' من <strong>' . (int)$activePlaces . '</strong> مكان') : '')
                . '</div>'
                . '<div style="margin:14px 0 8px 0"><strong>التعليق:</strong></div>'
                . '<div style="border:1px solid #e9ecef; border-radius:8px; padding:10px; background:#fafafa">' . $safeComment . '</div>'
                . '<div style="margin-top:14px">'
                . '<a href="' . Http::e($listUrl) . '">فتح تقارير الإدارة</a>'
                . ($printUrl !== '' ? (' | <a href="' . Http::e($printUrl) . '">فتح التقرير للطباعة</a>') : '')
                . '</div>'
                . '</div>';

            if ($to !== '') {
                Mailer::send($to, $subject, $html);
            }
        } catch (\Throwable $e) {
            error_log('Cleaning daily report email error: ' . $e->getMessage());
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Submit', 'cleaning_daily_reports', 'date=' . $today);
        Http::redirect('/cleaning?msg=sent');
    }

    public function reports(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $date = trim((string)($_GET['date'] ?? ''));
        if ($date === '') {
            $date = (new DateTimeImmutable('today'))->format('Y-m-d');
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT r.*, u.name AS cleaner_name, u.email AS cleaner_email FROM cleaning_daily_reports r JOIN users u ON u.id = r.cleaner_user_id WHERE r.report_date = ? ORDER BY r.submitted_at DESC');
        $stmt->execute([$date]);
        $reports = $stmt->fetchAll();

        $countsStmt = $db->prepare('SELECT cleaner_user_id, COUNT(*) AS c FROM cleaning_checks WHERE check_date = ? GROUP BY cleaner_user_id');
        $countsStmt->execute([$date]);
        $countsRows = $countsStmt->fetchAll();
        $counts = [];
        foreach ($countsRows as $r) {
            $counts[(int)$r['cleaner_user_id']] = (int)($r['c'] ?? 0);
        }

        View::render('cleaning/reports', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'date' => $date,
            'reports' => $reports,
            'photoCounts' => $counts,
        ]);
    }

    public function reportPrint(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/cleaning/reports');
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT r.*, u.name AS cleaner_name, u.email AS cleaner_email FROM cleaning_daily_reports r JOIN users u ON u.id = r.cleaner_user_id WHERE r.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        if (!$report) {
            Http::redirect('/cleaning/reports');
            return;
        }

        $checksStmt = $db->prepare('SELECT c.id, c.place_id, p.place_name, c.checked_at FROM cleaning_checks c JOIN cleaning_places p ON p.id = c.place_id WHERE c.cleaner_user_id = ? AND c.check_date = ? ORDER BY c.place_id ASC');
        $checksStmt->execute([(int)$report['cleaner_user_id'], (string)$report['report_date']]);
        $checks = $checksStmt->fetchAll();

        View::render('cleaning/report_print', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'report' => $report,
            'checks' => $checks,
        ]);
    }

    public function places(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $this->ensureDefaultPlaces();

        $db = $this->db();
        $stmt = $db->query('SELECT id, place_name, is_active FROM cleaning_places ORDER BY id ASC');
        $places = $stmt->fetchAll();

        View::render('cleaning/places', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'places' => $places,
        ]);
    }

    public function placesSave(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $db = $this->db();
        $rows = $_POST['places'] ?? [];
        if (!is_array($rows)) {
            Http::redirect('/cleaning/places');
        }

        // Enforce exactly 10 rows; allow rename + enable/disable.
        foreach ($rows as $id => $row) {
            $pid = (int)$id;
            if ($pid <= 0 || !is_array($row)) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                $name = 'مكان';
            }
            $active = isset($row['active']) ? 1 : 0;
            $stmt = $db->prepare('UPDATE cleaning_places SET place_name = ?, is_active = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$name, $active, (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'), $pid]);
        }

        Http::redirect('/cleaning/places');
    }

    public function checkSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) {
            Http::redirect('/login');
        }

        $placeId = (int)($_POST['place_id'] ?? 0);
        if ($placeId <= 0) {
            http_response_code(400);
            echo 'Invalid place';
            return;
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            http_response_code(400);
            echo 'Missing photo';
            return;
        }

        $file = $_FILES['photo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo 'Upload error';
            return;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > 6_000_000) {
            http_response_code(400);
            echo 'Invalid photo size';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            http_response_code(415);
            echo 'Invalid photo type';
            return;
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $now = new DateTimeImmutable('now');

        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'cleaning' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $today);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $safeName = 'u' . (int)$u['id'] . '_p' . $placeId . '_' . $now->format('His') . '.jpg';
        $target = $dir . DIRECTORY_SEPARATOR . $safeName;

        // Re-encode to JPG to strip metadata when GD is available.
        $saved = false;
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $raw = file_get_contents($tmp);
            if ($raw !== false) {
                $img = @imagecreatefromstring($raw);
                if ($img !== false) {
                    $saved = imagejpeg($img, $target, 82);
                    if (PHP_VERSION_ID < 80500) {
                        imagedestroy($img);
                    } else {
                        unset($img);
                    }
                }
            }
        }
        if (!$saved) {
            $saved = move_uploaded_file($tmp, $target);
        }

        if (!$saved) {
            http_response_code(500);
            echo 'Failed to save photo';
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO cleaning_checks (cleaner_user_id, place_id, check_date, checked_at, photo_path, notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE checked_at = VALUES(checked_at), photo_path = VALUES(photo_path)');
        $stmt->execute([
            (int)$u['id'],
            $placeId,
            $today,
            $now->format('Y-m-d H:i:s'),
            'cleaning/' . $today . '/' . $safeName,
            null,
        ]);

        // Return JSON when called via fetch
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            return;
        }

        Http::redirect('/cleaning');
    }

    public function photo(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $checkId = (int)($_GET['check_id'] ?? 0);
        if ($checkId <= 0) {
            http_response_code(400);
            echo 'Invalid';
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT id, cleaner_user_id, photo_path FROM cleaning_checks WHERE id = ? LIMIT 1');
        $stmt->execute([$checkId]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        // Cleaner can only view their own photo; admins can view all.
        if ((int)$row['cleaner_user_id'] !== (int)$u['id'] && !$this->auth->can('manage_cleaning_places')) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $rel = (string)$row['photo_path'];
            $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$row['photo_path']), '/');
            $file = $this->uploadsRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Missing';
            return;
        }

        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($file);
    }

    private function ensureDefaultPlaces(): void
    {
        $db = $this->db();
        $stmt = $db->query('SELECT COUNT(*) AS c FROM cleaning_places');
        $row = $stmt->fetch();
        $count = (int)($row['c'] ?? 0);
        if ($count > 0) {
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $names = [
            'مدخل المكتب',
            'الاستقبال',
            'الممر الرئيسي',
            'مكاتب الإدارة',
            'غرفة الاجتماعات',
            'دورات المياه',
            'المطبخ',
            'مخزن الأدوات',
            'منطقة الطباعة',
            'النوافذ والزجاج',
        ];

        $ins = $db->prepare('INSERT INTO cleaning_places (place_name,is_active,created_at,updated_at) VALUES (?,?,?,?)');
        foreach ($names as $n) {
            $ins->execute([$n, 1, $now, $now]);
        }
    }


}
