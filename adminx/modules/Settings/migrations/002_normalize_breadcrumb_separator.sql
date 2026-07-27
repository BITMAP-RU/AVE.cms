-- Normalize breadcrumb separator names across native and source settings.

INSERT INTO `{{system_prefix}}_settings` (`param`, `value`, `type`)
SELECT 'bread_separator', `value`, `type`
FROM `{{system_prefix}}_settings`
WHERE `param` = 'bread_sepparator'
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`);

DELETE FROM `{{system_prefix}}_settings`
WHERE `param` = 'bread_sepparator';

INSERT INTO `{{system_prefix}}_settings` (`param`, `value`, `type`)
SELECT 'bread_separator_use', `value`, `type`
FROM `{{system_prefix}}_settings`
WHERE `param` = 'bread_sepparator_use'
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`);

DELETE FROM `{{system_prefix}}_settings`
WHERE `param` = 'bread_sepparator_use';

SET @ave_rename_table = '{{public_shell_prefix}}_settings';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_sepparator')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_separator'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `bread_sepparator` `bread_separator` VARCHAR(255) NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_sepparator_use')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_separator_use'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `bread_sepparator_use` `bread_separator_use` ENUM(\'1\',\'0\') NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_table = '{{database_prefix}}_settings';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_sepparator')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_separator'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `bread_sepparator` `bread_separator` VARCHAR(255) NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_sepparator_use')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'bread_separator_use'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `bread_sepparator_use` `bread_separator_use` ENUM(\'1\',\'0\') NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_table = '{{content_prefix}}_rubric_breadcrumb';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'sepparator')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'separator'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `sepparator` `separator` VARCHAR(255) NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'sepparator_use')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'separator_use'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `sepparator_use` `separator_use` ENUM(\'1\',\'0\') NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_table = '{{database_prefix}}_rubric_breadcrumb';
SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'sepparator')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'separator'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `sepparator` `separator` VARCHAR(255) NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;

SET @ave_rename_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'sepparator_use')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @ave_rename_table AND COLUMN_NAME = 'separator_use'),
  CONCAT('ALTER TABLE `', @ave_rename_table, '` CHANGE COLUMN `sepparator_use` `separator_use` ENUM(\'1\',\'0\') NOT NULL'),
  'SELECT 1'
);
PREPARE ave_rename_stmt FROM @ave_rename_sql;
EXECUTE ave_rename_stmt;
DEALLOCATE PREPARE ave_rename_stmt;
