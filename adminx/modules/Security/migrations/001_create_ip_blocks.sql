CREATE TABLE IF NOT EXISTS `{{system_prefix}}_ip_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `expires_at` INT UNSIGNED NULL,
  `actor_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip` (`ip`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
