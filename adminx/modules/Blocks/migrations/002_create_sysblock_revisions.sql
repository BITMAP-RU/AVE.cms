CREATE TABLE IF NOT EXISTS `{{prefix}}_sysblock_revisions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `block_id` MEDIUMINT(5) UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(32) NOT NULL DEFAULT 'update',
  `snapshot_hash` CHAR(40) NOT NULL DEFAULT '',
  `text_hash` CHAR(40) NOT NULL DEFAULT '',
  `snapshot_json` LONGTEXT NOT NULL,
  `comment` VARCHAR(255) NOT NULL DEFAULT '',
  `author_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(255) NOT NULL DEFAULT '',
  `source_revision_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_block_created` (`block_id`, `created_at`),
  KEY `idx_action` (`action`),
  KEY `idx_snapshot_hash` (`snapshot_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
