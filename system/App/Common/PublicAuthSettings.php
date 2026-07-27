<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicAuthSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\PublicUserTables;
	use DB;

	/** Единая конфигурация публичной регистрации, профиля и восстановления пароля. */
	class PublicAuthSettings
	{
		protected static $loaded;
		protected static $available = false;
		protected static $rowExists = false;
		protected static $reported = false;

		public static function all(array $fallback = array())
		{
			if (self::$loaded !== null) { return self::$loaded; }
			$row = false;
			try {
				$row = DB::query('SELECT * FROM `' . self::table() . '` WHERE id=1 LIMIT 1')->getAssoc();
				self::$available = true;
				self::$rowExists = (bool) $row;
			} catch (\Throwable $e) {
				self::reportUnavailable($e);
			}

			$data = $row ? (array) $row : self::defaults($fallback);
			if (!$row) {
				$data['registration_enabled'] = 0;
				$data['password_reset_enabled'] = 0;
			}

			self::$loaded = self::withPages(self::normalize($data));
			return self::$loaded;
		}

		public static function save(array $input)
		{
			$current = self::all();
			if (!self::$available || !self::$rowExists) {
				throw new \RuntimeException('Схема публичной авторизации не обновлена. Примените миграции ядра.');
			}

			$data = self::normalize(array_merge($current, $input));
			$data['updated_at'] = date('Y-m-d H:i:s');
			DB::Update(self::table(), $data, 'id=1');
			self::$loaded = null;
			return self::all();
		}

		protected static function defaults(array $fallback)
		{
			return array(
				'registration_enabled' => isset($fallback['enabled']) ? (!empty($fallback['enabled']) ? 1 : 0) : 1,
				'registration_mode' => isset($fallback['mode']) ? $fallback['mode'] : 'email',
				'registration_gate' => isset($fallback['gate']) ? $fallback['gate'] : 'email',
				'default_group' => isset($fallback['default_group']) ? (int) $fallback['default_group'] : 4,
				'password_min_length' => isset($fallback['password_min_length']) ? (int) $fallback['password_min_length'] : 8,
				'verification_ttl' => isset($fallback['verification_ttl']) ? (int) $fallback['verification_ttl'] : 86400,
				'reset_ttl' => isset($fallback['reset_ttl']) ? (int) $fallback['reset_ttl'] : 3600,
				'password_reset_enabled' => 1,
				'require_firstname' => 1,
				'show_lastname' => 1,
				'require_lastname' => 0,
				'show_phone' => 0,
				'require_phone' => 0,
				'show_company' => 0,
				'require_company' => 0,
				'deny_domains' => '',
				'deny_emails' => '',
				'checkout_registration_enabled' => 1,
				'checkout_policy_url' => '/privacy-policy',
				'checkout_policy_label' => 'Я согласен с политикой конфиденциальности и обработкой персональных данных',
				'checkout_access_subject' => 'Доступ к личному кабинету',
				'checkout_access_template' => self::defaultCheckoutAccessTemplate(),
				'pages_json' => json_encode(self::defaultPages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
		}

		public static function defaultCheckoutAccessTemplate()
		{
			return '<h2>Личный кабинет создан</h2>'
				. '<p>{% if user.firstname %}{{ user.firstname }}, {% endif %}ваш заказ №{{ order.id }} оформлен, а личный кабинет уже доступен.</p>'
				. '<p>Логин: <strong>{{ user.email }}</strong></p>'
				. '<p><a href="{{ access_url }}">Задать пароль</a></p>'
				. '<p>Ссылка действует {{ expires_hours }} ч. Если она истечёт, запросите новую на странице восстановления пароля.</p>';
		}

		protected static function normalize(array $data)
		{
			$bools = array('registration_enabled','password_reset_enabled','require_firstname','show_lastname','require_lastname','show_phone','require_phone','show_company','require_company','checkout_registration_enabled');
			foreach ($bools as $key) { $data[$key] = !empty($data[$key]) ? 1 : 0; }
			$mode = isset($data['registration_mode']) ? (string) $data['registration_mode'] : 'email';
			$data['registration_mode'] = in_array($mode, array('now','email','byadmin'), true) ? $mode : 'email';
			$data['registration_gate'] = 'email';
			if (empty($data['show_lastname'])) { $data['require_lastname'] = 0; }
			if (empty($data['show_phone'])) { $data['require_phone'] = 0; }
			if (empty($data['show_company'])) { $data['require_company'] = 0; }
			$data['default_group'] = max(1, (int) (isset($data['default_group']) ? $data['default_group'] : 4));
			$data['password_min_length'] = max(8, min(72, (int) (isset($data['password_min_length']) ? $data['password_min_length'] : 8)));
			$data['verification_ttl'] = max(300, min(604800, (int) (isset($data['verification_ttl']) ? $data['verification_ttl'] : 86400)));
			$data['reset_ttl'] = max(300, min(604800, (int) (isset($data['reset_ttl']) ? $data['reset_ttl'] : 3600)));
			$data['deny_domains'] = trim((string) (isset($data['deny_domains']) ? $data['deny_domains'] : ''));
			$data['deny_emails'] = trim((string) (isset($data['deny_emails']) ? $data['deny_emails'] : ''));
			$data['checkout_policy_url'] = self::localPath(isset($data['checkout_policy_url']) ? $data['checkout_policy_url'] : '/privacy-policy');
			$data['checkout_policy_label'] = self::plainText(isset($data['checkout_policy_label']) ? $data['checkout_policy_label'] : '', 300, 'Я согласен с политикой конфиденциальности и обработкой персональных данных');
			$data['checkout_access_subject'] = self::plainText(isset($data['checkout_access_subject']) ? $data['checkout_access_subject'] : '', 190, 'Доступ к личному кабинету');
			$template = trim((string) (isset($data['checkout_access_template']) ? $data['checkout_access_template'] : ''));
			$data['checkout_access_template'] = $template === '' || strlen($template) > 60000 ? self::defaultCheckoutAccessTemplate() : $template;
			if (isset($data['pages']) && is_array($data['pages'])) {
				$data['pages_json'] = json_encode(self::normalizePages($data['pages']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} elseif (empty($data['pages_json'])) {
				$data['pages_json'] = json_encode(self::defaultPages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			unset($data['pages']);
			unset($data['id']);
			$allowed = array('registration_enabled','registration_mode','registration_gate','default_group','password_min_length','verification_ttl','reset_ttl','password_reset_enabled','require_firstname','show_lastname','require_lastname','show_phone','require_phone','show_company','require_company','deny_domains','deny_emails','checkout_registration_enabled','checkout_policy_url','checkout_policy_label','checkout_access_subject','checkout_access_template','pages_json','updated_at');
			return array_intersect_key($data, array_flip($allowed));
		}

		protected static function localPath($value)
		{
			$value = trim((string) $value);
			if ($value === '' || $value[0] !== '/' || strpos($value, '//') === 0 || strpos($value, '\\') !== false) {
				return '/privacy-policy';
			}

			return mb_substr($value, 0, 255, 'UTF-8');
		}

		protected static function plainText($value, $length, $default)
		{
			$value = trim(strip_tags((string) $value));
			return $value === '' ? $default : mb_substr($value, 0, (int) $length, 'UTF-8');
		}

		protected static function withPages(array $data)
		{
			$pages = json_decode(isset($data['pages_json']) ? (string) $data['pages_json'] : '', true);
			$data['pages'] = self::normalizePages(is_array($pages) ? $pages : array());
			return $data;
		}

		protected static function normalizePages(array $input)
		{
			$defaults = self::defaultPages();
			$pages = array();
			$used = array();
			$defaultOwners = array();
			foreach ($defaults as $defaultKey => $defaultPage) { $defaultOwners[$defaultPage['path']] = $defaultKey; }
			foreach ($defaults as $key => $default) {
				$page = isset($input[$key]) && is_array($input[$key]) ? array_merge($default, $input[$key]) : $default;
				$path = self::normalizePath(isset($page['path']) ? $page['path'] : $default['path']);
				if (isset($used[$path])) {
					throw new \InvalidArgumentException('Публичные URL страниц не должны повторяться.');
				}

				if (isset($defaultOwners[$path]) && $defaultOwners[$path] !== $key) {
					throw new \InvalidArgumentException('URL «' . $path . '» занят системным алиасом другой страницы.');
				}

				$used[$path] = true;
				$page['path'] = $path;
				foreach (array('title','description','submit_label') as $field) {
					$page[$field] = trim((string) (isset($page[$field]) ? $page[$field] : ''));
				}

				$page['template_id'] = max(1, (int) (isset($page['template_id']) ? $page['template_id'] : 1));
				if (!in_array($key, array('verify','logout'), true) && ($page['title'] === '' || $page['submit_label'] === '')) {
					throw new \InvalidArgumentException('Для каждой формы укажите заголовок и подпись кнопки.');
				}

				$pages[$key] = $page;
			}

			return $pages;
		}

		protected static function normalizePath($path)
		{
			$path = '/' . trim((string) $path, '/');
			if (!preg_match('#^/[a-z0-9][a-z0-9/_-]{0,119}$#', $path)
				|| strpos($path, '//') !== false
				|| preg_match('#^/(adminx|api|system|basket|checkout)(/|$)#', $path)
				|| preg_match('#^/personal/(orders|favorites|viewed)(/|$)#', $path)) {
				throw new \InvalidArgumentException('URL должен начинаться с / и содержать только латиницу, цифры, дефис и подчёркивание.');
			}

			return rtrim($path, '/');
		}

		protected static function defaultPages()
		{
			return array(
				'login' => array('path' => '/login', 'title' => 'Вход', 'description' => 'Войдите в личный кабинет.', 'submit_label' => 'Войти', 'template_id' => 1),
				'register' => array('path' => '/register', 'title' => 'Регистрация', 'description' => 'Создайте аккаунт покупателя.', 'submit_label' => 'Зарегистрироваться', 'template_id' => 1),
				'remember' => array('path' => '/remember', 'title' => 'Восстановление пароля', 'description' => 'Отправим одноразовую ссылку на email, указанный при регистрации.', 'submit_label' => 'Отправить ссылку', 'template_id' => 1),
				'reset' => array('path' => '/password/reset', 'title' => 'Новый пароль', 'description' => 'Задайте новый пароль для входа.', 'submit_label' => 'Сохранить пароль', 'template_id' => 1),
				'overview' => array('path' => '/personal/overview', 'title' => 'Личный кабинет', 'description' => 'Заказы, избранное и быстрый доступ к данным аккаунта.', 'submit_label' => 'Изменить профиль', 'template_id' => 1),
				'profile' => array('path' => '/personal', 'title' => 'Данные профиля', 'description' => 'Контактные данные и настройки аккаунта.', 'submit_label' => 'Сохранить', 'template_id' => 1),
				'password' => array('path' => '/personal/password', 'title' => 'Смена пароля', 'description' => 'Для безопасности укажите текущий пароль.', 'submit_label' => 'Изменить пароль', 'template_id' => 1),
				'verify' => array('path' => '/register/verify', 'title' => '', 'description' => '', 'submit_label' => '', 'template_id' => 1),
				'logout' => array('path' => '/logout', 'title' => '', 'description' => '', 'submit_label' => '', 'template_id' => 1),
			);
		}

		protected static function table()
		{
			return PublicUserTables::table('auth_settings');
		}

		protected static function reportUnavailable(\Throwable $error)
		{
			if (self::$reported) { return; }
			self::$reported = true;
			error_log('Public auth settings are unavailable: ' . $error->getMessage());
		}
	}
