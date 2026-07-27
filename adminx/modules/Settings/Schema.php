<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Settings/Schema.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Settings;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\AdminLocale;
	use App\Common\SystemTables;
	use App\Frontend\ThemeSettings;

	/**
	 * Описание системных настроек, переносимых из legacy source settings в
	 * key/value App\Common\Settings. Один источник для UI, validation и мигратора.
	 */
	class Schema
	{
		public static function groups()
		{
			$groups = array(
				'access' => array(
					'label' => 'Доступ к сайту',
					'icon' => 'ti ti-lock-access',
					'tile_bg' => 'var(--amber-100)',
					'tile_fg' => 'var(--amber-600)',
					'description' => 'Публичный доступ и временное закрытие сайта на время разработки.',
					'sort_order' => 5,
				),
				'site' => array(
					'label' => 'Сайт',
					'icon' => 'ti ti-world',
					'tile_bg' => 'var(--blue-100)',
					'tile_fg' => 'var(--blue-600)',
					'description' => 'Название, страна, дата и системные страницы.',
					'sort_order' => 10,
				),
				'mail' => array(
					'label' => 'Почта',
					'icon' => 'ti ti-mail',
					'tile_bg' => 'var(--green-100)',
					'tile_fg' => 'var(--green-600)',
					'description' => 'Отправитель, транспорт и шаблон письма.',
					'sort_order' => 20,
				),
				'pagination' => array(
					'label' => 'Пагинация',
					'icon' => 'ti ti-list-numbers',
					'tile_bg' => 'var(--violet-100)',
					'tile_fg' => 'var(--violet-600)',
					'description' => 'Шаблоны постраничной навигации по умолчанию.',
					'sort_order' => 30,
				),
				'breadcrumbs' => array(
					'label' => 'Хлебные крошки',
					'icon' => 'ti ti-route',
					'tile_bg' => 'var(--cyan-100)',
					'tile_fg' => 'var(--cyan-600)',
					'description' => 'Шаблоны цепочки навигации документа.',
					'sort_order' => 40,
				),
			);

			return $groups;
		}

		public static function definitions()
		{
			$definitions = array(
				'site_access_mode' => array(
					'label' => 'Режим публичного доступа',
					'type' => 'select',
					'group' => 'access',
					'options' => array(
						'public' => 'Сайт открыт',
						'development' => 'Режим разработки',
					),
					'default' => 'public',
					'public_shell' => false,
					'sort_order' => 10,
				),
				'site_development_title' => array(
					'label' => 'Заголовок закрытой страницы',
					'type' => 'string',
					'group' => 'access',
					'default' => 'Сайт готовится к запуску',
					'max' => 160,
					'public_shell' => false,
					'depends' => array('site_access_mode' => 'development'),
					'sort_order' => 20,
				),
				'site_development_message' => array(
					'label' => 'Сообщение посетителям',
					'type' => 'text',
					'group' => 'access',
					'default' => 'Мы завершаем настройку сайта. Пожалуйста, зайдите немного позже.',
					'max' => 1000,
					'public_shell' => false,
					'depends' => array('site_access_mode' => 'development'),
					'sort_order' => 30,
				),
				'site_name' => array(
					'label' => 'Название сайта',
					'type' => 'string',
					'group' => 'site',
					'required' => true,
					'max' => 255,
					'sort_order' => 10,
				),
				'default_country' => array(
					'label' => 'Страна по умолчанию',
					'type' => 'select',
					'group' => 'site',
					'options' => self::countryOptions(),
					'default' => 'RU',
					'sort_order' => 20,
				),
				'date_format' => array(
					'label' => 'Формат даты',
					'type' => 'select',
					'group' => 'site',
					'options' => array(
						'%d.%m.%Y' => '09.07.2026',
						'%d %B %Y' => '09 июля 2026',
						'%A, %d.%m.%Y' => 'Четверг, 09.07.2026',
						'%A, %d %B %Y' => 'Четверг, 09 июля 2026',
					),
					'default' => '%d.%m.%Y',
					'sort_order' => 30,
				),
				'time_format' => array(
					'label' => 'Формат даты и времени',
					'type' => 'select',
					'group' => 'site',
					'options' => array(
						'%d.%m.%Y, %H:%M' => '09.07.2026, 12:30',
						'%d %B %Y, %H:%M' => '09 июля 2026, 12:30',
						'%A, %d.%m.%Y (%H:%M)' => 'Четверг, 09.07.2026 (12:30)',
						'%A, %d %B %Y (%H:%M)' => 'Четверг, 09 июля 2026 (12:30)',
					),
					'default' => '%d.%m.%Y, %H:%M',
					'sort_order' => 40,
				),
				'use_doctime' => array(
					'label' => 'Учитывать дату публикации документа',
					'type' => 'bool',
					'group' => 'site',
					'default' => true,
					'sort_order' => 50,
				),
				'page_not_found_id' => array(
					'label' => 'ID страницы 404',
					'type' => 'int',
					'group' => 'site',
					'default' => 2,
					'min' => 1,
					'sort_order' => 60,
				),
				'message_forbidden' => array(
					'label' => 'Текст запрета доступа',
					'type' => 'text',
					'group' => 'site',
					'sort_order' => 70,
				),
				'hidden_text' => array(
					'label' => 'Текст скрытого блока',
					'type' => 'text',
					'group' => 'site',
					'sort_order' => 80,
				),

				'mail_from_name' => array(
					'label' => 'Имя отправителя',
					'type' => 'string',
					'group' => 'mail',
					'max' => 255,
					'sort_order' => 10,
				),
				'mail_from' => array(
					'label' => 'Email отправителя',
					'type' => 'email',
					'group' => 'mail',
					'max' => 255,
					'sort_order' => 20,
				),
				'mail_type' => array(
					'label' => 'Транспорт',
					'type' => 'select',
					'group' => 'mail',
					'options' => array(
						'mail' => 'PHP mail()',
						'smtp' => 'SMTP',
						'sendmail' => 'Sendmail',
					),
					'default' => 'mail',
					'sort_order' => 30,
				),
				'mail_host' => array(
					'label' => 'SMTP-сервер',
					'type' => 'string',
					'group' => 'mail',
					'max' => 255,
					'depends' => array('mail_type' => 'smtp'),
					'sort_order' => 40,
				),
				'mail_port' => array(
					'label' => 'SMTP-порт',
					'type' => 'int',
					'group' => 'mail',
					'default' => 25,
					'min' => 1,
					'max' => 65535,
					'depends' => array('mail_type' => 'smtp'),
					'sort_order' => 50,
				),
				'mail_smtp_encrypt' => array(
					'label' => 'SMTP-шифрование',
					'type' => 'select',
					'group' => 'mail',
					'options' => array('' => 'Без шифрования', 'ssl' => 'SSL', 'tls' => 'TLS'),
					'depends' => array('mail_type' => 'smtp'),
					'sort_order' => 60,
				),
				'mail_smtp_login' => array(
					'label' => 'SMTP-логин',
					'type' => 'string',
					'group' => 'mail',
					'max' => 255,
					'depends' => array('mail_type' => 'smtp'),
					'sort_order' => 70,
				),
				'mail_smtp_pass' => array(
					'label' => 'SMTP-пароль',
					'type' => 'password',
					'group' => 'mail',
					'max' => 255,
					'sensitive' => true,
					'depends' => array('mail_type' => 'smtp'),
					'sort_order' => 80,
				),
				'mail_sendmail_path' => array(
					'label' => 'Путь к sendmail',
					'type' => 'string',
					'group' => 'mail',
					'default' => '/usr/sbin/sendmail',
					'max' => 255,
					'depends' => array('mail_type' => 'sendmail'),
					'sort_order' => 90,
				),
				'mail_word_wrap' => array(
					'label' => 'Перенос строк',
					'type' => 'int',
					'group' => 'mail',
					'default' => 50,
					'min' => 0,
					'max' => 1000,
					'sort_order' => 100,
				),
				'mail_content_type' => array(
					'label' => 'Тип письма',
					'type' => 'select',
					'group' => 'mail',
					'options' => array('text/plain' => 'Text', 'text/html' => 'HTML'),
					'default' => 'text/plain',
					'sort_order' => 110,
				),
				'mail_new_user' => array(
					'label' => 'Письмо новому пользователю',
					'type' => 'text',
					'group' => 'mail',
					'default' => "Здравствуйте, %NAME%!\n\nВаш аккаунт на сайте %HOST% создан. Теперь вы можете войти, используя данные регистрации.",
					'sort_order' => 120,
				),
				'mail_signature' => array(
					'label' => 'Подпись письма',
					'type' => 'text',
					'group' => 'mail',
					'default' => "С уважением,\nкоманда сайта.",
					'sort_order' => 130,
				),
			) + self::navigationDefinitions();

			return $definitions;
		}

		public static function legacyKeys()
		{
			return array_keys(self::definitions());
		}

		public static function definition($key)
		{
			$defs = self::definitions();
			return isset($defs[$key]) ? $defs[$key] : null;
		}

		public static function groupedFields()
		{
			$groups = self::groups();
			$defs = self::definitions();
			foreach ($groups as $code => $group) {
				$groups[$code]['label'] = AdminLocale::translateMarkup($group['label']);
				$groups[$code]['description'] = AdminLocale::translateMarkup($group['description']);
				$groups[$code]['fields'] = array();
			}

			foreach ($defs as $key => $definition) {
				$group = isset($definition['group']) ? $definition['group'] : 'site';
				if (!isset($groups[$group])) {
					$groups[$group] = array(
						'label' => $group,
						'icon' => 'ti ti-settings',
						'description' => '',
						'sort_order' => 100,
						'fields' => array(),
					);
				}

				$definition['key'] = $key;
				$definition['value'] = Model::value($key, isset($definition['default']) ? $definition['default'] : '');
				$definition = self::localizeDefinition($definition);
				if (isset($definition['type']) && $definition['type'] === 'section_order') {
					$definition['order_items'] = ThemeSettings::sectionOrder(
						$definition['value'],
						isset($definition['options']) ? $definition['options'] : array()
					);
				}

				$groups[$group]['fields'][] = $definition;
			}

			uasort($groups, function ($a, $b) {
				return (int) $a['sort_order'] < (int) $b['sort_order'] ? -1 : 1;
			});

			foreach ($groups as $code => $group) {
				usort($groups[$code]['fields'], function ($a, $b) {
					$sa = isset($a['sort_order']) ? (int) $a['sort_order'] : 100;
					$sb = isset($b['sort_order']) ? (int) $b['sort_order'] : 100;
					if ($sa === $sb) {
						return strcmp($a['key'], $b['key']);
					}

					return $sa < $sb ? -1 : 1;
				});
			}

			return $groups;
		}

		protected static function localizeDefinition(array $definition)
		{
			foreach (array('label', 'description', 'hint') as $key) {
				if (isset($definition[$key]) && is_string($definition[$key])) {
					$definition[$key] = AdminLocale::translateMarkup($definition[$key]);
				}
			}

			if (isset($definition['options']) && is_array($definition['options'])) {
				foreach ($definition['options'] as $value => $label) {
					if (is_string($label)) {
						$definition['options'][$value] = AdminLocale::translateMarkup($label);
					}
				}
			}

			return $definition;
		}

		protected static function countryOptions()
		{
			$fallback = array('RU' => 'Россия');
			try {
				$table = SystemTables::table('countries');
				$exists = \DB::query('SHOW TABLES LIKE %s', $table)->getValue();
				if (!$exists) {
					return $fallback;
				}

				$rows = \DB::query(
					'SELECT country_code, country_name FROM ' . $table . " WHERE country_status = '1' ORDER BY country_name ASC"
				)->getAll();
				$out = array();
				foreach ((array) $rows as $row) {
					$out[(string) $row['country_code']] = (string) $row['country_name'];
				}

				return !empty($out) ? $out : $fallback;
			} catch (\Throwable $e) {
				return $fallback;
			}
		}

		protected static function navigationDefinitions()
		{
			$pagination = array(
				'navi_box' => array('Контейнер пагинации', '<ul class="pagination">%s</ul>'),
				'start_label' => array('Ссылка «Первая»', 'Первая'),
				'end_label' => array('Ссылка «Последняя»', 'Последняя'),
				'separator_label' => array('Метка пропуска страниц', '…'),
				'next_label' => array('Ссылка «Следующая»', '»'),
				'prev_label' => array('Ссылка «Предыдущая»', '«'),
				'total_label' => array('Метка общего количества', 'Страница %d из %d'),
				'link_box' => array('Контейнер ссылки', '<li>%s</li>'),
				'total_box' => array('Контейнер итога', '<span>%s</span>'),
				'active_box' => array('Контейнер активной страницы', '<li class="active">%s</li>'),
				'separator_box' => array('Контейнер разделителя', '<li>%s</li>'),
			);

			$breadcrumbs = array(
				'bread_box' => array('Контейнер хлебных крошек', '<ol class="breadcrumb">%s</ol>'),
				'bread_separator' => array('Разделитель хлебных крошек', '<li aria-hidden="true">/</li>'),
				'bread_link_box' => array('Контейнер ссылки крошки', '<li>%s</li>'),
				'bread_link_template' => array('Шаблон ссылки крошки', '<a href="[link]">[name]</a>'),
				'bread_self_box' => array('Контейнер текущей страницы', '<li aria-current="page">%s</li>'),
			);

			$defs = array();
			$sort = 10;
			foreach ($pagination as $key => $definition) {
				$defs[$key] = array(
					'label' => $definition[0],
					'type' => 'string',
					'group' => 'pagination',
					'default' => $definition[1],
					'sort_order' => $sort,
				);
				$sort += 10;
			}

			$sort = 10;
			foreach ($breadcrumbs as $key => $definition) {
				$defs[$key] = array(
					'label' => $definition[0],
					'type' => 'string',
					'group' => 'breadcrumbs',
					'default' => $definition[1],
					'sort_order' => $sort,
				);
				$sort += 10;
			}

			foreach (array(
				'bread_show_main' => 'Показывать главную',
				'bread_show_host' => 'Показывать хост',
				'bread_separator_use' => 'Использовать разделитель',
				'bread_link_box_last' => 'Последняя крошка как ссылка',
			) as $key => $label) {
				$defs[$key] = array(
					'label' => $label,
					'type' => 'bool',
					'group' => 'breadcrumbs',
					'default' => true,
					'sort_order' => $sort,
				);
				$sort += 10;
			}

			return $defs;
		}
	}
