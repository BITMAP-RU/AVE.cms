UPDATE `{{prefix}}_permissions`
SET
    `name` = 'Доступ в панель управления',
    `description` = 'Разрешает вход в панель управления.'
WHERE `code` = 'admin_panel';
