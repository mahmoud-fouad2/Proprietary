<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\Notify;
use Zaco\Core\Pdf;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class CustodyController extends BaseController
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
        $orgId = (int)($_GET['org_id'] ?? 0);
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $view = trim((string)($_GET['view'] ?? 'list'));
        if (!in_array($view, ['list', 'kanban'], true)) {
            $view = 'list';
        }

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 25;

        $sort = (string)($_GET['sort'] ?? '');
        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sortMap = [
            'id' => 'c.id',
            'employee' => 'c.employee_name',
            'item' => 'c.item_name',
            'serial' => 'c.serial_number',
            'date' => 'c.date_assigned',
            'status' => 'c.custody_status',
        ];
        if ($orgEnabled) {
            $sortMap['org'] = 'o.name';
        }
        $orderExpr = $sortMap[$sort] ?? 'c.id';
        $orderBy = $orderExpr . ' ' . $dir . ', c.id DESC';

        $where = 'c.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $where .= ' AND c.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $where .= ' AND (c.employee_name LIKE ? OR c.item_name LIKE ? OR c.serial_number LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($employeeId > 0) {
            $where .= ' AND c.employee_id = ?';
            $args[] = $employeeId;
        }
        if ($status !== '') {
            $where .= ' AND c.custody_status = ?';
            $args[] = $status;
        }

        $countStmt = $db->prepare('SELECT COUNT(*) AS c FROM custody c WHERE ' . $where);
        $countStmt->execute($args);
        $totalCount = (int)($countStmt->fetch()['c'] ?? 0);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $offset = max(0, $offset);

        $selectSql = $orgEnabled
            ? 'SELECT c.*, o.name AS org_name FROM custody c LEFT JOIN organizations o ON o.id = c.org_id'
            : 'SELECT c.* FROM custody c';
        $sql = $selectSql . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $items = $stmt->fetchAll();

        $undoId = 0;
        $undo = $_SESSION['undo_custody'] ?? null;
        if (is_array($undo) && isset($undo['id'], $undo['t'])) {
            $age = time() - (int)$undo['t'];
            if ($age >= 0 && $age <= self::UNDO_TTL_SECONDS) {
                $undoId = (int)$undo['id'];
            } else {
                unset($_SESSION['undo_custody']);
            }
        }

        View::render('custody/index', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'items' => $items,
            'q' => $q,
            'org_id' => $orgId,
            'employee_id' => $employeeId,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'status' => $status,
            'view' => $view,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'count' => $totalCount,
            'orgEnabled' => $orgEnabled,
            'sort' => $sort,
            'dir' => $dir,
            'canEdit' => $this->auth->can('edit_data'),
            'canDelete' => $this->auth->can('delete_data'),
            'undoId' => $undoId,
        ]);
    }

    public function createForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $orgId = (int)($_GET['org_id'] ?? 0);
        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $orgs = $orgEnabled ? $this->orgsList() : [];
        $defaultOrgId = $orgId > 0 ? $orgId : (count($orgs) === 1 ? (int)$orgs[0]['id'] : 0);

        View::render('custody/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $defaultOrgId > 0 ? ['org_id' => $defaultOrgId] : null,
            'orgs' => $orgs,
            'orgEnabled' => $orgEnabled,
            'employees' => $this->employeesList($orgEnabled && $defaultOrgId > 0 ? $defaultOrgId : null),
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
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => $err,
                'mode' => 'create',
            ]);
            return;
        }

        $empName = $this->employeeNameById((int)$data['employee_id'], ($data['org_id'] ?? null) ? (int)$data['org_id'] : null);
        if ($empName === null) {
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => 'الموظف غير موجود.',
                'mode' => 'create',
            ]);
            return;
        }

        $file = $this->handleAttachmentUpload('attachment');
        if (($file['error'] ?? null) !== null) {
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => (string)$file['error'],
                'mode' => 'create',
            ]);
            return;
        }
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $db = $this->db();
        $orgEnabled = $this->hasColumn($db, 'custody', 'org_id');
        if (!$orgEnabled) {
            $data['org_id'] = null;
        }

        $dateReturned = $data['date_returned'] ?? null;
        if (is_string($dateReturned) && trim($dateReturned) === '') {
            $dateReturned = null;
        }

        $stmt = $orgEnabled
            ? $db->prepare('INSERT INTO custody (org_id,employee_id,employee_name,item_name,description,serial_number,attachment_name,attachment_path,date_assigned,date_returned,custody_status,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            : $db->prepare('INSERT INTO custody (employee_id,employee_name,item_name,description,serial_number,attachment_name,attachment_path,date_assigned,date_returned,custody_status,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');

        $stmt->execute($orgEnabled ? [
            $data['org_id'],
            $data['employee_id'],
            $empName,
            $data['item_name'],
            $data['description'],
            $data['serial_number'],
            $file['name'],
            $file['path'],
            $data['date_assigned'],
            $dateReturned,
            $data['custody_status'],
            $data['notes'],
            $now,
            $now,
        ] : [
            $data['employee_id'],
            $empName,
            $data['item_name'],
            $data['description'],
            $data['serial_number'],
            $file['name'],
            $file['path'],
            $data['date_assigned'],
            $dateReturned,
            $data['custody_status'],
            $data['notes'],
            $now,
            $now,
        ]);

        $newId = (int)$db->lastInsertId();
        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'custody', $data['item_name'] . ' -> ' . $empName);
        
        // Send notification
        Notify::create(
            null,
            (int)$u['id'],
            (string)$u['name'],
            'create',
            'custody',
            $newId,
            $data['item_name'],
            'تمت إضافة عهدة جديدة: ' . $data['item_name'] . ' -> ' . $empName
        );
        
        Http::redirect('/custody?msg=created');
    }

    public function editForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) Http::redirect('/custody');

        $orgId = (int)($item['org_id'] ?? 0);

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'custody');

        View::render('custody/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'orgEnabled' => $orgEnabled,
            'employees' => $this->employeesList($orgId > 0 ? $orgId : null),
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
        if (!$existing) Http::redirect('/custody');

        $data = $this->readPost();
        $err = $this->validate($data);
        if ($err !== null) {
            $data['id'] = $id;
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => $err,
                'mode' => 'edit',
            ]);
            return;
        }

        $empName = $this->employeeNameById((int)$data['employee_id'], ($data['org_id'] ?? null) ? (int)$data['org_id'] : null);
        if ($empName === null) {
            $data['id'] = $id;
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => 'الموظف غير موجود.',
                'mode' => 'edit',
            ]);
            return;
        }

        $file = ['name' => $existing['attachment_name'] ?? null, 'path' => $existing['attachment_path'] ?? null];
        $new = $this->handleAttachmentUpload('attachment');
        if (($new['error'] ?? null) !== null) {
            $data['id'] = $id;
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
            View::render('custody/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'employees' => $this->employeesList(($data['org_id'] ?? null) ? (int)$data['org_id'] : null),
                'error' => (string)$new['error'],
                'mode' => 'edit',
            ]);
            return;
        }
        if ($new['path'] !== null) {
            $file = $new;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $orgEnabled = $this->hasColumn($db, 'custody', 'org_id');
        if (!$orgEnabled) {
            $data['org_id'] = null;
        }

        $dateReturned = $data['date_returned'] ?? null;
        if (is_string($dateReturned) && trim($dateReturned) === '') {
            $dateReturned = null;
        }

        $stmt = $orgEnabled
            ? $db->prepare('UPDATE custody SET org_id=?,employee_id=?,employee_name=?,item_name=?,description=?,serial_number=?,attachment_name=?,attachment_path=?,date_assigned=?,date_returned=?,custody_status=?,notes=?,updated_at=? WHERE id=? AND deleted_at IS NULL')
            : $db->prepare('UPDATE custody SET employee_id=?,employee_name=?,item_name=?,description=?,serial_number=?,attachment_name=?,attachment_path=?,date_assigned=?,date_returned=?,custody_status=?,notes=?,updated_at=? WHERE id=? AND deleted_at IS NULL');

        $stmt->execute($orgEnabled ? [
            $data['org_id'],
            $data['employee_id'],
            $empName,
            $data['item_name'],
            $data['description'],
            $data['serial_number'],
            $file['name'],
            $file['path'],
            $data['date_assigned'],
            $dateReturned,
            $data['custody_status'],
            $data['notes'],
            $now,
            $id,
        ] : [
            $data['employee_id'],
            $empName,
            $data['item_name'],
            $data['description'],
            $data['serial_number'],
            $file['name'],
            $file['path'],
            $data['date_assigned'],
            $dateReturned,
            $data['custody_status'],
            $data['notes'],
            $now,
            $id,
        ]);

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Edit', 'custody', 'ID=' . $id . ' ' . $data['item_name'] . ' -> ' . $empName);
        
        // Record change history
        $changes = Notify::diff($existing, $data, ['id', 'created_at', 'updated_at', 'deleted_at', 'attachment_path']);
        if (!empty($changes)) {
            Notify::recordChange(
                'custody',
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
            'custody',
            $id,
            $data['item_name'],
            'تم تعديل بيانات العهدة: ' . $data['item_name']
        );
        
        Http::redirect('/custody?msg=updated');
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
        if ($id <= 0) Http::redirect('/custody');

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $item = $this->find($id);
        $stmt = $db->prepare('UPDATE custody SET deleted_at=?, updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$now, $now, $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['undo_custody'] = ['id' => $id, 't' => time()];
        }

        if ($item) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'custody', 'ID=' . $id . ' ' . (string)$item['item_name']);
            
            // Record change history
            Notify::recordChange(
                'custody',
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
                'custody',
                $id,
                (string)$item['item_name'],
                'تم حذف العهدة: ' . (string)$item['item_name']
            );
        }
        Http::redirect('/custody?msg=deleted');
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
        $undo = $_SESSION['undo_custody'] ?? null;
        if ($id <= 0 || !is_array($undo) || !isset($undo['id'], $undo['t']) || (int)$undo['id'] !== $id) {
            Http::redirect('/custody');
            return;
        }

        $age = time() - (int)$undo['t'];
        if ($age < 0 || $age > self::UNDO_TTL_SECONDS) {
            unset($_SESSION['undo_custody']);
            Http::redirect('/custody');
            return;
        }

        $db = $this->db();
        $stmtInfo = $db->prepare('SELECT item_name FROM custody WHERE id = ? LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE custody SET deleted_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$now, $id]);

        if ($stmt->rowCount() > 0) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Restore', 'custody', 'ID=' . $id . ' ' . (string)($target['item_name'] ?? ''));
            
            // Record change history
            Notify::recordChange(
                'custody',
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
                'custody',
                $id,
                (string)($target['item_name'] ?? ''),
                'تم استعادة العهدة: ' . (string)($target['item_name'] ?? '')
            );
        }

        unset($_SESSION['undo_custody']);
        Http::redirect('/custody?msg=restored');
    }

    public function attachment(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        $row = $this->find($id);
        if (!$row || empty($row['attachment_path'])) {
            http_response_code(404);
            return;
        }

        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$row['attachment_path']), '/');
        $file = $this->uploadsRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }

        $name = (string)($row['attachment_name'] ?? 'attachment');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($file);
    }

    public function exportExcel(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? "SELECT c.id,c.employee_name,c.item_name,c.serial_number,c.date_assigned,c.date_returned,c.custody_status,o.name AS org_name FROM custody c LEFT JOIN organizations o ON o.id = c.org_id WHERE c.deleted_at IS NULL"
            : "SELECT c.id,c.employee_name,c.item_name,c.serial_number,c.date_assigned,c.date_returned,c.custody_status FROM custody c WHERE c.deleted_at IS NULL";
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= " AND c.org_id = ?";
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (c.employee_name LIKE ? OR c.item_name LIKE ? OR c.serial_number LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($employeeId > 0) {
            $sql .= ' AND c.employee_id = ?';
            $args[] = $employeeId;
        }
        if ($status !== '') {
            $sql .= ' AND c.custody_status = ?';
            $args[] = $status;
        }
        $sql .= " ORDER BY c.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="custody.xls"');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for better Arabic support in Excel
        $now = new DateTimeImmutable('now');
        $title = 'تقرير العُهد';
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

        echo '<table><thead><tr><th>ID</th>';
        if ($orgEnabled) echo '<th>المنظمة</th>';
        echo '<th>الموظف</th><th>العهدة</th><th>السيريال</th><th>تاريخ التسليم</th><th>تاريخ الإرجاع</th><th>الحالة</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) {
                echo '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            }
            echo '<td>' . $esc((string)$r['employee_name']) . '</td>';
            echo '<td>' . $esc((string)$r['item_name']) . '</td>';
            echo '<td>' . $esc((string)($r['serial_number'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)$r['date_assigned']) . '</td>';
            echo '<td>' . $esc((string)($r['date_returned'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)$r['custody_status']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }

    public function exportPdf(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'custody');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? 'SELECT c.id,c.employee_name,c.item_name,c.serial_number,c.date_assigned,c.date_returned,c.custody_status,c.notes,o.name AS org_name FROM custody c LEFT JOIN organizations o ON o.id = c.org_id WHERE c.deleted_at IS NULL'
            : 'SELECT c.id,c.employee_name,c.item_name,c.serial_number,c.date_assigned,c.date_returned,c.custody_status,c.notes FROM custody c WHERE c.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= ' AND c.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (c.employee_name LIKE ? OR c.item_name LIKE ? OR c.serial_number LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($employeeId > 0) {
            $sql .= ' AND c.employee_id = ?';
            $args[] = $employeeId;
        }
        if ($status !== '') {
            $sql .= ' AND c.custody_status = ?';
            $args[] = $status;
        }
        $sql .= ' ORDER BY c.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $now = new DateTimeImmutable('now');
        $title = 'تقرير العُهد';
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8" />'
            . '<style>'
            . 'body{font-family:dejavusans;font-size:12px;color:#111;}'
            . 'h1{font-size:18px;margin:0 0 8px;}'
            . '.meta{font-size:11px;color:#555;margin-bottom:10px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #999;padding:6px;vertical-align:top;}'
            . 'th{background:#f2f2f2;}'
            . '</style></head><body>';

        $html .= '<h1>' . $esc($title) . '</h1>';
        $html .= '<div class="meta">تاريخ: ' . $esc($now->format('Y-m-d H:i')) . ' — عدد السجلات: ' . (int)count($rows) . '</div>';

        $html .= '<table><thead><tr>';
        $html .= '<th>ID</th>';
        if ($orgEnabled) $html .= '<th>المنظمة</th>';
        $html .= '<th>الموظف</th><th>العهدة</th><th>السيريال</th><th>تاريخ التسليم</th><th>تاريخ الإرجاع</th><th>الحالة</th><th>ملاحظات</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) $html .= '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['employee_name']) . '</td>';
            $html .= '<td>' . $esc((string)$r['item_name']) . '</td>';
            $html .= '<td>' . $esc((string)($r['serial_number'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['date_assigned']) . '</td>';
            $html .= '<td>' . $esc((string)($r['date_returned'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['custody_status']) . '</td>';
            $html .= '<td>' . $esc((string)($r['notes'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        Pdf::download('custody.pdf', $html);
    }

    /** @return array<string,mixed>|null */
    private function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM custody WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array{id:int,full_name:string}> */
    private function employeesList(?int $orgId = null): array
    {
        $db = $this->db();
        $empOrgEnabled = $this->hasColumn($db, 'employees', 'org_id');
        if ($empOrgEnabled && $orgId !== null && $orgId > 0) {
            $stmt = $db->prepare("SELECT id, full_name FROM employees WHERE deleted_at IS NULL AND org_id = ? ORDER BY full_name ASC");
            $stmt->execute([$orgId]);
        } else {
            $stmt = $db->query("SELECT id, full_name FROM employees WHERE deleted_at IS NULL ORDER BY full_name ASC");
        }
        /** @var array<int,array{id:int,full_name:string}> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    private function employeeNameById(int $id, ?int $orgId = null): ?string
    {
        if ($id <= 0) return null;
        $db = $this->db();
        $empOrgEnabled = $this->hasColumn($db, 'employees', 'org_id');
        if ($empOrgEnabled && $orgId !== null && $orgId > 0) {
            $stmt = $db->prepare('SELECT full_name FROM employees WHERE id = ? AND org_id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$id, $orgId]);
        } else {
            $stmt = $db->prepare('SELECT full_name FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$id]);
        }
        $row = $stmt->fetch();
        $name = $row ? (string)($row['full_name'] ?? '') : '';
        return $name !== '' ? $name : null;
    }

    /** @return array<string,mixed> */
    private function readPost(): array
    {
        $orgId = (int)($_POST['org_id'] ?? 0);
        $eid = (int)($_POST['employee_id'] ?? 0);
        try {
            $db = $this->db();
            if (!$this->hasColumn($db, 'custody', 'org_id')) {
                $orgId = 0;
            }
        } catch (\Throwable) {
            $orgId = 0;
        }
        return [
            'org_id' => $orgId > 0 ? $orgId : null,
            'employee_id' => $eid,
            'item_name' => trim((string)($_POST['item_name'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'serial_number' => trim((string)($_POST['serial_number'] ?? '')) ?: null,
            'date_assigned' => (string)($_POST['date_assigned'] ?? ''),
            'date_returned' => (trim((string)($_POST['date_returned'] ?? '')) !== '')
                ? trim((string)$_POST['date_returned'])
                : null,
            'custody_status' => (string)($_POST['custody_status'] ?? 'active'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<int,array{id:int,name:string}> */
    private function orgsList(): array
    {
        $db = $this->db();
        try {
            if (!$this->hasTable($db, 'organizations')) {
                return [];
            }

            $nameCol = null;
            if ($this->hasColumn($db, 'organizations', 'name')) {
                $nameCol = 'name';
            } elseif ($this->hasColumn($db, 'organizations', 'org_name')) {
                $nameCol = 'org_name';
            } elseif ($this->hasColumn($db, 'organizations', 'title')) {
                $nameCol = 'title';
            }

            if ($nameCol === null) {
                return [];
            }

            $sql = 'SELECT id, ' . $nameCol . ' AS name FROM organizations';
            if ($this->hasColumn($db, 'organizations', 'is_active')) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY name ASC';
            $stmt = $db->query($sql);
            /** @var array<int,array{id:int,name:string}> $rows */
            $rows = $stmt->fetchAll();
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }


    private function validate(array $data): ?string
    {
        if ((int)($data['employee_id'] ?? 0) <= 0 || ($data['item_name'] ?? '') === '' || ($data['date_assigned'] ?? '') === '') {
            return 'الموظف + اسم العهدة + تاريخ التسليم حقول مطلوبة.';
        }
        $allowed = ['active','returned','damaged','lost'];
        if (!in_array((string)$data['custody_status'], $allowed, true)) {
            return 'حالة العهدة غير صحيحة.';
        }
        if ((string)$data['custody_status'] === 'returned' && empty($data['date_returned'])) {
            return 'عند اختيار (مُسترجعة) يجب تحديد تاريخ الإرجاع.';
        }
        return null;
    }

    /** @return array{name:?string,path:?string,error:?string} */
    private function handleAttachmentUpload(string $field): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return ['name' => null, 'path' => null, 'error' => null];
        }
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['name' => null, 'path' => null, 'error' => null];
        }
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['name' => null, 'path' => null, 'error' => 'تعذر رفع المرفق.'];
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        $size = (int)($f['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > 12_000_000) {
            return ['name' => null, 'path' => null, 'error' => 'حجم المرفق غير صالح (الحد الأقصى 12MB).'];
        }

        $origName = (string)($f['name'] ?? 'attachment');
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName) ?: 'attachment';

        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        if ($ext !== '' && !in_array($ext, $allowedExt, true)) {
            return ['name' => null, 'path' => null, 'error' => 'نوع المرفق غير مدعوم. ارفع PDF أو صورة (PNG/JPG/WEBP).'];
        }

        $mime = '';
        try {
            if (class_exists('finfo')) {
                $fi = new \finfo(FILEINFO_MIME_TYPE);
                $mime = (string)($fi->file($tmp) ?: '');
            }
        } catch (\Throwable) {
            $mime = '';
        }

        $allowedMime = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];
        if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
            return ['name' => null, 'path' => null, 'error' => 'نوع المرفق غير مدعوم. ارفع PDF أو صورة.'];
        }

        $now = new DateTimeImmutable('now');
        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'custody' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $now->format('Y/m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $targetName = $now->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase;
        $target = $dir . DIRECTORY_SEPARATOR . $targetName;

        if (!move_uploaded_file($tmp, $target)) {
            return ['name' => null, 'path' => null, 'error' => 'تعذر حفظ الملف المرفق.'];
        }

        return [
            'name' => $origName,
            'path' => 'custody/' . $now->format('Y/m') . '/' . $targetName,
            'error' => null,
        ];
    }


}
