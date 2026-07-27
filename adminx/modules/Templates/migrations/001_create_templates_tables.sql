CREATE TABLE IF NOT EXISTS `{{prefix}}_templates` (
  `Id` SMALLINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_title` VARCHAR(255) NOT NULL DEFAULT '',
  `template_text` LONGTEXT NOT NULL,
  `template_author_id` INT(10) UNSIGNED NOT NULL DEFAULT 1,
  `template_created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  KEY `idx_template_title` (`template_title`),
  KEY `idx_template_author` (`template_author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
