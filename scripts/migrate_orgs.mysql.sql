-- Migration: Multi-organization support
-- الهدف: إضافة جدول organizations + أعمدة org_id للجداول الأساسية.
-- ملاحظة: هذا الملف مخصص للتشغيل مرة واحدة على قاعدة بيانات قديمة.

-- تحديث (2026-03-09): الملف أصبح آمن لإعادة التشغيل.
-- إذا كان جدول organizations موجودًا بالفعل، سيتم تخطي إنشائه.
-- الأعمدة/الفهارس/القيود ستُضاف فقط إذا كانت غير موجودة.

CREATE TABLE IF NOT EXISTS organizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- assets.org_id
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'org_id') = 0,
    'ALTER TABLE assets ADD COLUMN org_id INT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'org_id' AND INDEX_NAME <> 'PRIMARY') = 0,
    'ALTER TABLE assets ADD INDEX idx_assets_org_id (org_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'org_id' AND REFERENCED_TABLE_NAME = 'organizations') = 0,
    'ALTER TABLE assets ADD CONSTRAINT fk_assets_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- employees.org_id
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'org_id') = 0,
    'ALTER TABLE employees ADD COLUMN org_id INT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'org_id' AND INDEX_NAME <> 'PRIMARY') = 0,
    'ALTER TABLE employees ADD INDEX idx_employees_org_id (org_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'org_id' AND REFERENCED_TABLE_NAME = 'organizations') = 0,
    'ALTER TABLE employees ADD CONSTRAINT fk_employees_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- custody.org_id
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custody' AND COLUMN_NAME = 'org_id') = 0,
    'ALTER TABLE custody ADD COLUMN org_id INT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custody' AND COLUMN_NAME = 'org_id' AND INDEX_NAME <> 'PRIMARY') = 0,
    'ALTER TABLE custody ADD INDEX idx_custody_org_id (org_id)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custody' AND COLUMN_NAME = 'org_id' AND REFERENCED_TABLE_NAME = 'organizations') = 0,
    'ALTER TABLE custody ADD CONSTRAINT fk_custody_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
