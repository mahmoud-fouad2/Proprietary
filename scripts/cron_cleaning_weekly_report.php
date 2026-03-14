<?php
declare(strict_types=1);

use DateTimeImmutable;
use Zaco\Core\CleaningWeeklyReport;
use Zaco\Core\Mailer;

$boot = require __DIR__ . '/bootstrap.php';
/** @var \PDO $db */
$db = $boot['db'];
/** @var \Zaco\Core\Config $config */
$config = $boot['config'];

$args = $argv ?? [];
$force = in_array('--force', $args, true);

// Previous week (Mon..Sun)
$start = new DateTimeImmutable('monday last week');
$end = new DateTimeImmutable('sunday last week');
$startDate = $start->format('Y-m-d');
$endDate = $end->format('Y-m-d');

// Idempotency guard (avoid duplicates)
$storageDir = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
$markerFile = rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR . 'cleaning_weekly_last_sent.json';
if (!$force && is_file($markerFile)) {
    $prev = json_decode((string)@file_get_contents($markerFile), true);
    if (is_array($prev) && ($prev['start'] ?? '') === $startDate && ($prev['end'] ?? '') === $endDate) {
        fwrite(STDOUT, "Weekly cleaning report already sent for {$startDate}..{$endDate}. Use --force to resend.\n");
        exit(0);
    }
}

$to = trim((string)$config->get('mail.cleaning.weekly_to', 'm.fouad@zaco.sa'));
if ($to === '') {
    fwrite(STDERR, "No weekly recipient configured (mail.cleaning.weekly_to).\n");
    exit(2);
}

if (!Mailer::enabled()) {
    fwrite(STDERR, "Mailer is not enabled or PHPMailer not installed.\n");
    exit(3);
}

// Active places (expected scale)
$ok = CleaningWeeklyReport::sendPreviousWeek($db, $to);
if (!$ok) {
    fwrite(STDERR, "Failed to send weekly email.\n");
    exit(4);
}

@file_put_contents($markerFile, json_encode([
    'start' => $startDate,
    'end' => $endDate,
    'sent_at' => (new DateTimeImmutable('now'))->format('c'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

fwrite(STDOUT, "Weekly cleaning report sent to {$to} for {$startDate}..{$endDate}.\n");
