CREATE TABLE IF NOT EXISTS `{{prefix}}_media_search_index` (
  `path_hash` CHAR(40) CHARACTER SET ascii NOT NULL,
  `path` TEXT NOT NULL,
  `name` VARCHAR(190) NOT NULL DEFAULT '',
  `extension` VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT '',
  `is_image` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `modified_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`path_hash`),
  KEY `idx_modified` (`modified_at`),
  KEY `idx_type` (`is_image`,`extension`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
