-- ZACO Assets - Asset Structure Migration (Categories/Sections/Subsections)
-- Safe to run multiple times (idempotent-ish checks via INFORMATION_SCHEMA)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Create lookup tables
CREATE TABLE IF NOT EXISTS asset_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_sections_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_subsections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_subsections_section_name (section_id, name),
  KEY idx_asset_subsections_section_id (section_id),
  CONSTRAINT fk_asset_subsections_section FOREIGN KEY (section_id) REFERENCES asset_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Add columns to assets (if missing)
SET @has_category_id := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND column_name = 'category_id'
);
SET @sql := IF(@has_category_id = 0,
  'ALTER TABLE assets ADD COLUMN category_id BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_section_id := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND column_name = 'section_id'
);
SET @sql := IF(@has_section_id = 0,
  'ALTER TABLE assets ADD COLUMN section_id BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_subsection_id := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND column_name = 'subsection_id'
);
SET @sql := IF(@has_subsection_id = 0,
  'ALTER TABLE assets ADD COLUMN subsection_id BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Indexes on new columns (if missing)
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND index_name = 'idx_assets_category_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE assets ADD KEY idx_assets_category_id (category_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND index_name = 'idx_assets_section_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE assets ADD KEY idx_assets_section_id (section_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'assets' AND index_name = 'idx_assets_subsection_id'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE assets ADD KEY idx_assets_subsection_id (subsection_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Foreign keys (if missing)
SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.key_column_usage
  WHERE constraint_schema = DATABASE() AND table_name = 'assets' AND column_name = 'category_id' AND referenced_table_name = 'asset_categories'
);
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE assets ADD CONSTRAINT fk_assets_category FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.key_column_usage
  WHERE constraint_schema = DATABASE() AND table_name = 'assets' AND column_name = 'section_id' AND referenced_table_name = 'asset_sections'
);
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE assets ADD CONSTRAINT fk_assets_section FOREIGN KEY (section_id) REFERENCES asset_sections(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.key_column_usage
  WHERE constraint_schema = DATABASE() AND table_name = 'assets' AND column_name = 'subsection_id' AND referenced_table_name = 'asset_subsections'
);
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE assets ADD CONSTRAINT fk_assets_subsection FOREIGN KEY (subsection_id) REFERENCES asset_subsections(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Backfill categories from existing assets.category (string)
INSERT INTO asset_categories (name, created_at, updated_at)
SELECT DISTINCT a.category, NOW(), NOW()
FROM assets a
WHERE a.category IS NOT NULL AND a.category <> ''
  AND NOT EXISTS (SELECT 1 FROM asset_categories c WHERE c.name = a.category);

UPDATE assets a
JOIN asset_categories c ON c.name = a.category
SET a.category_id = c.id
WHERE (a.category_id IS NULL OR a.category_id = 0)
  AND a.category IS NOT NULL AND a.category <> '';
