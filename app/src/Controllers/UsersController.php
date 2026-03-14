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

final class UsersController extends BaseController
{
    private const UNDO_TTL_SECONDS = 300;

    public function __construct(private readonly Auth $auth)
    {
    }

    /** @return array<string,string> */
    private function navTabLabels(): array
    {
        return [
            'inventory' => 'الأصول',
            'custody' => 'العُهد',
            'employees' => 'الموظفون',
            'software' => 'البرامج',
            'cleaning' => 'النظافة',
            'users' => 'المستخدمون',
            'settings' => 'الإعدادات',
        ];
    }

    /** @return array<string,string> */
    private function customPermissionLabels(): array
    {
        return [
            'view_assets' => 'عرض الأصول',
            'view_custody' => 'عرض العُهد',
            'view_employees' => 'عرض الموظفين',
            'view_software' => 'عرض البرامج',
            'cleaning' => 'النظافة',
            'manage_cleaning_places' => 'إدارة أماكن النظافة',
            'manage_users' => 'إدارة المستخدمين',
            'settings' => 'الإعدادات',
            'edit_data' => 'تعديل البيانات',
            'delete_data' => 'حذف البيانات',
        ];
    }

    /** @return list<string> */
    private function readCustomPermissionsFromPost(): array
    {
        $allowed = array_keys($this->customPermissionLabels());
        $perms = $_POST['custom_perms'] ?? [];
        if (!is_array($perms)) {
            return [];
        }
        $out = [];
        foreach ($perms as $p) {
            if (is_string($p) && in_array($p, $allowed, true)) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function loadCustomPermissions(int $userId): array
    {
        if ($userId <= 0) return [];
        $db = $this->db();
        $key = 'perms:user:' . $userId;
        $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $raw = (string)($row['setting_value'] ?? '');
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];

        $allowed = array_keys($this->customPermissionLabels());
        $out = [];
        foreach ($decoded as $v) {
            if (is_string($v) && in_array($v, $allowed, true)) {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }

    private function saveCustomPermissions(int $userId, array $permissions): void
    {
        if ($userId <= 0) return;
        $db = $this->db();
        $key = 'perms:user:' . $userId;
        $val = json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE);
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)');
        $stmt->execute([$key, $val, $now]);
    }

    /** @return list<string> */
    private function readHiddenTabsFromPost(): array
    {
        $allowed = array_keys($this->navTabLabels());
        $hidden = $_POST['hide_tabs'] ?? [];
        if (!is_array($hidden)) {
            return [];
        }
        $out = [];
        foreach ($hidden as $k) {
            if (is_string($k) && in_array($k, $allowed, true)) {
                $out[] = $k;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function loadHiddenTabs(int $userId): array
    {
        if ($userId <= 0) return [];
        $db = $this->db();
        $key = 'nav_hidden:user:' . $userId;
        $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $raw = (string)($row['setting_value'] ?? '');
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $allowed = array_keys($this->navTabLabels());
        $out = [];
        foreach ($decoded as $v) {
            if (is_string($v) && in_array($v, $allowed, true)) {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }

    private function saveHiddenTabs(int $userId, array $hiddenTabs): void
    {
        if ($userId <= 0) return;
        $db = $this->db();
        $key = 'nav_hidden:user:' . $userId;
        $val = json_encode(array_values($hiddenTabs), JSON_UNESCAPED_UNICODE);
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)');
        $stmt->execute([$key, $val, $now]);
    }

    public function index(): void
    {
        $db = $this->db();

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 25;

        $sort = (string)($_GET['sort'] ?? '');
        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $sortMap = [
            'id' => 'u.id',
            'name' => 'u.name',
            'email' => 'u.email',
            'role' => 'u.role',
            'active' => 'u.is_active',
            'last' => 'u.last_login_at',
        ];
        $orderExpr = $sortMap[$sort] ?? 'u.id';
        $orderBy = $orderExpr . ' ' . $dir . ', u.id DESC';

        $countStmt = $db->query("SELECT COUNT(*) AS c FROM users u WHERE u.deleted_at IS NULL");
        $totalCount = (int)($countStmt->fetch()['c'] ?? 0);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $offset = max(0, $offset);

        $stmt = $db->query("SELECT u.id,u.name,u.email,u.role,u.is_active,u.last_login_at FROM users u WHERE u.deleted_at IS NULL ORDER BY " . $orderBy . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
        $users = $stmt->fetchAll();

        $undoId = 0;
        $undo = $_SESSION['undo_users'] ?? null;
        if (is_array($undo) && isset($undo['id'], $undo['t'])) {
            $age = time() - (int)$undo['t'];
            if ($age >= 0 && $age <= self::UNDO_TTL_SECONDS) {
                $undoId = (int)$undo['id'];
            } else {
                unset($_SESSION['undo_users']);
            }
        }

        View::render('users/index', [
            'csrf' => Csrf::token(),
            'currentUser' => $this->auth->user(),
            'users' => $users,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'count' => $totalCount,
            'sort' => $sort,
            'dir' => $dir,
            'undoId' => $undoId,
        ]);
    }

    public function createForm(): void
    {
        View::render('users/create', [
            'csrf' => Csrf::token(),
            'error' => null,
            'navTabs' => $this->navTabLabels(),
            'hiddenTabs' => [],
            'permLabels' => $this->customPermissionLabels(),
            'customPerms' => [],
        ]);
    }

    public function createSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');

        if ($name === '' || $email === '' || $password === '') {
            View::render('users/create', [
                'csrf' => Csrf::token(),
                'error' => 'الرجاء إدخال الاسم والبريد وكلمة المرور',
                'navTabs' => $this->navTabLabels(),
                'hiddenTabs' => $this->readHiddenTabsFromPost(),
                'permLabels' => $this->customPermissionLabels(),
                'customPerms' => $this->readCustomPermissionsFromPost(),
            ]);
            return;
        }

        $allowed = ['admin', 'user'];
        if (!in_array($role, $allowed, true)) {
            $role = 'user';
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();

        // Pre-check email uniqueness to provide a clear error (and to detect soft-deleted conflicts).
        $stmtEmail = $db->prepare('SELECT id, deleted_at FROM users WHERE email = ? LIMIT 1');
        $stmtEmail->execute([$email]);
        $existing = $stmtEmail->fetch();
        if ($existing) {
            $isDeleted = !empty($existing['deleted_at']);
            View::render('users/create', [
                'csrf' => Csrf::token(),
                'error' => $isDeleted
                    ? 'هذا البريد مرتبط بحساب محذوف. قم باستعادته من قائمة المستخدمين أو استخدم بريدًا مختلفًا.'
                    : 'البريد مستخدم بالفعل.',
                'navTabs' => $this->navTabLabels(),
                'hiddenTabs' => $this->readHiddenTabsFromPost(),
                'permLabels' => $this->customPermissionLabels(),
                'customPerms' => $this->readCustomPermissionsFromPost(),
            ]);
            return;
        }

        $stmt = $db->prepare('INSERT INTO users (name,email,password_hash,role,is_active,must_change_password,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');

        try {
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, 1, 0, $now, $now]);
        } catch (\Throwable $e) {
            // Some installations use an ENUM role that doesn't include 'user' (e.g. uses 'viewer').
            $retryOk = false;
            if ($role === 'user') {
                try {
                    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'viewer', 1, 0, $now, $now]);
                    $retryOk = true;
                } catch (\Throwable) {
                    $retryOk = false;
                }
            }
            if ($retryOk) {
                // continue below
            } else {
                $msg = 'تعذر إنشاء المستخدم.';
                if ($e instanceof \PDOException) {
                    $info = $e->errorInfo ?? null;
                    $mysqlCode = is_array($info) ? (int)($info[1] ?? 0) : 0;
                    if ($mysqlCode === 1062) {
                        $msg = 'البريد مستخدم بالفعل (قد يكون لحساب محذوف).';
                    }
                }
            View::render('users/create', [
                'csrf' => Csrf::token(),
                'error' => $msg,
                'navTabs' => $this->navTabLabels(),
                'hiddenTabs' => $this->readHiddenTabsFromPost(),
                'permLabels' => $this->customPermissionLabels(),
                'customPerms' => $this->readCustomPermissionsFromPost(),
            ]);
            return;
            }
        }

        $newUserId = (int)$db->lastInsertId();
        $this->saveHiddenTabs($newUserId, $this->readHiddenTabsFromPost());
        $this->saveCustomPermissions($newUserId, $this->readCustomPermissionsFromPost());

        $actor = $this->auth->user();
        if ($actor) {
            $this->auth->audit((int)$actor['id'], (string)$actor['name'], 'Create', 'users', $name . ' <' . $email . '>');
            
            // Send notification
            Notify::create(
                null,
                (int)$actor['id'],
                (string)$actor['name'],
                'create',
                'user',
                $newUserId,
                $name,
                'تمت إضافة مستخدم جديد: ' . $name
            );
        }
        Http::redirect('/users?msg=created');
    }

    public function editForm(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $db = $this->db();
        $stmt = $db->prepare('SELECT id,name,email,role,is_active FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $userRow = $stmt->fetch();
        if (!$userRow) {
            Http::redirect('/users');
            return;
        }

        View::render('users/edit', [
            'csrf' => Csrf::token(),
            'error' => null,
            'userRow' => $userRow,
            'navTabs' => $this->navTabLabels(),
            'hiddenTabs' => $this->loadHiddenTabs((int)($userRow['id'] ?? 0)),
            'permLabels' => $this->customPermissionLabels(),
            'customPerms' => $this->loadCustomPermissions((int)($userRow['id'] ?? 0)),
        ]);
    }

    public function editSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $db = $this->db();

        $stmt = $db->prepare('SELECT id,name,email,role,is_active FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $userRow = $stmt->fetch();
        if (!$userRow) {
            Http::redirect('/users');
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role'] ?? 'user');
        $newPass = trim((string)($_POST['password'] ?? ''));

        if ($name === '' || $email === '') {
            View::render('users/edit', [
                'csrf' => Csrf::token(),
                'error' => 'الاسم والبريد مطلوبان',
                'userRow' => $userRow,
                'navTabs' => $this->navTabLabels(),
                'hiddenTabs' => $this->loadHiddenTabs($id),
                'permLabels' => $this->customPermissionLabels(),
                'customPerms' => $this->loadCustomPermissions($id),
            ]);
            return;
        }

        $allowed = ['admin', 'user'];
        if (!in_array($role, $allowed, true)) $role = 'user';

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        // Pre-check email uniqueness (also detects soft-deleted conflicts).
        $stmtEmail = $db->prepare('SELECT id, deleted_at FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmtEmail->execute([$email, $id]);
        $existing = $stmtEmail->fetch();
        if ($existing) {
            $isDeleted = !empty($existing['deleted_at']);
            View::render('users/edit', [
                'csrf' => Csrf::token(),
                'error' => $isDeleted
                    ? 'هذا البريد مرتبط بحساب محذوف. قم باستعادته من قائمة المستخدمين أو استخدم بريدًا مختلفًا.'
                    : 'البريد مستخدم بالفعل.',
                'userRow' => $userRow,
                'navTabs' => $this->navTabLabels(),
                'hiddenTabs' => $this->loadHiddenTabs($id),
                'permLabels' => $this->customPermissionLabels(),
                'customPerms' => $this->loadCustomPermissions($id),
            ]);
            return;
        }

        if ($newPass !== '') {
            $stmtU = $db->prepare('UPDATE users SET name=?, email=?, role=?, password_hash=?, updated_at=? WHERE id=?');
            try {
                $stmtU->execute([$name, $email, $role, password_hash($newPass, PASSWORD_DEFAULT), $now, $id]);
            } catch (\Throwable) {
                // Role fallback for installations where 'user' isn't accepted by DB schema.
                if ($role === 'user') {
                    try {
                        $stmtU->execute([$name, $email, 'viewer', password_hash($newPass, PASSWORD_DEFAULT), $now, $id]);
                        goto user_edit_ok_with_pass;
                    } catch (\Throwable) {
                        // fall through to error render
                    }
                }
                View::render('users/edit', [
                    'csrf' => Csrf::token(),
                    'error' => 'تعذر التعديل (قد يكون البريد مستخدمًا).',
                    'userRow' => $userRow,
                    'navTabs' => $this->navTabLabels(),
                    'hiddenTabs' => $this->loadHiddenTabs($id),
                    'permLabels' => $this->customPermissionLabels(),
                    'customPerms' => $this->loadCustomPermissions($id),
                ]);
                return;
            }
            user_edit_ok_with_pass:
        } else {
            $stmtU = $db->prepare('UPDATE users SET name=?, email=?, role=?, updated_at=? WHERE id=?');
            try {
                $stmtU->execute([$name, $email, $role, $now, $id]);
            } catch (\Throwable) {
                // Role fallback for installations where 'user' isn't accepted by DB schema.
                if ($role === 'user') {
                    try {
                        $stmtU->execute([$name, $email, 'viewer', $now, $id]);
                        goto user_edit_ok_no_pass;
                    } catch (\Throwable) {
                        // fall through to error render
                    }
                }
                View::render('users/edit', [
                    'csrf' => Csrf::token(),
                    'error' => 'تعذر التعديل (قد يكون البريد مستخدمًا).',
                    'userRow' => $userRow,
                    'navTabs' => $this->navTabLabels(),
                    'hiddenTabs' => $this->loadHiddenTabs($id),
                    'permLabels' => $this->customPermissionLabels(),
                    'customPerms' => $this->loadCustomPermissions($id),
                ]);
                return;
            }
            user_edit_ok_no_pass:
        }

        $this->saveHiddenTabs($id, $this->readHiddenTabsFromPost());
        $this->saveCustomPermissions($id, $this->readCustomPermissionsFromPost());

        $actor = $this->auth->user();
        if ($actor) {
            $this->auth->audit((int)$actor['id'], (string)$actor['name'], 'Edit', 'users', 'ID=' . $id . ' ' . $name . ' <' . $email . '>');
            
            // Send notification
            Notify::create(
                null,
                (int)$actor['id'],
                (string)$actor['name'],
                'update',
                'user',
                $id,
                $name,
                'تم تعديل بيانات المستخدم: ' . $name
            );
        }
        Http::redirect('/users?msg=updated');
    }

    public function toggleActive(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $currentUser = $this->auth->user();

        // Prevent disabling yourself
        if ($currentUser && (int)$currentUser['id'] === $id) {
            Http::redirect('/users');
            return;
        }

        $db = $this->db();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmtInfo = $db->prepare('SELECT name,email,is_active FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();
        $stmt = $db->prepare('UPDATE users SET is_active = IF(is_active=1,0,1), updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$now, $id]);

        if ($currentUser && $target) {
            $newState = ((int)$target['is_active'] === 1) ? 'disabled' : 'enabled';
            $this->auth->audit((int)$currentUser['id'], (string)$currentUser['name'], 'ToggleActive', 'users', 'ID=' . $id . ' ' . (string)$target['email'] . ' -> ' . $newState);
        }
        Http::redirect('/users?msg=toggled');
    }

    public function deleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $currentUser = $this->auth->user();

        // Prevent deleting yourself
        if ($currentUser && (int)$currentUser['id'] === $id) {
            Http::redirect('/users');
            return;
        }

        $db = $this->db();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmtInfo = $db->prepare('SELECT name,email FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();
        $stmt = $db->prepare('UPDATE users SET deleted_at=?, updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$now, $now, $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['undo_users'] = ['id' => $id, 't' => time()];
        }

        if ($currentUser && $target) {
            $this->auth->audit((int)$currentUser['id'], (string)$currentUser['name'], 'Delete', 'users', 'ID=' . $id . ' ' . (string)$target['email']);
            
            // Record change history
            Notify::recordChange(
                'user',
                $id,
                (int)$currentUser['id'],
                (string)$currentUser['name'],
                'delete',
                ['deleted' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$currentUser['id'],
                (string)$currentUser['name'],
                'delete',
                'user',
                $id,
                (string)($target['name'] ?? $target['email']),
                'تم حذف المستخدم: ' . (string)$target['email']
            );
        }
        Http::redirect('/users?msg=deleted');
    }

    public function undoDeleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $currentUser = $this->auth->user();
        if (!$currentUser) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $undo = $_SESSION['undo_users'] ?? null;
        if ($id <= 0 || !is_array($undo) || !isset($undo['id'], $undo['t']) || (int)$undo['id'] !== $id) {
            Http::redirect('/users');
            return;
        }

        $age = time() - (int)$undo['t'];
        if ($age < 0 || $age > self::UNDO_TTL_SECONDS) {
            unset($_SESSION['undo_users']);
            Http::redirect('/users');
            return;
        }

        $db = $this->db();
        $stmtInfo = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE users SET deleted_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$now, $id]);

        if ($stmt->rowCount() > 0) {
            $this->auth->audit((int)$currentUser['id'], (string)$currentUser['name'], 'Restore', 'users', 'ID=' . $id . ' ' . (string)($target['email'] ?? ''));
            
            // Record change history
            Notify::recordChange(
                'user',
                $id,
                (int)$currentUser['id'],
                (string)$currentUser['name'],
                'restore',
                ['restored' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$currentUser['id'],
                (string)$currentUser['name'],
                'restore',
                'user',
                $id,
                (string)($target['email'] ?? ''),
                'تم استعادة المستخدم: ' . (string)($target['email'] ?? '')
            );
        }

        unset($_SESSION['undo_users']);
        Http::redirect('/users?msg=restored');
    }


}

