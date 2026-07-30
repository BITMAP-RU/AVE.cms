CREATE TABLE IF NOT EXISTS `{{content_prefix}}_presentations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(190) NOT NULL,
  `code` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `kind` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'card',
  `description` TEXT NULL,
  `draft_item_markup` LONGTEXT NOT NULL,
  `draft_wrapper_markup` LONGTEXT NOT NULL,
  `draft_empty_markup` LONGTEXT NOT NULL,
  `draft_css` LONGTEXT NOT NULL,
  `draft_settings_json` TEXT NULL,
  `published_item_markup` LONGTEXT NOT NULL,
  `published_wrapper_markup` LONGTEXT NOT NULL,
  `published_empty_markup` LONGTEXT NOT NULL,
  `published_css` LONGTEXT NOT NULL,
  `published_settings_json` TEXT NULL,
  `is_published` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `version` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `published_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_content_presentation_code` (`code`),
  KEY `idx_content_presentation_kind` (`kind`, `is_published`),
  KEY `idx_content_presentation_updated` (`updated_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{content_prefix}}_presentation_revisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `presentation_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `snapshot_hash` CHAR(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `comment` VARCHAR(255) NOT NULL DEFAULT '',
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(190) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_presentation_revision_owner` (`presentation_id`, `created_at`, `id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{content_prefix}}_presentation_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `presentation_id` INT UNSIGNED NOT NULL,
  `context_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target_key` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '0',
  `mode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'legacy',
  `settings_json` TEXT NULL,
  `author_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_presentation_assignment_target` (`context_code`, `target_type`, `target_key`),
  KEY `idx_presentation_assignment_owner` (`presentation_id`, `mode`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
