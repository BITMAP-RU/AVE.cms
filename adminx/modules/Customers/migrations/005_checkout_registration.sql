ALTER TABLE `{{public_user_prefix}}_auth_settings`
  ADD COLUMN IF NOT EXISTS `checkout_registration_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `deny_emails`,
  ADD COLUMN IF NOT EXISTS `checkout_policy_url` VARCHAR(255) NOT NULL DEFAULT '/privacy-policy' AFTER `checkout_registration_enabled`,
  ADD COLUMN IF NOT EXISTS `checkout_policy_label` VARCHAR(300) NOT NULL DEFAULT '' AFTER `checkout_policy_url`,
  ADD COLUMN IF NOT EXISTS `checkout_access_subject` VARCHAR(190) NOT NULL DEFAULT 'Доступ к личному кабинету' AFTER `checkout_policy_label`,
  ADD COLUMN IF NOT EXISTS `checkout_access_template` MEDIUMTEXT NULL AFTER `checkout_access_subject`;

UPDATE `{{public_user_prefix}}_auth_settings`
SET `checkout_policy_label` = 'Я согласен с политикой конфиденциальности и обработкой персональных данных',
    `checkout_access_subject` = 'Доступ к личному кабинету',
    `checkout_access_template` = '<h2>Личный кабинет создан</h2><p>{% if user.firstname %}{{ user.firstname }}, {% endif %}ваш заказ №{{ order.id }} оформлен, а личный кабинет уже доступен.</p><p>Логин: <strong>{{ user.email }}</strong></p><p><a href="{{ access_url }}">Задать пароль</a></p><p>Ссылка действует {{ expires_hours }} ч. Если она истечёт, запросите новую на странице восстановления пароля.</p>'
WHERE `id` = 1
  AND (`checkout_access_template` IS NULL OR `checkout_access_template` = '');

UPDATE `{{public_user_prefix}}_auth_settings`
SET `checkout_registration_enabled` = COALESCE((SELECT `value` FROM `{{prefix}}_settings` WHERE `param` = 'email_registration.checkout_enabled' LIMIT 1), `checkout_registration_enabled`),
    `checkout_policy_url` = COALESCE(NULLIF((SELECT `value` FROM `{{prefix}}_settings` WHERE `param` = 'email_registration.policy_url' LIMIT 1), ''), `checkout_policy_url`),
    `checkout_policy_label` = COALESCE(NULLIF((SELECT `value` FROM `{{prefix}}_settings` WHERE `param` = 'email_registration.policy_label' LIMIT 1), ''), `checkout_policy_label`),
    `checkout_access_subject` = COALESCE(NULLIF((SELECT `value` FROM `{{prefix}}_settings` WHERE `param` = 'email_registration.access_subject' LIMIT 1), ''), `checkout_access_subject`),
    `checkout_access_template` = COALESCE(NULLIF((SELECT `value` FROM `{{prefix}}_settings` WHERE `param` = 'email_registration.access_template' LIMIT 1), ''), `checkout_access_template`)
WHERE `id` = 1;

DELETE FROM `{{prefix}}_settings`
WHERE `param` LIKE 'email_registration.%';

DELETE rp FROM `{{prefix}}_role_permissions` rp
INNER JOIN `{{prefix}}_permissions` p ON p.`code` = rp.`permission_code`
WHERE p.`module` = 'email_registration';

DELETE FROM `{{prefix}}_permissions`
WHERE `module` = 'email_registration';

DELETE FROM `{{prefix}}_module_events`
WHERE `module_code` = 'email_registration';

DELETE FROM `{{prefix}}_module_migrations`
WHERE `module` = 'email_registration';

DELETE FROM `{{prefix}}_modules`
WHERE `code` = 'email_registration';
