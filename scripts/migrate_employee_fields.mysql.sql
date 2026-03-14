-- Migration: Add device_password and photo to employees

ALTER TABLE employees
  ADD COLUMN device_password VARCHAR(255) NULL AFTER emp_status,
  ADD COLUMN photo VARCHAR(255) NULL AFTER device_password;
