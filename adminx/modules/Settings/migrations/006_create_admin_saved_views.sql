CREATE TABLE IF NOT EXISTS `{{system_prefix}}_admin_saved_views` (
  `id` CHAR(12) CHARACTER SET ascii NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `scope` VARCHAR(64) CHARACTER SET ascii NOT NULL,
  `title` VARCHAR(60) NOT NULL,
  `filters_json` TEXT NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_scope_title` (`user_id`,`scope`,`title`),
  KEY `idx_user_scope` (`user_id`,`scope`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4;
