<?php
declare(strict_types=1);

namespace Zaco\Core;

use DateInterval;
use DateTimeImmutable;
use PDO;

final class CleaningWeeklyReport
{
    private static function storageMarkerFile(): string
    {
        $root = rtrim((string)dirname(__DIR__, 3), '/\\');
        $storage = $root . DIRECTORY_SEPARATOR . 'storage';
        return $storage . DIRECTORY_SEPARATOR . 'cleaning_weekly_last_sent.json';
    }

    /**
     * Auto-send weekly report on Mondays for admins (no cron required).
     * Safe: idempotent marker prevents duplicates.
     */
    public static function maybeAutoSend(PDO $db): void
    {
        // Only run in web context and only on Mondays.
        try {
            $today = new DateTimeImmutable('today');
            if ((int)$today->format('N') !== 1) {
                return;
            }

            $start = new DateTimeImmutable('monday last week');
            $end = new DateTimeImmutable('sunday last week');
            $startDate = $start->format('Y-m-d');
            $endDate = $end->format('Y-m-d');

            $markerFile = self::storageMarkerFile();
            if (is_file($markerFile)) {
                $prev = json_decode((string)@file_get_contents($markerFile), true);
                if (is_array($prev) && ($prev['start'] ?? '') === $startDate && ($prev['end'] ?? '') === $endDate) {
                    return;
                }
            }

            $config = $GLOBALS['config'] ?? null;
            if (!$config instanceof Config) {
                return;
            }

            $to = trim((string)$config->get('mail.cleaning.weekly_to', 'm.fouad@zaco.sa'));
            if ($to === '') {
                return;
            }

            if (!Mailer::enabled()) {
                return;
            }

            $ok = self::sendPreviousWeek($db, $to);
            if ($ok) {
                @file_put_contents($markerFile, json_encode([
                    'start' => $startDate,
                    'end' => $endDate,
                    'sent_at' => (new DateTimeImmutable('now'))->format('c'),
                    'sent_by' => 'auto',
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
            error_log('CleaningWeeklyReport auto error: ' . $e->getMessage());
        }
    }

    public static function sendPreviousWeek(PDO $db, string $to): bool
    {
        $start = new DateTimeImmutable('monday last week');
        $end = new DateTimeImmutable('sunday last week');
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        $activePlaces = 0;
        try {
            $p = $db->query('SELECT COUNT(*) AS c FROM cleaning_places WHERE is_active = 1');
            $activePlaces = (int)($p->fetch()['c'] ?? 0);
        } catch (\Throwable) {
            $activePlaces = 0;
        }

        $checksByDay = [];
        try {
            $stmt = $db->prepare('SELECT check_date AS d, COUNT(*) AS c, COUNT(DISTINCT cleaner_user_id) AS cleaners FROM cleaning_checks WHERE check_date BETWEEN ? AND ? GROUP BY check_date ORDER BY check_date ASC');
            $stmt->execute([$startDate, $endDate]);
            foreach (($stmt->fetchAll() ?: []) as $r) {
                $checksByDay[(string)$r['d']] = [
                    'checks' => (int)($r['c'] ?? 0),
                    'cleaners' => (int)($r['cleaners'] ?? 0),
                ];
            }
        } catch (\Throwable) {
            $checksByDay = [];
        }

        $reportsByDay = [];
        try {
            $stmt = $db->prepare('SELECT report_date AS d, COUNT(*) AS c FROM cleaning_daily_reports WHERE report_date BETWEEN ? AND ? GROUP BY report_date ORDER BY report_date ASC');
            $stmt->execute([$startDate, $endDate]);
            foreach (($stmt->fetchAll() ?: []) as $r) {
                $reportsByDay[(string)$r['d']] = (int)($r['c'] ?? 0);
            }
        } catch (\Throwable) {
            $reportsByDay = [];
        }

        $dates = [];
        for ($d = $start; $d <= $end; $d = $d->add(new DateInterval('P1D'))) {
            $dates[] = $d->format('Y-m-d');
        }

        $totalChecks = 0;
        $totalReports = 0;
        foreach ($dates as $d) {
            $totalChecks += (int)($checksByDay[$d]['checks'] ?? 0);
            $totalReports += (int)($reportsByDay[$d] ?? 0);
        }

        $subject = 'تقرير النظافة الأسبوعي (' . $startDate . ' - ' . $endDate . ')';
        $listUrl = Mailer::absoluteUrl('/cleaning/reports?date=' . urlencode($endDate));

        $rowsHtml = '';
        foreach ($dates as $d) {
            $c = (int)($checksByDay[$d]['checks'] ?? 0);
            $cl = (int)($checksByDay[$d]['cleaners'] ?? 0);
            $r = (int)($reportsByDay[$d] ?? 0);
            $rowsHtml .= '<tr>'
                . '<td style="padding:8px; border:1px solid #e9ecef">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px; border:1px solid #e9ecef; text-align:center">' . $c . '</td>'
                . '<td style="padding:8px; border:1px solid #e9ecef; text-align:center">' . $cl . '</td>'
                . '<td style="padding:8px; border:1px solid #e9ecef; text-align:center">' . $r . '</td>'
                . '</tr>';
        }

        $html = ''
            . '<div style="font-family:Arial,Segoe UI,Tahoma,sans-serif; line-height:1.6; direction:rtl">'
            . '<h2 style="margin:0 0 10px 0">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div style="color:#6c757d; margin-bottom:12px">الفترة: <strong>' . htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') . '</strong> إلى <strong>' . htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') . '</strong></div>'
            . '<div style="margin-bottom:10px">إجمالي الزيارات/الصور: <strong>' . (int)$totalChecks . '</strong></div>'
            . '<div style="margin-bottom:10px">إجمالي عدد التقارير المرسلة: <strong>' . (int)$totalReports . '</strong></div>'
            . '<div style="margin-bottom:10px">عدد الأماكن النشطة (مرجع): <strong>' . (int)$activePlaces . '</strong></div>'
            . '<div style="margin:14px 0 8px 0"><strong>تفاصيل يومية</strong></div>'
            . '<table style="border-collapse:collapse; width:100%; font-size:14px">'
            . '<thead>'
            . '<tr>'
            . '<th style="padding:8px; border:1px solid #e9ecef; background:#f8f9fa; text-align:right">التاريخ</th>'
            . '<th style="padding:8px; border:1px solid #e9ecef; background:#f8f9fa">عدد الزيارات</th>'
            . '<th style="padding:8px; border:1px solid #e9ecef; background:#f8f9fa">عدد المنفذين</th>'
            . '<th style="padding:8px; border:1px solid #e9ecef; background:#f8f9fa">عدد التقارير</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>'
            . $rowsHtml
            . '</tbody>'
            . '</table>'
            . '<div style="margin-top:14px">رابط تقارير الإدارة: <a href="' . htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') . '">فتح</a></div>'
            . '</div>';

        return Mailer::send($to, $subject, $html);
    }
}
