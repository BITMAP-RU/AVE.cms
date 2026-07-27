INSERT INTO `{{prefix}}_permissions`
    (`code`, `module`, `group_code`, `name`, `description`, `sort_order`)
VALUES
    ('view_public_debug', 'admin', 'core', 'Публичная панель отладки',
     'Показывает на публичном сайте запросы, ошибки, сессию и служебные данные страницы.', 3)
ON DUPLICATE KEY UPDATE
    `module` = VALUES(`module`),
    `group_code` = VALUES(`group_code`),
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `sort_order` = VALUES(`sort_order`);
