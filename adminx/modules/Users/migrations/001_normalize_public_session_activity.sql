-- Correct last_activ in public remember-session tables without changing data.

SET @ave_rename_table = '{{public_user_prefix}}_users_session';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'last_activ')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'last_active'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `last_activ` `last_active` INT(11) NOT NULL DEFAULT 0'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_table = '{{database_prefix}}_users_session';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'last_activ')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'last_active'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `last_activ` `last_active` INT(11) NOT NULL DEFAULT 0'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;
