CREATE TABLE IF NOT EXISTS `{{content_prefix}}_rubric_admin_views` (
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL,
  `mode` VARCHAR(20) NOT NULL DEFAULT 'standard',
  `config_json` LONGTEXT NOT NULL,
  `updated_by` INT(10) UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`rubric_id`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @ave_admin_view_table = '{{content_prefix}}_rubrics';
SET @ave_admin_view_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @ave_admin_view_table
      AND COLUMN_NAME = 'rubric_admin_teaser_template'
  ),
  CONCAT('ALTER TABLE `', @ave_admin_view_table, '` DROP COLUMN `rubric_admin_teaser_template`'),
  'SELECT 1'
);
PREPARE ave_admin_view_stmt FROM @ave_admin_view_sql;
EXECUTE ave_admin_view_stmt;
DEALLOCATE PREPARE ave_admin_view_stmt;

SET @ave_admin_view_table = '{{database_prefix}}_rubrics';
SET @ave_admin_view_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @ave_admin_view_table
      AND COLUMN_NAME = 'rubric_admin_teaser_template'
  ),
  CONCAT('ALTER TABLE `', @ave_admin_view_table, '` DROP COLUMN `rubric_admin_teaser_template`'),
  'SELECT 1'
);
PREPARE ave_admin_view_stmt FROM @ave_admin_view_sql;
EXECUTE ave_admin_view_stmt;
DEALLOCATE PREPARE ave_admin_view_stmt;
