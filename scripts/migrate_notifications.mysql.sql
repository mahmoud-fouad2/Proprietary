-- Notifications & Change History Migration
-- Run this to enable notifications and detailed change tracking

SET NAMES utf8mb4;

-- Notifications table (for navbar bell)
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL COMMENT 'NULL = for all admins',
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(190) NOT NULL,
  type ENUM('create','update','delete','login','restore') NOT NULL DEFAULT 'create',
  entity_type VARCHAR(64) NOT NULL COMMENT 'employees, assets, custody, software, users',
  entity_id BIGINT UNSIGNED NULL,
  entity_name VARCHAR(190) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notif_user_read (user_id, is_read),
  KEY idx_notif_created (created_at),
  KEY idx_notif_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Change history (detailed field-level tracking)
CREATE TABLE IF NOT EXISTS change_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(190) NOT NULL,
  action ENUM('create','update','delete','restore') NOT NULL,
  changes JSON NULL COMMENT 'Old/new values for each changed field',
  ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ch_entity (entity_type, entity_id),
  KEY idx_ch_created (created_at),
  KEY idx_ch_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for soft delete queries
ALTER TABLE employees ADD INDEX idx_employees_deleted (deleted_at);
ALTER TABLE assets ADD INDEX idx_assets_deleted (deleted_at);
ALTER TABLE custody ADD INDEX idx_custody_deleted (deleted_at);
ALTER TABLE software_library ADD INDEX idx_software_deleted (deleted_at);
ALTER TABLE users ADD INDEX idx_users_deleted (deleted_at);
