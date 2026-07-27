CREATE TABLE IF NOT EXISTS `{{prefix}}_rubrics` (
  `Id` SMALLINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_title` VARCHAR(255) NOT NULL DEFAULT '',
  `rubric_alias` VARCHAR(255) NOT NULL DEFAULT '',
  `rubric_alias_history` ENUM('0','1') NOT NULL DEFAULT '0',
  `rubric_template` LONGTEXT NOT NULL,
  `rubric_template_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 1,
  `rubric_author_id` INT(10) UNSIGNED NOT NULL DEFAULT 1,
  `rubric_created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `rubric_docs_active` INT(1) UNSIGNED NOT NULL DEFAULT 1,
  `rubric_start_code` TEXT NOT NULL,
  `rubric_code_start` TEXT NOT NULL,
  `rubric_code_end` TEXT NOT NULL,
  `rubric_teaser_template` TEXT NOT NULL,
  `rubric_header_template` TEXT NOT NULL,
  `rubric_og_template` TEXT NOT NULL,
  `rubric_footer_template` TEXT NOT NULL,
  `rubric_linked_rubric` VARCHAR(255) NOT NULL DEFAULT '0',
  `rubric_description` TEXT NOT NULL,
  `rubric_meta_gen` ENUM('0','1') NOT NULL DEFAULT '0',
  `rubric_position` INT(11) UNSIGNED NOT NULL DEFAULT 100,
  `rubric_changed` INT(10) NOT NULL DEFAULT 0,
  `rubric_changed_fields` INT(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  KEY `idx_rubric_position` (`rubric_position`),
  KEY `idx_rubric_alias` (`rubric_alias`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{content_prefix}}_rubric_admin_views` (
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL,
  `mode` VARCHAR(20) NOT NULL DEFAULT 'standard',
  `config_json` LONGTEXT NOT NULL,
  `updated_by` INT(10) UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`rubric_id`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_fields` (
  `Id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `rubric_field_group` SMALLINT(3) DEFAULT NULL,
  `rubric_field_alias` VARCHAR(20) NOT NULL DEFAULT '',
  `rubric_field_title` VARCHAR(255) NOT NULL DEFAULT '',
  `rubric_field_type` VARCHAR(75) NOT NULL DEFAULT '',
  `rubric_field_numeric` ENUM('0','1') NOT NULL DEFAULT '0',
  `rubric_field_position` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 1,
  `rubric_field_default` TEXT NOT NULL,
  `rubric_field_search` ENUM('0','1') NOT NULL DEFAULT '1',
  `rubric_field_template` TEXT NOT NULL,
  `rubric_field_template_request` TEXT NOT NULL,
  `rubric_field_description` TEXT NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `idx_rubric` (`rubric_id`, `rubric_field_position`),
  KEY `idx_group` (`rubric_field_group`),
  KEY `idx_alias` (`rubric_id`, `rubric_field_alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_fields_group` (
  `Id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `group_title` VARCHAR(255) NOT NULL DEFAULT '',
  `group_description` TEXT NOT NULL,
  `group_position` INT(11) NOT NULL DEFAULT 100,
  PRIMARY KEY (`Id`),
  KEY `idx_rubric_position` (`rubric_id`, `group_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_templates` (
  `id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `template` LONGTEXT NOT NULL,
  `author_id` INT(10) UNSIGNED NOT NULL DEFAULT 1,
  `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_rubric` (`rubric_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{{prefix}}_rubric_permissions` (
  `Id` MEDIUMINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `user_group_id` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 0,
  `rubric_permission` CHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`Id`),
  KEY `idx_rubric_group` (`rubric_id`, `user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
