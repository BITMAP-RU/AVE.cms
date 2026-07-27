CREATE TABLE IF NOT EXISTS `{{prefix}}_console_snippets` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `code` MEDIUMTEXT NOT NULL,
  `author_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
