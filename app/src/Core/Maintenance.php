<?php
declare(strict_types=1);

namespace Zaco\Core;

use PDO;

/**
 * Database Maintenance Service
 * Handles cleanup of old records and database optimization
 */
final class Maintenance
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Run all cleanup tasks
     * @return array<string,int|string>
     */
    public function runAll(): array
    {
        $results = [];

        $results['auth_attempts'] = $this->cleanupAuthAttempts();
        $results['rate_limits'] = $this->cleanupRateLimits();
        $results['soft_deleted'] = $this->cleanupSoftDeleted();
        $results['audit_log'] = $this->cleanupAuditLog();
        $results['notifications'] = $this->cleanupNotifications();

        return $results;
    }

    /**
     * Cleanup old authentication attempts
     */
    public function cleanupAuthAttempts(int $daysOld = 7): int
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM auth_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
            );
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Cleanup old rate limit records
     */
    public function cleanupRateLimits(int $hoursOld = 24): int
    {
        if (!$this->tableExists('rate_limits')) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)'
            );
            $stmt->execute([$hoursOld]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Permanently delete soft-deleted records older than X days
     */
    public function cleanupSoftDeleted(int $daysOld = 30): int
    {
        $tables = ['assets', 'employees', 'custody', 'software_library', 'users'];
        $total = 0;

        foreach ($tables as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'deleted_at')) {
                continue;
            }

            try {
                $stmt = $this->db->prepare(
                    "DELETE FROM `{$table}` WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
                );
                $stmt->execute([$daysOld]);
                $total += $stmt->rowCount();
            } catch (\Throwable) {
                // Continue with other tables
            }
        }

        return $total;
    }

    /**
     * Cleanup old audit log entries
     */
    public function cleanupAuditLog(int $daysOld = 90): int
    {
        if (!$this->tableExists('audit_log')) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
            );
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Cleanup old read notifications
     */
    public function cleanupNotifications(int $daysOld = 30): int
    {
        if (!$this->tableExists('notifications')) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
            );
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get database statistics
     * @return array<string,mixed>
     */
    public function getStats(): array
    {
        $stats = [];

        $tables = ['assets', 'employees', 'custody', 'software_library', 'users', 'audit_log', 'notifications'];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            try {
                $stmt = $this->db->query("SELECT COUNT(*) as c FROM `{$table}`");
                $row = $stmt->fetch();
                $stats[$table] = (int)($row['c'] ?? 0);

                // Get soft-deleted count
                if ($this->columnExists($table, 'deleted_at')) {
                    $stmt = $this->db->query("SELECT COUNT(*) as c FROM `{$table}` WHERE deleted_at IS NOT NULL");
                    $row = $stmt->fetch();
                    $stats[$table . '_deleted'] = (int)($row['c'] ?? 0);
                }
            } catch (\Throwable) {
                $stats[$table] = 'error';
            }
        }

        return $stats;
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(): void
    {
        $tables = ['assets', 'employees', 'custody', 'software_library', 'users', 'audit_log', 'auth_attempts'];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            try {
                $this->db->exec("OPTIMIZE TABLE `{$table}`");
            } catch (\Throwable) {
                // Continue with other tables
            }
        }
    }

    /**
     * Check if table exists
     */
    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
            );
            $stmt->execute([$table]);
            $cache[$table] = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /**
     * Check if column exists in table
     */
    private function columnExists(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        static $cache = [];

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
            );
            $stmt->execute([$table, $column]);
            $cache[$key] = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}
