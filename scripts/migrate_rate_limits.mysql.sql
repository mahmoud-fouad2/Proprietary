-- Rate Limits Table
-- Run this migration to enable comprehensive rate limiting

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    limit_key VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_rate_limits_key_ip (limit_key, ip),
    INDEX idx_rate_limits_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add event to cleanup old rate limit records daily (requires EVENT scheduler enabled)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_rate_limits
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 3 HOUR
DO
BEGIN
    DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    DELETE FROM auth_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$
DELIMITER ;

-- Alternative: Manual cleanup query (run via cron or scheduled task)
-- DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
-- DELETE FROM auth_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
