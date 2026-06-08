-- Migration: Forgot Password tanpa tabel terpisah
-- Jalankan SQL ini di phpMyAdmin hosting untuk DST Recruitment.
-- Aman dijalankan ulang karena kolom/index dicek terlebih dahulu.

SET @reset_token_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'reset_token'
);
SET @sql := IF(
  @reset_token_exists = 0,
  'ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER status',
  'SELECT "reset_token already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reset_token_expires_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'reset_token_expires'
);
SET @sql := IF(
  @reset_token_expires_exists = 0,
  'ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token',
  'SELECT "reset_token_expires already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reset_token_index_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_reset_token'
);
SET @sql := IF(
  @reset_token_index_exists = 0,
  'CREATE INDEX idx_users_reset_token ON users(reset_token)',
  'SELECT "idx_users_reset_token already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS password_resets;
