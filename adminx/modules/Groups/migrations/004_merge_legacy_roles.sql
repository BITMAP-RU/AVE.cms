UPDATE `{{prefix}}_users`
SET `role` = 'moderator'
WHERE `role` IN ('manager','designer');

INSERT IGNORE INTO `{{prefix}}_role_permissions` (`role_id`,`permission_code`)
SELECT target.`id`, source_permissions.`permission_code`
FROM `{{prefix}}_roles` target
INNER JOIN `{{prefix}}_roles` source_roles ON source_roles.`code` IN ('manager','designer')
INNER JOIN `{{prefix}}_role_permissions` source_permissions ON source_permissions.`role_id` = source_roles.`id`
WHERE target.`code` = 'moderator';

DELETE source_permissions
FROM `{{prefix}}_role_permissions` source_permissions
INNER JOIN `{{prefix}}_roles` source_roles ON source_roles.`id` = source_permissions.`role_id`
WHERE source_roles.`code` IN ('manager','designer');

DELETE FROM `{{prefix}}_roles`
WHERE `code` IN ('manager','designer');
