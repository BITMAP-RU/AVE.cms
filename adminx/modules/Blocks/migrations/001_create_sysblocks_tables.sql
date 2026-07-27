CREATE TABLE IF NOT EXISTS `{{prefix}}_sysblocks_groups` (
  `id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `position` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_sysblocks` (
  `id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sysblock_group_id` INT(3) NOT NULL DEFAULT 0,
  `sysblock_name` VARCHAR(255) NOT NULL DEFAULT '',
  `sysblock_description` VARCHAR(255) DEFAULT NULL,
  `sysblock_alias` VARCHAR(20) NOT NULL DEFAULT '',
  `sysblock_text` LONGTEXT NOT NULL,
  `sysblock_active` ENUM('0','1') NOT NULL DEFAULT '1',
  `sysblock_eval` ENUM('0','1') NOT NULL DEFAULT '1',
  `sysblock_external` ENUM('0','1') NOT NULL DEFAULT '0',
  `sysblock_ajax` ENUM('0','1') NOT NULL DEFAULT '0',
  `sysblock_visual` ENUM('0','1') NOT NULL DEFAULT '0',
  `sysblock_author_id` INT(10) UNSIGNED NOT NULL DEFAULT 1,
  `sysblock_created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_alias` (`sysblock_alias`),
  KEY `idx_group` (`sysblock_group_id`),
  KEY `idx_active` (`sysblock_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
