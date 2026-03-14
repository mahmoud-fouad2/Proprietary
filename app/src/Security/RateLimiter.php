<?php
declare(strict_types=1);

namespace Zaco\Security;

use PDO;

/**
 * Rate Limiter Service
 * Provides rate limiting for various actions beyond login
 */
final class RateLimiter
{
    private PDO $db;
    private string $ip;

    // Rate limits: [max_attempts, window_seconds]
    private const LIMITS = [
        'login' => [5, 300],          // 5 attempts per 5 minutes
        'password_reset' => [3, 3600], // 3 attempts per hour
        'api' => [100, 60],            // 100 requests per minute
        'export' => [10, 300],         // 10 exports per 5 minutes
        'upload' => [20, 60],          // 20 uploads per minute
        'form_submit' => [30, 60],     // 30 form submissions per minute
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ip = $this->getClientIp();
    }

    /**
     * Check if an action is rate limited
     */
    public function isLimited(string $action, ?string $identifier = null): bool
    {
        $limit = self::LIMITS[$action] ?? self::LIMITS['form_submit'];
        [$maxAttempts, $windowSeconds] = $limit;

        $key = $this->buildKey($action, $identifier);
        $count = $this->getAttemptCount($key, $windowSeconds);

        return $count >= $maxAttempts;
    }

    /**
     * Record an attempt for rate limiting
     */
    public function hit(string $action, ?string $identifier = null, bool $success = true): void
    {
        if (!$this->tableExists()) {
            return; // Silently skip if table doesn't exist
        }

        $key = $this->buildKey($action, $identifier);
        
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO rate_limits (limit_key, ip, success, created_at) VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$key, $this->ip, $success ? 1 : 0]);
        } catch (\Throwable) {
            // Silently ignore - rate limiting should not break functionality
        }
    }

    /**
     * Get remaining attempts for an action
     */
    public function remaining(string $action, ?string $identifier = null): int
    {
        $limit = self::LIMITS[$action] ?? self::LIMITS['form_submit'];
        [$maxAttempts, $windowSeconds] = $limit;

        $key = $this->buildKey($action, $identifier);
        $count = $this->getAttemptCount($key, $windowSeconds);

        return max(0, $maxAttempts - $count);
    }

    /**
     * Get time until rate limit resets (in seconds)
     */
    public function retryAfter(string $action, ?string $identifier = null): int
    {
        $limit = self::LIMITS[$action] ?? self::LIMITS['form_submit'];
        [, $windowSeconds] = $limit;

        $key = $this->buildKey($action, $identifier);
        
        try {
            $stmt = $this->db->prepare(
                'SELECT MIN(created_at) as oldest FROM rate_limits WHERE limit_key = ? AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $stmt->execute([$key, $this->ip, $windowSeconds]);
            $row = $stmt->fetch();
            
            if ($row && $row['oldest']) {
                $oldest = strtotime($row['oldest']);
                $resetTime = $oldest + $windowSeconds;
                return max(0, $resetTime - time());
            }
        } catch (\Throwable) {
            // Ignore errors
        }

        return 0;
    }

    /**
     * Clear rate limit records for an action
     */
    public function clear(string $action, ?string $identifier = null): void
    {
        $key = $this->buildKey($action, $identifier);
        
        try {
            $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE limit_key = ? AND ip = ?');
            $stmt->execute([$key, $this->ip]);
        } catch (\Throwable) {
            // Ignore
        }
    }

    /**
     * Cleanup old rate limit records
     */
    public function cleanup(int $olderThanSeconds = 86400): int
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $stmt->execute([$olderThanSeconds]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Middleware-style check that sends 429 response if limited
     */
    public function enforce(string $action, ?string $identifier = null): void
    {
        if ($this->isLimited($action, $identifier)) {
            $retryAfter = $this->retryAfter($action, $identifier);
            
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'error' => 'Too many requests',
                'message' => 'تم تجاوز الحد المسموح من المحاولات. يرجى المحاولة لاحقاً.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            
            exit;
        }
    }

    /**
     * Build rate limit key
     */
    private function buildKey(string $action, ?string $identifier): string
    {
        $key = 'rl:' . $action;
        if ($identifier !== null && $identifier !== '') {
            $key .= ':' . $identifier;
        }
        return $key;
    }

    /**
     * Get attempt count within time window
     */
    private function getAttemptCount(string $key, int $windowSeconds): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) as c FROM rate_limits WHERE limit_key = ? AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $stmt->execute([$key, $this->ip, $windowSeconds]);
            $row = $stmt->fetch();
            return (int)($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Check if rate_limits table exists
     */
    private function tableExists(): bool
    {
        static $exists = null;
        
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $this->db->query(
                "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rate_limits' LIMIT 1"
            );
            $exists = (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? null;
            if ($ip !== null && $ip !== '') {
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Create the rate_limits table if it doesn't exist
     */
    public static function createTable(PDO $db): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            limit_key VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX idx_rate_limits_key_ip (limit_key, ip),
            INDEX idx_rate_limits_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $db->exec($sql);
    }
}
