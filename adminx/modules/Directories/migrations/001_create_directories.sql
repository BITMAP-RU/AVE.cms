CREATE TABLE IF NOT EXISTS `{{content_prefix}}_directories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `settings_json` TEXT NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_directory_code` (`code`),
  KEY `idx_directory_active_name` (`is_active`, `name`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{content_prefix}}_directory_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `directory_id` INT UNSIGNED NOT NULL,
  `item_key` VARCHAR(120) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `data_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_directory_item_key` (`directory_id`, `item_key`(120)),
  KEY `idx_directory_item_order` (`directory_id`, `sort_order`, `id`),
  KEY `idx_directory_item_parent` (`directory_id`, `parent_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
