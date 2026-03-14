-- ZACO Assets (MySQL)
-- Charset & collation recommended: utf8mb4 / utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin','admin','editor','viewer','cleaner') NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(190) NOT NULL,
  action VARCHAR(64) NOT NULL,
  table_name VARCHAR(64) NOT NULL,
  details TEXT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(64) NOT NULL,
  email VARCHAR(190) NULL,
  success TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attempts_ip_created_at (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(190) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organizations (multi-org filtering)
CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_org_name (name),
  KEY idx_org_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Core modules (assets/employees/custody/software) - created in Phase 2
CREATE TABLE IF NOT EXISTS assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  org_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(190) NOT NULL,
  asset_type ENUM('general','vehicle') NOT NULL DEFAULT 'general',
  image_path VARCHAR(255) NULL,
  code VARCHAR(64) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  min_quantity INT NOT NULL DEFAULT 1,
  purchase_date DATE NULL,
  supplier VARCHAR(190) NULL,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  asset_condition ENUM('excellent','good','fair','poor','disposed') NOT NULL DEFAULT 'good',
  location VARCHAR(190) NULL,
  plate_number VARCHAR(64) NULL,
  vehicle_model VARCHAR(190) NULL,
  vehicle_year INT NULL,
  mileage INT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assets_code (code),
  KEY idx_assets_category (category),
  KEY idx_assets_org_id (org_id),
  CONSTRAINT fk_assets_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  org_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(190) NOT NULL,
  employee_no VARCHAR(64) NOT NULL,
  department VARCHAR(190) NULL,
  job_title VARCHAR(190) NULL,
  hire_date DATE NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  phone VARCHAR(64) NULL,
  email VARCHAR(190) NULL,
  national_id VARCHAR(64) NULL,
  contract_type ENUM('permanent','temporary','parttime','freelance') NOT NULL DEFAULT 'permanent',
  emp_status ENUM('active','suspended','resigned','terminated') NOT NULL DEFAULT 'active',
  device_password VARCHAR(255) NULL,
  photo VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_employee_no (employee_no),
  KEY idx_employees_org_id (org_id),
  CONSTRAINT fk_employees_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custody (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  org_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  employee_name VARCHAR(190) NOT NULL,
  item_name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  serial_number VARCHAR(190) NULL,
  attachment_name VARCHAR(190) NULL,
  attachment_path VARCHAR(255) NULL,
  date_assigned DATE NOT NULL,
  date_returned DATE NULL,
  custody_status ENUM('active','returned','damaged','lost') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_custody_employee_id (employee_id),
  KEY idx_custody_org_id (org_id),
  CONSTRAINT fk_custody_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_custody_org FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS software_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(190) NULL,
  version VARCHAR(190) NULL,
  description TEXT NULL,
  file_path VARCHAR(255) NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  is_free TINYINT(1) NOT NULL DEFAULT 1,
  license_key TEXT NULL,
  download_url TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleaning module (Phase 2)
CREATE TABLE IF NOT EXISTS cleaning_places (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  place_name VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cleaning_checks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cleaner_user_id BIGINT UNSIGNED NOT NULL,
  place_id BIGINT UNSIGNED NOT NULL,
  check_date DATE NOT NULL,
  checked_at DATETIME NOT NULL,
  photo_path VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cleaning_unique (cleaner_user_id, place_id, check_date),
  KEY idx_cleaning_date (check_date),
  CONSTRAINT fk_cleaning_user FOREIGN KEY (cleaner_user_id) REFERENCES users(id),
  CONSTRAINT fk_cleaning_place FOREIGN KEY (place_id) REFERENCES cleaning_places(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employee records (reports / behavior) + awards (certificate)
CREATE TABLE IF NOT EXISTS employee_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  note_date DATE NULL,
  note_text TEXT NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_emp_notes_employee_id (employee_id),
  KEY idx_emp_notes_created_at (created_at),
  CONSTRAINT fk_emp_notes_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  period_from DATE NULL,
  period_to DATE NULL,
  title VARCHAR(190) NOT NULL,
  report_text TEXT NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_emp_reports_employee_id (employee_id),
  KEY idx_emp_reports_created_at (created_at),
  CONSTRAINT fk_emp_reports_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_awards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  issue_date DATE NULL,
  award_title VARCHAR(190) NOT NULL,
  award_text TEXT NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_emp_awards_employee_id (employee_id),
  KEY idx_emp_awards_created_at (created_at),
  CONSTRAINT fk_emp_awards_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleaning daily report to admin (comment + printable with photos)
CREATE TABLE IF NOT EXISTS cleaning_daily_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cleaner_user_id BIGINT UNSIGNED NOT NULL,
  report_date DATE NOT NULL,
  comment TEXT NULL,
  submitted_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cleaning_daily_unique (cleaner_user_id, report_date),
  KEY idx_cleaning_daily_date (report_date),
  CONSTRAINT fk_cleaning_daily_user FOREIGN KEY (cleaner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
