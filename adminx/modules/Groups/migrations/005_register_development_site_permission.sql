INSERT INTO `{{prefix}}_permissions`
    (`code`, `module`, `group_code`, `name`, `description`, `sort_order`)
VALUES
    ('view_development_site', 'admin', 'core', 'Просмотр сайта в режиме разработки',
     'Разрешает видеть публичный сайт, когда он временно закрыт для посетителей и поисковых систем.', 4)
ON DUPLICATE KEY UPDATE
    `module` = VALUES(`module`),
    `group_code` = VALUES(`group_code`),
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `sort_order` = VALUES(`sort_order`);
