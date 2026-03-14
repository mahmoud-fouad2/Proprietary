<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class SettingsController extends BaseController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    /** @return array{app:string, php:string} */
    private function logFiles(): array
    {
        $logsDir = $this->storageRoot() . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0775, true);
        }

        return [
            'app' => $logsDir . DIRECTORY_SEPARATOR . 'app.log',
            'php' => $logsDir . DIRECTORY_SEPARATOR . 'php-error.log',
        ];
    }

    private function readTail(string $file, int $maxBytes = 200_000): string
    {
        if (!is_file($file)) {
            return '';
        }

        $size = (int)@filesize($file);
        if ($size <= 0) {
            return '';
        }

        $fh = @fopen($file, 'rb');
        if (!is_resource($fh)) {
            return '';
        }

        $start = max(0, $size - $maxBytes);
        @fseek($fh, $start);
        $data = (string)@stream_get_contents($fh);
        @fclose($fh);
        return $data;
    }

    public function tools(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        $storage = $this->storageRoot();
        $uploads = $this->uploadsRoot();
        $logsDir = $storage . DIRECTORY_SEPARATOR . 'logs';

        $uploadsOk = false;
        $uploadsDetails = $uploads;
        if (is_dir($uploads) || @mkdir($uploads, 0775, true)) {
            $testFile = rtrim($uploads, '/\\') . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
            $written = @file_put_contents($testFile, 'ok');
            if ($written !== false) {
                @unlink($testFile);
                $uploadsOk = true;
            } else {
                $uploadsOk = is_writable($uploads);
            }

            $rp = @realpath($uploads);
            if (is_string($rp) && $rp !== '') {
                $uploadsDetails = $uploads . ' => ' . $rp;
            }
        }

        $dbOk = true;
        try {
            $db->query('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        $tableChecks = [
            'users',
            'employees',
            'assets',
            'custody',
            'organizations',
            'audit_log',
        ];
        $tables = [];
        foreach ($tableChecks as $t) {
            $tables[$t] = $this->hasTable($db, $t);
        }

        $checks = [
            [
                'label' => 'اتصال قاعدة البيانات',
                'ok' => $dbOk,
                'details' => $dbOk ? 'OK' : 'فشل الاتصال بقاعدة البيانات',
            ],
            [
                'label' => 'مجلد التخزين storage',
                'ok' => is_dir($storage) && is_writable($storage),
                'details' => $storage,
            ],
            [
                'label' => 'مجلد الرفع uploads',
                'ok' => $uploadsOk,
                'details' => $uploadsDetails,
            ],
            [
                'label' => 'مجلد السجلات logs',
                'ok' => (is_dir($logsDir) || @mkdir($logsDir, 0775, true)) && is_writable($logsDir),
                'details' => $logsDir,
            ],
            [
                'label' => 'PHP: upload_max_filesize',
                'ok' => true,
                'details' => (string)ini_get('upload_max_filesize'),
            ],
            [
                'label' => 'PHP: post_max_size',
                'ok' => true,
                'details' => (string)ini_get('post_max_size'),
            ],
        ];

        $logFiles = $this->logFiles();
        $appLog = $this->readTail($logFiles['app']);
        $phpLog = $this->readTail($logFiles['php']);

        View::render('settings/tools', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'checks' => $checks,
            'tables' => $tables,
            'appLog' => $appLog,
            'phpLog' => $phpLog,
            'appLogExists' => is_file($logFiles['app']),
            'phpLogExists' => is_file($logFiles['php']),
            'appLogSize' => (is_file($logFiles['app']) ? (int)@filesize($logFiles['app']) : 0),
            'phpLogSize' => (is_file($logFiles['php']) ? (int)@filesize($logFiles['php']) : 0),
        ]);
    }

    public function clearLog(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $which = (string)($_POST['which'] ?? '');
        $files = $this->logFiles();
        if (!isset($files[$which])) {
            Http::redirect('/settings/tools');
            return;
        }

        $path = $files[$which];
        @file_put_contents($path, '');

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'ClearLog', 'logs', $which);
        Http::redirect('/settings/tools?log_ok=1');
    }

    public function downloadLog(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $which = (string)($_GET['which'] ?? '');
        $files = $this->logFiles();
        if (!isset($files[$which])) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $path = $files[$which];
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ($which === 'php' ? 'php-error.log' : 'app.log') . '"');
        header('Cache-Control: no-store');
        readfile($path);
    }

    private function handleLogoUpload(string $field): ?string
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }

        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return null;
        }

        $size = (int)($f['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return null;
        }

        $name = (string)($f['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
            return null;
        }

        $mime = '';
        try {
            $fi = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
        } catch (\Throwable) {
            $mime = '';
        }

        $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            return null;
        }

        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'branding';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $token = '';
        try {
            $token = bin2hex(random_bytes(10));
        } catch (\Throwable) {
            $token = (string)time();
        }

        $targetBase = 'logo-' . $token . '.' . $ext;
        $targetAbs = $dir . DIRECTORY_SEPARATOR . $targetBase;

        if (!move_uploaded_file($tmp, $targetAbs)) {
            return null;
        }

        return 'uploads/branding/' . $targetBase;
    }

    public function logo(): void
    {
        $db = $this->db();
        $path = '';
        try {
            $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_logo_path' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $path = $row ? trim((string)($row['setting_value'] ?? '')) : '';
        } catch (\Throwable) {
            $path = '';
        }

        $defaultCandidates = [
            $this->projectRoot() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo-zawaya.svg',
            // Backward compatibility (older deployments placed the file in project root)
            $this->projectRoot() . DIRECTORY_SEPARATOR . 'logo-zawaya.svg',
        ];

        $file = '';
        foreach ($defaultCandidates as $candidate) {
            if (is_file($candidate)) {
                $file = $candidate;
                break;
            }
        }

        if ($path !== '') {
            $rel = ltrim(str_replace(['..', '\\'], ['', '/'], $path), '/');
            // Backward compatibility: stored values sometimes include leading "uploads/"
            if (str_starts_with($rel, 'uploads/')) {
                $rel = substr($rel, strlen('uploads/'));
            }

            $candidate = $this->uploadsRoot() . DIRECTORY_SEPARATOR . $rel;
            if (is_file($candidate)) {
                $file = $candidate;
            }
        }

        if ($file === '' || !is_file($file)) {
            http_response_code(200);
            header('Content-Type: image/svg+xml; charset=utf-8');
            header('Cache-Control: public, max-age=60');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="40" viewBox="0 0 160 40">'
                . '<rect width="160" height="40" rx="8" fill="#111827"/>'
                . '<text x="16" y="26" font-size="16" fill="#E5E7EB" font-family="Arial, sans-serif">Assets</text>'
                . '</svg>';
            return;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=300');
        readfile($file);
    }

    private function assetStructureEnabled(PDO $db): bool
    {
        return $this->hasTable($db, 'asset_categories')
            && $this->hasTable($db, 'asset_sections')
            && $this->hasTable($db, 'asset_subsections');
    }

    public function index(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings ORDER BY setting_key ASC");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[(string)$r['setting_key']] = (string)$r['setting_value'];
        }

        $orgs = [];
        if ($this->hasTable($db, 'organizations')) {
            try {
                $orgStmt = $db->query('SELECT id, name, is_active FROM organizations ORDER BY id DESC');
                $orgs = $orgStmt->fetchAll();
            } catch (\Throwable) {
                $orgs = [];
            }
        }

        $assetCategories = [];
        $assetSections = [];
        $assetSubsections = [];
        $assetStructureEnabled = $this->assetStructureEnabled($db);
        if ($assetStructureEnabled) {
            try {
                $assetCategories = $db->query('SELECT id, name FROM asset_categories ORDER BY name ASC')->fetchAll();
            } catch (\Throwable) {
                $assetCategories = [];
            }
            try {
                $assetSections = $db->query('SELECT id, name FROM asset_sections ORDER BY name ASC')->fetchAll();
            } catch (\Throwable) {
                $assetSections = [];
            }
            try {
                $assetSubsections = $db->query('SELECT ss.id, ss.section_id, s.name AS section_name, ss.name FROM asset_subsections ss JOIN asset_sections s ON s.id = ss.section_id ORDER BY s.name ASC, ss.name ASC')->fetchAll();
            } catch (\Throwable) {
                $assetSubsections = [];
            }
        }

        View::render('settings/index', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'settings' => $settings,
            'orgs' => $orgs,
            'orgsEnabled' => $this->hasTable($db, 'organizations'),
            'assetStructureEnabled' => $assetStructureEnabled,
            'assetCategories' => $assetCategories,
            'assetSections' => $assetSections,
            'assetSubsections' => $assetSubsections,
            'success' => $_GET['saved'] ?? null,
        ]);
    }

    public function assetCategoryCreate(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Http::redirect('/settings?struct_err=cat_empty');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $stmt = $db->prepare('INSERT INTO asset_categories (name, created_at, updated_at) VALUES (?, ?, ?)');
            $stmt->execute([$name, $now, $now]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=cat_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'asset_categories', $name);
        Http::redirect('/settings?struct_ok=cat_created');
    }

    public function assetCategoryDelete(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/settings');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        try {
            $stmt = $db->prepare('DELETE FROM asset_categories WHERE id = ?');
            $stmt->execute([$id]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=cat_del_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'asset_categories', 'id=' . $id);
        Http::redirect('/settings?struct_ok=cat_deleted');
    }

    public function assetSectionCreate(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Http::redirect('/settings?struct_err=sec_empty');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $stmt = $db->prepare('INSERT INTO asset_sections (name, created_at, updated_at) VALUES (?, ?, ?)');
            $stmt->execute([$name, $now, $now]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=sec_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'asset_sections', $name);
        Http::redirect('/settings?struct_ok=sec_created');
    }

    public function assetSectionDelete(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/settings');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        try {
            $stmt = $db->prepare('DELETE FROM asset_sections WHERE id = ?');
            $stmt->execute([$id]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=sec_del_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'asset_sections', 'id=' . $id);
        Http::redirect('/settings?struct_ok=sec_deleted');
    }

    public function assetSubsectionCreate(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $sectionId = (int)($_POST['section_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($sectionId <= 0 || $name === '') {
            Http::redirect('/settings?struct_err=sub_empty');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $stmt = $db->prepare('INSERT INTO asset_subsections (section_id, name, created_at, updated_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$sectionId, $name, $now, $now]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=sub_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'asset_subsections', 'section_id=' . $sectionId . ' name=' . $name);
        Http::redirect('/settings?struct_ok=sub_created');
    }

    public function assetSubsectionDelete(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/settings');
            return;
        }

        $db = $this->db();
        if (!$this->assetStructureEnabled($db)) {
            Http::redirect('/settings?struct_err=missing');
            return;
        }

        try {
            $stmt = $db->prepare('DELETE FROM asset_subsections WHERE id = ?');
            $stmt->execute([$id]);
        } catch (\Throwable) {
            Http::redirect('/settings?struct_err=sub_del_fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'asset_subsections', 'id=' . $id);
        Http::redirect('/settings?struct_ok=sub_deleted');
    }

    public function orgCreate(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            Http::redirect('/settings?org_err=empty');
            return;
        }

        $db = $this->db();
        if (!$this->hasTable($db, 'organizations')) {
            Http::redirect('/settings?org_err=missing');
            return;
        }

        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare('INSERT INTO organizations (name, is_active, created_at, updated_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $isActive, $now, $now]);
        } catch (\Throwable) {
            Http::redirect('/settings?org_err=fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'organizations', $name);
        Http::redirect('/settings?org_ok=created');
    }

    public function orgToggle(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($id <= 0) {
            Http::redirect('/settings');
            return;
        }

        $db = $this->db();
        if (!$this->hasTable($db, 'organizations')) {
            Http::redirect('/settings');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $stmt = $db->prepare('UPDATE organizations SET is_active = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$active, $now, $id]);
        } catch (\Throwable) {
            Http::redirect('/settings?org_err=fail');
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Toggle', 'organizations', 'id=' . $id . ' active=' . $active);
        Http::redirect('/settings?org_ok=updated');
    }

    public function save(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $keys = [
            'org_name',
            'org_name_en',
            'org_phone',
            'org_email',
            'org_address',
            'theme_color',
            'low_stock_alert',
        ];

        $db = $this->db();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $logoPath = $this->handleLogoUpload('app_logo');
        if ($logoPath !== null) {
            $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
            $stmt->execute(['app_logo_path', $logoPath, $now]);
        }

        foreach ($keys as $k) {
            $val = trim((string)($_POST[$k] ?? ''));
            $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
            $stmt->execute([$k, $val, $now]);
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'UpdateSettings', 'app_settings', 'Organization settings updated');
        Http::redirect('/settings?saved=1');
    }

    public function changePassword(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $current = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $newPass === '' || $confirm === '') {
            Http::redirect('/settings?pw_err=empty');
            return;
        }

        if ($newPass !== $confirm) {
            Http::redirect('/settings?pw_err=mismatch');
            return;
        }

        if (mb_strlen($newPass) < 8) {
            Http::redirect('/settings?pw_err=short');
            return;
        }

        // Verify current password
        $db = $this->db();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([(int)$u['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            Http::redirect('/settings?pw_err=wrong');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt2 = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = ? WHERE id = ?');
        $stmt2->execute([password_hash($newPass, PASSWORD_DEFAULT), $now, (int)$u['id']]);

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'ChangePassword', 'users', (string)$u['email']);
        Http::redirect('/settings?pw_ok=1');
    }

    /**
     * Run database maintenance tasks
     */
    public function runMaintenance(): void
    {
        Csrf::verify();

        $u = $this->auth->user();
        if (!$u || !$this->auth->isAdminLike()) {
            Http::redirect('/settings?maint_err=forbidden');
            return;
        }

        $db = $this->db();
        $maintenance = new \Zaco\Core\Maintenance($db);
        $results = $maintenance->runAll();

        $this->auth->audit(
            (int)$u['id'],
            (string)$u['name'],
            'RunMaintenance',
            'system',
            json_encode($results, JSON_UNESCAPED_UNICODE) ?: ''
        );

        Http::redirect('/settings?maint_ok=1');
    }

    /**
     * Get database statistics
     */
    public function dbStats(): void
    {
        $u = $this->auth->user();
        if (!$u || !$this->auth->isAdminLike()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $db = $this->db();
        $maintenance = new \Zaco\Core\Maintenance($db);
        $stats = $maintenance->getStats();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Audit Log Page
     */
    public function audit(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Filters
        $action = trim((string)($_GET['action'] ?? ''));
        $table = trim((string)($_GET['table'] ?? ''));
        $actor = trim((string)($_GET['actor'] ?? ''));

        $where = '1=1';
        $params = [];

        if ($action !== '') {
            $where .= ' AND action = ?';
            $params[] = $action;
        }
        if ($table !== '') {
            $where .= ' AND table_name = ?';
            $params[] = $table;
        }
        if ($actor !== '') {
            $where .= ' AND actor_name LIKE ?';
            $params[] = '%' . $actor . '%';
        }

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($total / $perPage);

        // Get logs
        $stmt = $db->prepare(
            "SELECT id, actor_name, action, table_name, details, ip, created_at 
             FROM audit_log WHERE {$where} 
             ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get unique actions and tables for filter dropdowns
        $actions = $db->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
        $tables = $db->query('SELECT DISTINCT table_name FROM audit_log ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN);

        View::render('settings/audit', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'actions' => $actions,
            'tables' => $tables,
            'filterAction' => $action,
            'filterTable' => $table,
            'filterActor' => $actor,
        ]);
    }


}
