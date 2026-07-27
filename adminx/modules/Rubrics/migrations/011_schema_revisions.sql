CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_schema_revisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(32) CHARACTER SET ascii NOT NULL DEFAULT 'update',
  `snapshot_hash` CHAR(40) CHARACTER SET ascii NOT NULL DEFAULT '',
  `snapshot_json` LONGTEXT NOT NULL,
  `comment` VARCHAR(500) NOT NULL DEFAULT '',
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(255) NOT NULL DEFAULT '',
  `source_revision_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_rubric_created` (`rubric_id`, `created_at`),
  KEY `idx_action` (`action`),
  KEY `idx_snapshot_hash` (`snapshot_hash`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
