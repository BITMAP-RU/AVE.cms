INSERT INTO `{{system_prefix}}_constants`
(`name`,`value`,`type`,`group_code`,`label`,`description`,`options`,`is_system`,`sort_order`,`updated_at`)
VALUES
('PUBLIC_SITE_URL','','string','system','PUBLIC_SITE_URL','Канонический публичный URL сайта для проверки Host и защищённых ссылок.','',1,20,NOW())
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`),`is_system`=1;
