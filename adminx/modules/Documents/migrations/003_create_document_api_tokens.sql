CREATE TABLE IF NOT EXISTS `{{system_prefix}}_api_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `token_prefix` VARCHAR(20) NOT NULL,
  `scopes` VARCHAR(255) NOT NULL,
  `last_used_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
