<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\Notify;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class SoftwareController extends BaseController
{
    private const UNDO_TTL_SECONDS = 300;

    public function __construct(private readonly Auth $auth)
    {
    }

    public function index(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $cat = trim((string)($_GET['cat'] ?? ''));

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 24;

        $where = 'deleted_at IS NULL';
        $args = [];
        if ($q !== '') {
            $where .= " AND (name LIKE ? OR version LIKE ?)";
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $where .= " AND category = ?";
            $args[] = $cat;
        }
        $db = $this->db();

        $countStmt = $db->prepare('SELECT COUNT(*) AS c FROM software_library WHERE ' . $where);
        $countStmt->execute($args);
        $totalCount = (int)($countStmt->fetch()['c'] ?? 0);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM software_library WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $items = $stmt->fetchAll();

        $catsStmt = $db->query("SELECT DISTINCT category FROM software_library WHERE deleted_at IS NULL AND category IS NOT NULL AND category <> '' ORDER BY category ASC");
        $cats = array_values(array_filter(array_map(static fn($r) => (string)($r['category'] ?? ''), $catsStmt->fetchAll())));

        $undoId = 0;
        $undo = $_SESSION['undo_software'] ?? null;
        if (is_array($undo) && isset($undo['id'], $undo['t'])) {
            $age = time() - (int)$undo['t'];
            if ($age >= 0 && $age <= self::UNDO_TTL_SECONDS) {
                $undoId = (int)$undo['id'];
            } else {
                unset($_SESSION['undo_software']);
            }
        }

        View::render('software/index', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'items' => $items,
            'q' => $q,
            'cat' => $cat,
            'cats' => $cats,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'count' => $totalCount,
            'canEdit' => $this->auth->can('edit_data'),
            'canDelete' => $this->auth->can('delete_data'),
            'undoId' => $undoId,
        ]);
    }

    public function createForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        View::render('software/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => null,
            'error' => null,
            'mode' => 'create',
        ]);
    }

    public function createSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $data = $this->readPost();
        $err = $this->validate($data);
        if ($err !== null) {
            View::render('software/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'error' => $err,
                'mode' => 'create',
            ]);
            return;
        }

        $file = $this->handleUpload('file');
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO software_library (name,category,version,description,file_path,file_size,is_free,license_key,download_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['version'],
            $data['description'],
            $file['path'],
            $file['size'],
            $data['is_free'],
            $data['license_key'],
            $data['download_url'],
            $now,
            $now,
        ]);

        $newId = (int)$db->lastInsertId();
        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'software_library', $data['name'] . ' ' . $data['version']);
        
        // Send notification
        Notify::create(
            null,
            (int)$u['id'],
            (string)$u['name'],
            'create',
            'software',
            $newId,
            $data['name'],
            'تمت إضافة برنامج جديد: ' . $data['name'] . ' ' . $data['version']
        );
        
        Http::redirect('/software?msg=created');
    }

    public function editForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) Http::redirect('/software');

        View::render('software/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'error' => null,
            'mode' => 'edit',
        ]);
    }

    public function editSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $existing = $this->find($id);
        if (!$existing) Http::redirect('/software');

        $data = $this->readPost();
        $err = $this->validate($data);
        if ($err !== null) {
            $data['id'] = $id;
            $data['file_path'] = $existing['file_path'] ?? null;
            View::render('software/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'error' => $err,
                'mode' => 'edit',
            ]);
            return;
        }

        $filePath = $existing['file_path'] ?? null;
        $fileSize = (int)($existing['file_size'] ?? 0);
        $new = $this->handleUpload('file');
        if ($new['path'] !== null) {
            $filePath = $new['path'];
            $fileSize = $new['size'];
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $stmt = $db->prepare('UPDATE software_library SET name=?,category=?,version=?,description=?,file_path=?,file_size=?,is_free=?,license_key=?,download_url=?,updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['version'],
            $data['description'],
            $filePath,
            $fileSize,
            $data['is_free'],
            $data['license_key'],
            $data['download_url'],
            $now,
            $id,
        ]);

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Edit', 'software_library', 'ID=' . $id . ' ' . $data['name'] . ' ' . $data['version']);
        
        // Record change history
        $changes = Notify::diff($existing, $data, ['id', 'created_at', 'updated_at', 'deleted_at', 'file_path']);
        if (!empty($changes)) {
            Notify::recordChange(
                'software',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'update',
                $changes
            );
        }
        
        // Send notification
        Notify::create(
            null,
            (int)$u['id'],
            (string)$u['name'],
            'update',
            'software',
            $id,
            $data['name'],
            'تم تعديل بيانات البرنامج: ' . $data['name']
        );
        
        Http::redirect('/software?msg=updated');
    }

    public function deleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Http::redirect('/software');

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $item = $this->find($id);
        $stmt = $db->prepare('UPDATE software_library SET deleted_at=?, updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$now, $now, $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['undo_software'] = ['id' => $id, 't' => time()];
        }

        if ($item) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'software_library', 'ID=' . $id . ' ' . (string)$item['name']);
            
            // Record change history
            Notify::recordChange(
                'software',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                ['deleted' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                'software',
                $id,
                (string)$item['name'],
                'تم حذف البرنامج: ' . (string)$item['name']
            );
        }
        Http::redirect('/software?msg=deleted');
    }

    public function undoDeleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $undo = $_SESSION['undo_software'] ?? null;
        if ($id <= 0 || !is_array($undo) || !isset($undo['id'], $undo['t']) || (int)$undo['id'] !== $id) {
            Http::redirect('/software');
            return;
        }

        $age = time() - (int)$undo['t'];
        if ($age < 0 || $age > self::UNDO_TTL_SECONDS) {
            unset($_SESSION['undo_software']);
            Http::redirect('/software');
            return;
        }

        $db = $this->db();
        $stmtInfo = $db->prepare('SELECT name FROM software_library WHERE id = ? LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE software_library SET deleted_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$now, $id]);

        if ($stmt->rowCount() > 0) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Restore', 'software_library', 'ID=' . $id . ' ' . (string)($target['name'] ?? ''));
            
            // Record change history
            Notify::recordChange(
                'software',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                ['restored' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                'software',
                $id,
                (string)($target['name'] ?? ''),
                'تم استعادة البرنامج: ' . (string)($target['name'] ?? '')
            );
        }

        unset($_SESSION['undo_software']);
        Http::redirect('/software?msg=restored');
    }

    public function download(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        $row = $this->find($id);
        if (!$row || empty($row['file_path'])) {
            http_response_code(404);
            return;
        }

        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$row['file_path']), '/');
        $file = $this->uploadsRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($row['name'] ?? 'software')) ?: 'software';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($file);
    }

    public function exportExcel(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        $q = trim((string)($_GET['q'] ?? ''));
        $cat = trim((string)($_GET['cat'] ?? ''));
        $where = 'deleted_at IS NULL';
        $args = [];
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR version LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $where .= ' AND category = ?';
            $args[] = $cat;
        }

        $stmt = $db->prepare('SELECT id,name,category,version,is_free,download_url FROM software_library WHERE ' . $where . ' ORDER BY id DESC');
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="software.xls"');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for better Arabic support in Excel
        $now = new DateTimeImmutable('now');
        $title = 'تقرير البرامج';
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        echo '<html lang="ar" dir="rtl"><head><meta charset="utf-8" />'
            . '<style>'
            . 'body{font-family:Tahoma,Arial,sans-serif;font-size:12px;color:#111;}'
            . 'h2{font-size:16px;margin:0 0 8px;}'
            . '.meta{font-size:11px;color:#555;margin:0 0 10px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #999;padding:6px;vertical-align:top;}'
            . 'th{background:#f2f2f2;font-weight:bold;}'
            . '</style></head><body>';

        echo '<h2>' . $esc($title) . '</h2>';
        echo '<div class="meta">تاريخ: ' . $esc($now->format('Y-m-d H:i')) . ' — عدد السجلات: ' . (int)count($rows) . '</div>';

        echo '<table><thead><tr><th>ID</th><th>الاسم</th><th>الفئة</th><th>الإصدار</th><th>مجاني</th><th>رابط</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . (int)$r['id'] . '</td>';
            echo '<td>' . $esc((string)$r['name']) . '</td>';
            echo '<td>' . $esc((string)($r['category'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)($r['version'] ?? '')) . '</td>';
            echo '<td>' . ((int)$r['is_free'] === 1 ? 'نعم' : 'لا') . '</td>';
            echo '<td>' . $esc((string)($r['download_url'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }

    /** @return array<string,mixed>|null */
    private function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM software_library WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed> */
    private function readPost(): array
    {
        return [
            'name' => trim((string)($_POST['name'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? '')) ?: null,
            'version' => trim((string)($_POST['version'] ?? '')) ?: null,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'is_free' => isset($_POST['is_free']) ? 1 : 0,
            'license_key' => trim((string)($_POST['license_key'] ?? '')) ?: null,
            'download_url' => trim((string)($_POST['download_url'] ?? '')) ?: null,
        ];
    }

    private function validate(array $data): ?string
    {
        if (($data['name'] ?? '') === '') {
            return 'اسم البرنامج مطلوب.';
        }
        if (!empty($data['download_url']) && !filter_var((string)$data['download_url'], FILTER_VALIDATE_URL)) {
            return 'رابط التحميل غير صالح.';
        }
        return null;
    }

    /** @return array{path:?string,size:int} */
    private function handleUpload(string $field): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return ['path' => null, 'size' => 0];
        }
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'size' => 0];
        }
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'size' => 0];
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        $size = (int)($f['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > 250_000_000) {
            return ['path' => null, 'size' => 0];
        }

        $origName = (string)($f['name'] ?? 'file');
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName) ?: 'file';

        $now = new DateTimeImmutable('now');
        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'software' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $now->format('Y/m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $targetName = $now->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase;
        $target = $dir . DIRECTORY_SEPARATOR . $targetName;

        if (!move_uploaded_file($tmp, $target)) {
            return ['path' => null, 'size' => 0];
        }

        return [
            'path' => 'software/' . $now->format('Y/m') . '/' . $targetName,
            'size' => $size,
        ];
    }


}
