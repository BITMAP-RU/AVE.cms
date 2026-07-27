-- Replace the ambiguous legacy teaser property with the canonical document
-- excerpt while preserving any existing content.

SET @ave_excerpt_table = '{{content_prefix}}_documents';
SET @ave_excerpt_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_excerpt_table
    AND COLUMN_NAME = 'document_teaser'
);
SET @ave_excerpt_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_excerpt_table
    AND COLUMN_NAME = 'document_excerpt'
);
SET @ave_excerpt_sql = IF(
  @ave_excerpt_has_new = 0 AND @ave_excerpt_has_old = 1,
  CONCAT('ALTER TABLE `', @ave_excerpt_table, '` CHANGE COLUMN `document_teaser` `document_excerpt` TEXT NOT NULL'),
  IF(
    @ave_excerpt_has_new = 0,
    CONCAT('ALTER TABLE `', @ave_excerpt_table, '` ADD COLUMN `document_excerpt` TEXT NOT NULL AFTER `document_breadcrumb_title`'),
    'SELECT 1'
  )
);
PREPARE ave_excerpt_stmt FROM @ave_excerpt_sql;
EXECUTE ave_excerpt_stmt;
DEALLOCATE PREPARE ave_excerpt_stmt;

SET @ave_excerpt_has_old = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_excerpt_table
    AND COLUMN_NAME = 'document_teaser'
);
SET @ave_excerpt_has_new = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @ave_excerpt_table
    AND COLUMN_NAME = 'document_excerpt'
);
SET @ave_excerpt_sql = IF(
  @ave_excerpt_has_old = 1 AND @ave_excerpt_has_new = 1,
  CONCAT('UPDATE `', @ave_excerpt_table, '` SET `document_excerpt` = `document_teaser` WHERE `document_excerpt` = \'\' AND `document_teaser` <> \'\''),
  'SELECT 1'
);
PREPARE ave_excerpt_stmt FROM @ave_excerpt_sql;
EXECUTE ave_excerpt_stmt;
DEALLOCATE PREPARE ave_excerpt_stmt;

SET @ave_excerpt_sql = IF(
  @ave_excerpt_has_old = 1 AND @ave_excerpt_has_new = 1,
  CONCAT('ALTER TABLE `', @ave_excerpt_table, '` DROP COLUMN `document_teaser`'),
  'SELECT 1'
);
PREPARE ave_excerpt_stmt FROM @ave_excerpt_sql;
EXECUTE ave_excerpt_stmt;
DEALLOCATE PREPARE ave_excerpt_stmt;
