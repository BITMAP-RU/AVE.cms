INSERT INTO `{{prefix}}_public_user_groups`
    (`user_group`, `user_group_name`, `status`, `set_default_avatar`, `default_avatar`, `user_group_permission`)
VALUES
    (1, 'Администраторы', '1', '0', '', ''),
    (2, 'Гости', '1', '0', '', ''),
    (3, 'Модераторы', '1', '0', '', ''),
    (4, 'Пользователи', '1', '0', '', '')
ON DUPLICATE KEY UPDATE
    `user_group_name` = VALUES(`user_group_name`),
    `status` = '1';
