INSERT INTO `{{prefix}}_permissions`
  (`code`, `module`, `group_code`, `name`, `description`, `sort_order`)
VALUES
  ('view_registrations', 'registrations', 'navigation', 'Рег. удостоверения: просмотр', 'Просмотр реестра регистрационных удостоверений.', 10),
  ('manage_registrations', 'registrations', 'content', 'Рег. удостоверения: управление', 'Создание, изменение и удаление регистрационных удостоверений.', 20)
ON DUPLICATE KEY UPDATE
  `module` = VALUES(`module`), `group_code` = VALUES(`group_code`),
  `name` = VALUES(`name`), `description` = VALUES(`description`), `sort_order` = VALUES(`sort_order`);
