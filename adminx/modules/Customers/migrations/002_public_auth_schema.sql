ALTER TABLE `{{public_user_prefix}}_users`
  MODIFY `password` VARCHAR(255) NOT NULL,
  MODIFY `salt` VARCHAR(64) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `email_verified_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `email`,
  ADD COLUMN IF NOT EXISTS `phone_verified_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `phone`;

CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_auth_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `purpose` VARCHAR(32) NOT NULL,
  `channel` VARCHAR(24) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` INT UNSIGNED NOT NULL,
  `used_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_purpose` (`user_id`,`purpose`),
  KEY `idx_expiry` (`expires_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_user_profile_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `type` VARCHAR(24) NOT NULL DEFAULT 'text',
  `options` TEXT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `show_registration` TINYINT(1) NOT NULL DEFAULT 0,
  `show_profile` TINYINT(1) NOT NULL DEFAULT 1,
  `position` INT NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code`),
  KEY `idx_active_position` (`is_active`,`position`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

ALTER TABLE `{{public_user_prefix}}_user_profile_fields`
  ADD COLUMN IF NOT EXISTS `show_registration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `show_profile` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_registration`;

CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_user_profile_values` (
  `user_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `value` TEXT NULL,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`field_id`),
  KEY `idx_field` (`field_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{public_user_prefix}}_user_identities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(24) CHARACTER SET ascii NOT NULL,
  `provider_user_id` VARCHAR(190) CHARACTER SET ascii NOT NULL,
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `profile_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  `last_login_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_identity` (`provider`,`provider_user_id`),
  KEY `idx_identity_user` (`user_id`),
  KEY `idx_identity_email` (`email`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
