CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_trash` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `rubric_title` VARCHAR(255) NOT NULL DEFAULT '',
  `rubric_alias` VARCHAR(255) NOT NULL DEFAULT '',
  `fields_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `groups_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `payload_json` LONGTEXT NOT NULL,
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(255) NOT NULL DEFAULT '',
  `deleted_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_rubric_id` (`rubric_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
