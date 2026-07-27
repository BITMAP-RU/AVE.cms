DROP TABLE IF EXISTS `{{module_prefix}}_registration_certificates`;
DELETE FROM `{{prefix}}_permissions` WHERE `module` = 'registrations';
