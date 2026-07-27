ALTER TABLE `{{prefix}}_module_catalog_settings`
  ADD COLUMN IF NOT EXISTS `purpose` VARCHAR(20) NOT NULL DEFAULT 'commerce' AFTER `field_id`;

UPDATE `{{prefix}}_module_catalog_settings`
SET `purpose` = 'commerce'
WHERE `purpose` = '' OR `purpose` IS NULL;

ALTER TABLE `{{prefix}}_module_catalog_settings`
  MODIFY COLUMN `purpose` VARCHAR(20) NOT NULL DEFAULT 'content';
