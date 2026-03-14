<?php
declare(strict_types=1);

namespace Zaco\Security;

use DateTimeImmutable;
use PDO;

final class Auth
{
    public function __construct(private readonly PDO $db)
    {
        // Expose for middleware (Router uses $GLOBALS)
        $GLOBALS['auth'] = $this;
    }

    private bool $userCacheLoaded = false;

    /** @var array<string,mixed>|null */
    private ?array $userCache = null;

    private ?int $userCacheUid = null;

    /** @var array<int, list<string>> */
    private array $navHiddenCache = [];

    /** @var array<int, list<string>> */
    private array $customPermsCache = [];

    private function invalidateUserCache(): void
    {
        $this->userCacheLoaded = false;
        $this->userCache = null;
        $this->userCacheUid = null;
    }

    private function isRateLimited(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        // Block if too many failed attempts in last 5 minutes.
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM auth_attempts WHERE ip = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0) >= 10;
    }

    public function check(): bool
    {
        return isset($_SESSION['uid']) && is_int($_SESSION['uid']);
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        $uid = (int)$_SESSION['uid'];
        if ($this->userCacheLoaded && $this->userCacheUid === $uid) {
            return $this->userCache;
        }

        $stmt = $this->db->prepare('SELECT id,name,email,role,is_active,must_change_password,last_login_at FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        $this->userCacheLoaded = true;
        $this->userCacheUid = $uid;
        $this->userCache = $row ?: null;
        return $this->userCache;
    }

    private function normalizedRole(?string $role): string
    {
        $role = (string)($role ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return 'admin';
        }
        return 'user';
    }

    public function hasRole(string $minRole): bool
    {
        $u = $this->user();
        if (!$u) return false;

        $role = $this->normalizedRole((string)($u['role'] ?? ''));
        $need = $this->normalizedRole($minRole);
        return $role === 'admin' || $need === 'user';
    }

    public function can(string $permission): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }

        if ((int)$u['is_active'] !== 1) {
            return false;
        }

        $role = $this->normalizedRole((string)($u['role'] ?? ''));

        // Admin has full access.
        if ($role === 'admin') {
            return true;
        }

        // User: allow per-user custom permissions override if configured.
        {
            $uid = (int)($u['id'] ?? 0);
            $custom = $this->getCustomPermissionsForUser($uid);
            if ($custom !== []) {
                // Always allow dashboard for logged-in users
                if ($permission === 'view_dashboard') {
                    return true;
                }
                return in_array($permission, $custom, true);
            }
        }

        // Default (no custom perms): safe baseline for normal users.
        return match ($permission) {
            'view_dashboard' => true,
            'view_assets', 'view_employees', 'view_custody', 'view_software', 'cleaning' => true,
            default => false,
        };
    }

    /** @return list<string> */
    public function getCustomPermissionsForUser(int $userId): array
    {
        if ($userId <= 0) return [];
        if (isset($this->customPermsCache[$userId])) {
            return $this->customPermsCache[$userId];
        }

        $key = 'perms:user:' . $userId;
        try {
            $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $raw = (string)($row['setting_value'] ?? '');
        } catch (\Throwable) {
            $raw = '';
        }

        $list = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (is_string($v) && $v !== '') {
                        $list[] = $v;
                    }
                }
            }
        }

        $list = array_values(array_unique($list));
        $this->customPermsCache[$userId] = $list;
        return $list;
    }

    public function isAdminLike(): bool
    {
        $u = $this->user();
        if (!$u) return false;
        return $this->normalizedRole((string)($u['role'] ?? '')) === 'admin';
    }

    /**
     * Controls navigation visibility (UI only).
     * - Admin/superadmin are exempt (always visible).
     * - For other users, tabs can be hidden regardless of permission.
     */
    public function navVisible(string $tabKey): bool
    {
        $u = $this->user();
        if (!$u) return false;
        if ($this->isAdminLike()) return true;

        $uid = (int)($u['id'] ?? 0);
        if ($uid <= 0) return true;
        $hidden = $this->getHiddenTabsForUser($uid);
        return !in_array($tabKey, $hidden, true);
    }

    /** @return list<string> */
    public function getHiddenTabsForUser(int $userId): array
    {
        if ($userId <= 0) return [];
        if (isset($this->navHiddenCache[$userId])) {
            return $this->navHiddenCache[$userId];
        }

        $key = 'nav_hidden:user:' . $userId;
        try {
            $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $raw = (string)($row['setting_value'] ?? '');
        } catch (\Throwable) {
            $raw = '';
        }

        $list = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (is_string($v) && $v !== '') {
                        $list[] = $v;
                    }
                }
            }
        }

        $list = array_values(array_unique($list));
        $this->navHiddenCache[$userId] = $list;
        return $list;
    }

    public function login(string $email, string $password): bool
    {
        $email = trim(mb_strtolower($email));
        if ($email === '' || $password === '') {
            return false;
        }

        $this->invalidateUserCache();

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($this->isRateLimited($ip)) {
            // Silently refuse (avoid user enumeration / leaking thresholds)
            return false;
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        $ok = false;
        if ($u && (int)$u['is_active'] === 1 && password_verify($password, (string)$u['password_hash'])) {
            $ok = true;
        }

        $stmt2 = $this->db->prepare('INSERT INTO auth_attempts (ip,email,success,created_at) VALUES (?,?,?,?)');
        $stmt2->execute([$ip, $email, $ok ? 1 : 0, (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);

        if (!$ok) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];

        $this->invalidateUserCache();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt3 = $this->db->prepare('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?');
        $stmt3->execute([$now, $now, (int)$u['id']]);

        $this->audit((int)$u['id'], (string)$u['name'], 'Login', 'users', (string)$u['email']);

        return true;
    }

    public function logout(): void
    {
        $u = $this->user();
        if ($u) {
            $this->audit((int)$u['id'], (string)$u['name'], 'Logout', 'users', (string)$u['email']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();

        $this->invalidateUserCache();
    }

    public function usersCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL');
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    }

    public function createInitialSuperAdmin(string $name, string $email, string $password): void
    {
        if ($this->usersCount() > 0) {
            throw new \RuntimeException('Setup already completed');
        }
        $name = trim($name);
        $email = trim(mb_strtolower($email));
        if ($name === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException('Missing fields');
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO users (name,email,password_hash,role,is_active,must_change_password,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', 1, 0, $now, $now]);

        $uid = (int)$this->db->lastInsertId();
        $this->audit($uid, $name, 'InitialSetup', 'users', $email);
    }

    public function audit(?int $actorUserId, string $actorName, string $action, string $table, ?string $details): void
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO audit_log (actor_user_id,actor_name,action,table_name,details,ip,user_agent,created_at) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$actorUserId, $actorName, $action, $table, $details, $ip, mb_substr($ua, 0, 255), $now]);
    }
}
