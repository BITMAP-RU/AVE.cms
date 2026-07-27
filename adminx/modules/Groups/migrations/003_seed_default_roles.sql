INSERT INTO `{{prefix}}_roles` (`code`,`name`,`is_system`,`created_at`,`updated_at`) VALUES
  ('admin','Администратор',1,NOW(),NOW()),
  ('guest','Гостевая',1,NOW(),NOW()),
  ('moderator','Модератор',1,NOW(),NOW()),
  ('user','Пользователи',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `is_system`=1,
  `updated_at`=VALUES(`updated_at`);
