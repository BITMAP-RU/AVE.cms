UPDATE `{{prefix}}_users`
SET `role` = 'user'
WHERE `role` = 'observer';

DELETE rp
FROM `{{prefix}}_role_permissions` rp
INNER JOIN `{{prefix}}_roles` r ON r.`id` = rp.`role_id`
WHERE r.`code` = 'observer';

DELETE FROM `{{prefix}}_roles`
WHERE `code` = 'observer';
