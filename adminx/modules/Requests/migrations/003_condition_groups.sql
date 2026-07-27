CREATE TABLE IF NOT EXISTS `{{prefix}}_request_condition_groups` (
  `Id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` SMALLINT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `group_title` VARCHAR(100) NOT NULL DEFAULT '',
  `group_operator` ENUM('AND','OR') NOT NULL DEFAULT 'AND',
  `group_position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  KEY `idx_request_parent` (`request_id`, `parent_id`, `group_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `{{prefix}}_request_conditions`
  ADD COLUMN IF NOT EXISTS `condition_group_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `request_id`;

INSERT INTO `{{prefix}}_request_condition_groups`
  (`request_id`, `parent_id`, `group_title`, `group_operator`, `group_position`)
SELECT r.`Id`, 0, '',
       COALESCE((SELECT c.`condition_join`
                 FROM `{{prefix}}_request_conditions` c
                 WHERE c.`request_id` = r.`Id` AND c.`condition_status` = '1'
                 ORDER BY c.`condition_position`, c.`Id` LIMIT 1), 'AND'),
       0
FROM `{{prefix}}_request` r
WHERE NOT EXISTS (
  SELECT 1 FROM `{{prefix}}_request_condition_groups` g
  WHERE g.`request_id` = r.`Id` AND g.`parent_id` = 0
);

UPDATE `{{prefix}}_request_conditions` c
JOIN `{{prefix}}_request_condition_groups` g
  ON g.`request_id` = c.`request_id` AND g.`parent_id` = 0
SET c.`condition_group_id` = g.`Id`
WHERE c.`condition_group_id` = 0;

ALTER TABLE `{{prefix}}_request_conditions`
  ADD KEY IF NOT EXISTS `idx_request_group_position`
    (`request_id`, `condition_group_id`, `condition_position`);
