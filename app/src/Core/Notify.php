<?php
declare(strict_types=1);

namespace Zaco\Core;

use DateTimeImmutable;
use PDO;

/**
 * Notification & Change History Service
 */
final class Notify
{
    private static ?PDO $db = null;

    private static function db(): PDO
    {
        if (self::$db === null) {
            self::$db = $GLOBALS['db'] ?? throw new \RuntimeException('DB not available');
        }
        return self::$db;
    }

    /**
     * Check if notifications table exists
     */
    public static function enabled(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $stmt = self::db()->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' LIMIT 1");
            $ok = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * Check if change_history table exists
     */
    public static function historyEnabled(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $stmt = self::db()->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'change_history' LIMIT 1");
            $ok = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * Create a notification
     * @param int|null $userId Target user (null = for all admins)
     */
    public static function create(
        ?int $userId,
        ?int $actorUserId,
        string $actorName,
        string $type,
        string $entityType,
        ?int $entityId,
        ?string $entityName,
        string $message
    ): void {
        if (!self::enabled()) return;

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $stmt = self::db()->prepare(
                'INSERT INTO notifications (user_id, actor_user_id, actor_name, type, entity_type, entity_id, entity_name, message, is_read, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
            );
            $stmt->execute([$userId, $actorUserId, $actorName, $type, $entityType, $entityId, $entityName, $message, $now]);
        } catch (\Throwable) {
            // Silently fail
        }
    }

    /**
     * Get unread notifications for user
     * Admin sees all (user_id IS NULL), regular users see only their own
     */
    public static function getUnread(int $userId, bool $isAdmin, int $limit = 20): array
    {
        if (!self::enabled()) return [];

        try {
            if ($isAdmin) {
                // Admin sees all notifications (both global and personal)
                $stmt = self::db()->prepare(
                    'SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT ?'
                );
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            } else {
                // Regular user sees only their own
                $stmt = self::db()->prepare(
                    'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?'
                );
                $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count unread notifications
     */
    public static function countUnread(int $userId, bool $isAdmin): int
    {
        if (!self::enabled()) return 0;

        try {
            if ($isAdmin) {
                $stmt = self::db()->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
            } else {
                $stmt = self::db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
                $stmt->execute([$userId]);
            }
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Mark all as read
     */
    public static function markAllRead(int $userId, bool $isAdmin): void
    {
        if (!self::enabled()) return;

        try {
            if ($isAdmin) {
                self::db()->exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
            } else {
                $stmt = self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
                $stmt->execute([$userId]);
            }
        } catch (\Throwable) {
            // Silently fail
        }
    }

    /**
     * Mark single notification as read
     */
    public static function markRead(int $notificationId, int $userId, bool $isAdmin): void
    {
        if (!self::enabled()) return;

        try {
            if ($isAdmin) {
                $stmt = self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
                $stmt->execute([$notificationId]);
            } else {
                $stmt = self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
                $stmt->execute([$notificationId, $userId]);
            }
        } catch (\Throwable) {
            // Silently fail
        }
    }

    /**
     * Record a change in history
     * @param array|null $changes ['field' => ['old' => x, 'new' => y], ...]
     */
    public static function recordChange(
        string $entityType,
        int $entityId,
        ?int $actorUserId,
        string $actorName,
        string $action,
        ?array $changes = null
    ): void {
        if (!self::historyEnabled()) return;

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $changesJson = $changes !== null ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;

        try {
            $stmt = self::db()->prepare(
                'INSERT INTO change_history (entity_type, entity_id, actor_user_id, actor_name, action, changes, ip, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$entityType, $entityId, $actorUserId, $actorName, $action, $changesJson, $ip, $now]);
        } catch (\Throwable) {
            // Silently fail
        }
    }

    /**
     * Get change history for an entity
     */
    public static function getHistory(string $entityType, int $entityId, int $limit = 50): array
    {
        if (!self::historyEnabled()) return [];

        try {
            $stmt = self::db()->prepare(
                'SELECT * FROM change_history WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->bindValue(1, $entityType);
            $stmt->bindValue(2, $entityId, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Helper: compare two arrays and return changes
     */
    public static function diff(array $old, array $new, array $trackFields): array
    {
        $changes = [];
        foreach ($trackFields as $field) {
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if ($oldVal !== $newVal) {
                $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $changes;
    }

    /**
     * Generate action labels (AR)
     */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'restore' => 'استرجاع',
            'login' => 'تسجيل دخول',
            default => $action,
        };
    }

    /**
     * Generate entity type labels (AR)
     */
    public static function entityLabel(string $type): string
    {
        return match ($type) {
            'employees' => 'موظف',
            'assets' => 'أصل',
            'custody' => 'عهدة',
            'software' => 'برنامج',
            'users' => 'مستخدم',
            default => $type,
        };
    }
}
