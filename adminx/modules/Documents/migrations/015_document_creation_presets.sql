CREATE TABLE IF NOT EXISTS `{{prefix}}_document_creation_presets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` MEDIUMINT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `description` TEXT NOT NULL,
  `document_json` MEDIUMTEXT NOT NULL,
  `fields_json` MEDIUMTEXT NOT NULL,
  `source_document_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_preset_rubric` (`rubric_id`,`is_active`,`sort_order`,`id`),
  KEY `idx_preset_source` (`source_document_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
