<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use PDO;
use Zaco\Core\Config;

abstract class BaseController
{
    /** @var array<string,bool> */
    private static array $tableExistsCache = [];

    /** @var array<string,bool> */
    private static array $columnExistsCache = [];

    protected function db(): PDO
    {
        /** @var PDO $db */
        $db = $GLOBALS['db'] ?? null;
        if ($db instanceof PDO) return $db;
        throw new \RuntimeException('DB not available');
    }

    protected function hasTable(PDO $db, string $table): bool
    {
        $key = mb_strtolower($table);
        if (array_key_exists($key, self::$tableExistsCache)) {
            return self::$tableExistsCache[$key];
        }

        try {
            $stmt = $db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $stmt->execute([$table]);
            $ok = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        self::$tableExistsCache[$key] = $ok;
        return $ok;
    }

    protected function clearHasTableCache(string $table): void
    {
        $key = mb_strtolower($table);
        unset(self::$tableExistsCache[$key]);
    }

    protected function hasColumn(PDO $db, string $table, string $column): bool
    {
        $key = mb_strtolower($table . '.' . $column);
        if (array_key_exists($key, self::$columnExistsCache)) {
            return self::$columnExistsCache[$key];
        }

        try {
            $stmt = $db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
            $stmt->execute([$table, $column]);
            $ok = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        self::$columnExistsCache[$key] = $ok;
        return $ok;
    }

    protected function clearHasColumnCache(string $table, string $column): void
    {
        $key = mb_strtolower($table . '.' . $column);
        unset(self::$columnExistsCache[$key]);
    }

    protected function orgFeatureEnabled(PDO $db, string $tableWithOrgId): bool
    {
        return $this->hasTable($db, 'organizations') && $this->hasColumn($db, $tableWithOrgId, 'org_id');
    }

    protected function projectRoot(): string
    {
        return rtrim((string)dirname(__DIR__, 3), '/\\');
    }

    protected function storageRoot(): string
    {
        $cfg = $GLOBALS['config'] ?? null;
        if ($cfg instanceof Config) {
            $override = trim((string)$cfg->get('app.storage_dir', ''));
            if ($override !== '') {
                return rtrim($override, '/\\');
            }
        }

        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'storage';
    }

    protected function uploadsRoot(): string
    {
        $cfg = $GLOBALS['config'] ?? null;
        if ($cfg instanceof Config) {
            $override = trim((string)$cfg->get('app.uploads_dir', ''));
            if ($override !== '') {
                return rtrim($override, '/\\');
            }
        }

        return $this->storageRoot() . DIRECTORY_SEPARATOR . 'uploads';
    }
}
