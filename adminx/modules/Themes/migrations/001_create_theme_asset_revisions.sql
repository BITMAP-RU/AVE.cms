CREATE TABLE IF NOT EXISTS `{{prefix}}_theme_asset_revisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `theme` VARCHAR(64) CHARACTER SET ascii NOT NULL,
  `asset_path` VARCHAR(500) NOT NULL,
  `action` VARCHAR(32) CHARACTER SET ascii NOT NULL DEFAULT 'update',
  `checksum` CHAR(64) CHARACTER SET ascii NOT NULL,
  `content` LONGTEXT NOT NULL,
  `content_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(255) NOT NULL DEFAULT '',
  `source_revision_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_theme_created` (`theme`,`created_at`),
  KEY `idx_theme_path` (`theme`,`asset_path`(120),`created_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
