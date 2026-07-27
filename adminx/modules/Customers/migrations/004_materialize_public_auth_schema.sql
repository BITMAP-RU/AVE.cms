CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_auth_settings` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `registration_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `registration_mode` VARCHAR(16) NOT NULL DEFAULT 'email',
  `registration_gate` VARCHAR(24) NOT NULL DEFAULT 'email',
  `default_group` SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  `password_min_length` TINYINT UNSIGNED NOT NULL DEFAULT 8,
  `verification_ttl` INT UNSIGNED NOT NULL DEFAULT 86400,
  `reset_ttl` INT UNSIGNED NOT NULL DEFAULT 3600,
  `password_reset_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `require_firstname` TINYINT(1) NOT NULL DEFAULT 1,
  `show_lastname` TINYINT(1) NOT NULL DEFAULT 1,
  `require_lastname` TINYINT(1) NOT NULL DEFAULT 0,
  `show_phone` TINYINT(1) NOT NULL DEFAULT 0,
  `require_phone` TINYINT(1) NOT NULL DEFAULT 0,
  `show_company` TINYINT(1) NOT NULL DEFAULT 0,
  `require_company` TINYINT(1) NOT NULL DEFAULT 0,
  `deny_domains` TEXT NULL,
  `deny_emails` TEXT NULL,
  `pages_json` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

ALTER TABLE `{{public_user_prefix}}_auth_settings`
  ADD COLUMN IF NOT EXISTS `pages_json` MEDIUMTEXT NULL AFTER `deny_emails`;

INSERT INTO `{{public_user_prefix}}_auth_settings`
  (`id`, `registration_enabled`, `registration_mode`, `registration_gate`, `default_group`,
   `password_min_length`, `verification_ttl`, `reset_ttl`, `password_reset_enabled`,
   `require_firstname`, `show_lastname`, `require_lastname`, `show_phone`, `require_phone`,
   `show_company`, `require_company`, `deny_domains`, `deny_emails`, `pages_json`, `updated_at`)
VALUES
  (1, 0, 'email', 'email', 4, 8, 86400, 3600, 0, 1, 1, 0, 0, 0, 0, 0, '', '', NULL, NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_auth_templates` (
  `page_key` VARCHAR(32) NOT NULL,
  `template_text` MEDIUMTEXT NOT NULL,
  `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`page_key`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
