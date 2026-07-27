CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_field_sets` (
  `code` VARCHAR(64) CHARACTER SET ascii NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` TEXT NOT NULL,
  `definition_json` LONGTEXT NOT NULL,
  `fingerprint` CHAR(64) CHARACTER SET ascii NOT NULL DEFAULT '',
  `source` VARCHAR(32) CHARACTER SET ascii NOT NULL DEFAULT 'import',
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`),
  KEY `idx_active_title` (`active`,`title`(120))
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_field_set_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `set_code` VARCHAR(64) CHARACTER SET ascii NOT NULL,
  `applied_fingerprint` CHAR(64) CHARACTER SET ascii NOT NULL DEFAULT '',
  `applied_definition_json` LONGTEXT NOT NULL,
  `field_map_json` LONGTEXT NOT NULL,
  `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rubric_set` (`rubric_id`,`set_code`),
  KEY `idx_set_code` (`set_code`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
