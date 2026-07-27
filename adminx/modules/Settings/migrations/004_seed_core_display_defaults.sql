INSERT INTO `{{prefix}}_settings` (`param`, `value`, `type`) VALUES
('mail_new_user', 'Здравствуйте, %NAME%!\n\nВаш аккаунт на сайте %HOST% создан. Теперь вы можете войти, используя данные регистрации.', 'string'),
('mail_signature', 'С уважением,\nкоманда сайта.', 'string'),
('navi_box', '<ul class="pagination">%s</ul>', 'string'),
('start_label', 'Первая', 'string'),
('end_label', 'Последняя', 'string'),
('separator_label', '…', 'string'),
('next_label', '»', 'string'),
('prev_label', '«', 'string'),
('total_label', 'Страница %d из %d', 'string'),
('link_box', '<li>%s</li>', 'string'),
('total_box', '<span>%s</span>', 'string'),
('active_box', '<li class="active">%s</li>', 'string'),
('separator_box', '<li>%s</li>', 'string'),
('bread_box', '<ol class="breadcrumb">%s</ol>', 'string'),
('bread_separator', '<li aria-hidden="true">/</li>', 'string'),
('bread_link_box', '<li>%s</li>', 'string'),
('bread_link_template', '<a href="[link]">[name]</a>', 'string'),
('bread_self_box', '<li aria-current="page">%s</li>', 'string')
ON DUPLICATE KEY UPDATE
`value` = IF(`value` IS NULL OR `value` = '', VALUES(`value`), `value`),
`type` = IF(`type` IS NULL OR `type` = '', VALUES(`type`), `type`);
