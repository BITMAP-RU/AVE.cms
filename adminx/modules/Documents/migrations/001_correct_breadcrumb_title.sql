-- Canonicalize the historical document_breadcrum_title typo without losing data.
-- Both the active content schema and the dbpref legacy schema are handled; when
-- they point to the same table, the second pass is an idempotent no-op.

SET @ave_breadcrumb_table = '{{content_prefix}}_documents';
SET @ave_breadcrumb_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrum_title'
);
SET @ave_breadcrumb_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrumb_title'
);
SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_new = 0 AND @ave_breadcrumb_has_old = 1,
  CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` CHANGE COLUMN `document_breadcrum_title` `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT \'\''),
  IF(
    @ave_breadcrumb_has_new = 0,
    CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` ADD COLUMN `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `document_title`'),
    'SELECT 1'
  )
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

SET @ave_breadcrumb_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrum_title'
);
SET @ave_breadcrumb_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrumb_title'
);
SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_old = 1 AND @ave_breadcrumb_has_new = 1,
  CONCAT('UPDATE `', @ave_breadcrumb_table, '` SET `document_breadcrumb_title` = `document_breadcrum_title` WHERE `document_breadcrumb_title` = \'\' AND `document_breadcrum_title` <> \'\''),
  'SELECT 1'
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_old = 1 AND @ave_breadcrumb_has_new = 1,
  CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` DROP COLUMN `document_breadcrum_title`'),
  'SELECT 1'
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

SET @ave_breadcrumb_table = '{{database_prefix}}_documents';
SET @ave_breadcrumb_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrum_title'
);
SET @ave_breadcrumb_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrumb_title'
);
SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_new = 0 AND @ave_breadcrumb_has_old = 1,
  CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` CHANGE COLUMN `document_breadcrum_title` `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT \'\''),
  IF(
    @ave_breadcrumb_has_new = 0,
    CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` ADD COLUMN `document_breadcrumb_title` VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `document_title`'),
    'SELECT 1'
  )
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

SET @ave_breadcrumb_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrum_title'
);
SET @ave_breadcrumb_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_breadcrumb_table
    AND COLUMN_NAME = 'document_breadcrumb_title'
);
SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_old = 1 AND @ave_breadcrumb_has_new = 1,
  CONCAT('UPDATE `', @ave_breadcrumb_table, '` SET `document_breadcrumb_title` = `document_breadcrum_title` WHERE `document_breadcrumb_title` = \'\' AND `document_breadcrum_title` <> \'\''),
  'SELECT 1'
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

SET @ave_breadcrumb_sql = IF(
  @ave_breadcrumb_has_old = 1 AND @ave_breadcrumb_has_new = 1,
  CONCAT('ALTER TABLE `', @ave_breadcrumb_table, '` DROP COLUMN `document_breadcrum_title`'),
  'SELECT 1'
);
PREPARE ave_breadcrumb_stmt FROM @ave_breadcrumb_sql;
EXECUTE ave_breadcrumb_stmt;
DEALLOCATE PREPARE ave_breadcrumb_stmt;

UPDATE `{{content_prefix}}_sysblocks`
SET `sysblock_text` = REPLACE(
  `sysblock_text`,
  'document_breadcrum_title',
  'document_breadcrumb_title'
)
WHERE `sysblock_text` LIKE '%document_breadcrum_title%';

UPDATE `{{database_prefix}}_sysblocks`
SET `sysblock_text` = REPLACE(
  `sysblock_text`,
  'document_breadcrum_title',
  'document_breadcrumb_title'
)
WHERE `sysblock_text` LIKE '%document_breadcrum_title%';
