CREATE TABLE IF NOT EXISTS `{{prefix}}_countries` (
  `Id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_code` CHAR(2) NOT NULL DEFAULT 'RU',
  `country_name` CHAR(50) NOT NULL DEFAULT '',
  `country_status` ENUM('1','2') NOT NULL DEFAULT '2',
  `country_eu` ENUM('1','2') NOT NULL DEFAULT '2',
  PRIMARY KEY (`Id`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_country_status` (`country_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_paginations` (
  `id` TINYINT(1) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pagination_name` TINYTEXT,
  `pagination_box` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_start_label` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_end_label` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_separator_box` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_separator_label` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_next_label` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_prev_label` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_link_box` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_active_link_box` VARCHAR(255) NOT NULL DEFAULT '',
  `pagination_link_template` VARCHAR(255) DEFAULT NULL,
  `pagination_link_active_template` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_constants` (
  `name` VARCHAR(80) NOT NULL,
  `value` MEDIUMTEXT,
  `type` VARCHAR(20) NOT NULL DEFAULT 'string',
  `group_code` VARCHAR(80) NOT NULL DEFAULT 'custom',
  `label` VARCHAR(190) NOT NULL DEFAULT '',
  `description` TEXT,
  `options` TEXT,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 100,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`name`),
  KEY `idx_group_code` (`group_code`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
