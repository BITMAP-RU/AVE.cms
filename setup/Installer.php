<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         setup/Installer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('AVE_SETUP') || die('Direct access to this location is not allowed.');

	class AveInstaller
	{
		const ADMIN_PASSWORD_MIN_LENGTH = 8;

		protected $root;

		protected $locale;

		public function __construct($root, $locale = 'ru')
		{
			$this->root = rtrim(str_replace('\\', '/', (string) $root), '/');
			$this->locale = in_array((string) $locale, array('ru', 'en'), true) ? (string) $locale : 'ru';
			defined('BASEPATH') || define('BASEPATH', $this->root);
		}

		public function officialRepositoryProfile()
		{
			try {
				$this->loadDistributionProfile();
				$profile = \App\Common\DistributionProfile::official();
				return array('available' => true, 'error' => '', 'profile' => $profile);
			} catch (Throwable $e) {
				return array('available' => false, 'error' => $e->getMessage(), 'profile' => null);
			}
		}

		public function validateRepositorySelection($mode, $customProfile = '')
		{
			$mode = strtolower(trim((string) $mode));
			if ($mode === 'disabled') {
				return array('mode' => 'disabled', 'profile' => null);
			}

			$this->loadDistributionProfile();
			if ($mode === 'official') {
				return array('mode' => 'official', 'profile' => \App\Common\DistributionProfile::official());
			}

			if ($mode === 'custom') {
				if (trim((string) $customProfile) === '') {
					throw new InvalidArgumentException('Вставьте профиль подключения, полученный от издателя.');
				}

				return array('mode' => 'custom', 'profile' => \App\Common\DistributionProfile::decode($customProfile));
			}

			throw new InvalidArgumentException('Выберите способ подключения обновлений и модулей.');
		}

		public function requirements()
		{
			$checks = array(
				$this->check('PHP 7.3 или новее', PHP_VERSION_ID >= 70300, PHP_VERSION),
				$this->check('Расширение mysqli', extension_loaded('mysqli')),
				$this->check('Расширение mbstring', extension_loaded('mbstring')),
				$this->check('Расширение JSON', extension_loaded('json')),
				$this->check('Расширение OpenSSL', extension_loaded('openssl')),
				$this->check('Каталог configs доступен для записи', $this->writablePath($this->root . '/configs')),
				$this->check('Каталог storage доступен для записи', $this->writablePath($this->root . '/storage')),
				$this->check('Каталог tmp доступен для записи', $this->writablePath($this->root . '/tmp')),
				$this->check('Каталог uploads доступен для записи', $this->writablePath($this->root . '/uploads')),
				$this->check('Core-схема найдена', is_file(__DIR__ . '/schema.sql')),
			);
			$checks[] = $this->check('GD и WebP', $this->gdWebpAvailable(), 'Необязательно для установки', false);
			return $checks;
		}

		public function requirementsPassed(array $checks)
		{
			foreach ($checks as $check) {
				if (!empty($check['required']) && empty($check['passed'])) {
					return false;
				}
			}

			return true;
		}

		public function existingDatabaseConfig()
		{
			$file = $this->root . '/configs/db.config.php';
			if (!is_file($file) || filesize($file) === 0) {
				return null;
			}

			$config = null;
			include $file;
			return is_array($config) ? $config : null;
		}

		public function installationState()
		{
			if (is_file($this->lockFile())) {
				return array('installed' => true, 'reason' => 'Установка уже завершена.');
			}

			$config = $this->existingDatabaseConfig();
			if (!$config) {
				return array('installed' => false, 'reason' => '');
			}

			try {
				$db = $this->connect($config);
				$prefix = $this->validatePrefix(isset($config['dbpref']) ? $config['dbpref'] : '');
				if ($this->tableExists($db, $prefix . '_users')
					&& $this->tableCount($db, $prefix . '_users') > 0) {
					return array('installed' => true, 'reason' => 'В настроенной базе уже есть системный пользователь.');
				}
			} catch (Exception $e) {
				return array('installed' => false, 'reason' => '');
			}

			return array('installed' => false, 'reason' => '');
		}

		public function install(array $database, array $site, array $options = array())
		{
			$writeFiles = !isset($options['write_files']) || (bool) $options['write_files'];
			$progress = isset($options['progress']) && is_callable($options['progress']) ? $options['progress'] : null;
			$resetPrefix = !empty($options['reset_prefix']);
			$resetConfirmation = isset($options['reset_prefix_confirmation'])
				? trim((string) $options['reset_prefix_confirmation'])
				: '';
			$repositories = isset($options['repositories']) && is_array($options['repositories'])
				? $options['repositories']
				: array('mode' => 'disabled', 'profile' => null);
			$this->reportProgress($progress, 8, 'Проверяем данные', 'Параметры базы данных и администратора');
			$database = $this->validateDatabase($database);
			$site = $this->validateSite($site);
			if ($resetPrefix && !hash_equals($database['dbpref'], $resetConfirmation)) {
				throw new InvalidArgumentException('Для очистки таблиц введите точный префикс: ' . $database['dbpref']);
			}

			$this->reportProgress($progress, 18, 'Подключаемся к базе данных', $database['dbhost'] . ' / ' . $database['dbname']);
			$db = $this->connect($database);
			$prefix = $database['dbpref'];
			$initialTables = $this->prefixedTables($db, $prefix);
			$hadDatabaseConfig = is_file($this->root . '/configs/db.config.php');
			$adminChange = null;
			if ($resetPrefix) {
				$this->reportProgress($progress, 24, 'Очищаем таблицы префикса', 'Удаляем объекты ' . $prefix . '_* без возможности восстановления');
				$removedObjects = $this->dropPrefixedObjects($db, $prefix);
				$initialTables = array();
				$this->reportProgress($progress, 30, 'Таблицы префикса удалены', 'Удалено объектов: ' . $removedObjects);
			}

			$this->assertPristine($db, $prefix);
			if (!$resetPrefix) {
				$this->reportProgress($progress, 30, 'Подключение проверено', 'Префикс ' . $prefix . ' свободен');
			}

			try {
				$this->reportProgress($progress, 38, 'Создаём структуру базы данных', 'Таблицы ядра и необходимые индексы');
				$this->applySchema($db, $prefix);
				$this->reportProgress($progress, 64, 'Структура базы данных готова', 'Начинаем заполнение системных данных');
				$db->begin_transaction();
				$this->reportProgress($progress, 70, 'Создаём начальные данные', 'Администратор, настройки, рубрика и документы');
				$summary = $this->seed($db, $prefix, $site, $repositories);
				$db->commit();
				$this->reportProgress($progress, 84, 'Начальные данные созданы', 'Стартовая страница и страница 404 готовы');

				if ($writeFiles) {
					$this->reportProgress($progress, 90, 'Записываем конфигурацию', 'Подключение к базе и адрес панели управления');
					$this->writeDatabaseConfig($database);
					$adminChange = $this->applyAdminDirectory($site['admin_directory']);
					$this->reportProgress($progress, 96, 'Очищаем кеш', 'Подготавливаем первый запуск сайта');
					$this->clearRuntimeCache();
					$this->writeLock($prefix, $site['admin_email'], $site['admin_directory']);
				}

				$summary['tables'] = count($this->prefixedTables($db, $prefix));
				$summary['modules'] = $this->tableCount($db, $prefix . '_modules');
				$summary['admin_directory'] = $site['admin_directory'];
				$this->reportProgress($progress, 100, 'Установка завершена', 'AVE.cms готова к работе');
				return $summary;
			} catch (Throwable $e) {
				@$db->rollback();
				if ($adminChange) {
					$this->rollbackAdminDirectory($adminChange);
				}

				if (!$initialTables) {
					$this->dropPrefixedTables($db, $prefix);
					if ($writeFiles && !$hadDatabaseConfig) {
						@unlink($this->root . '/configs/db.config.php');
						@unlink($this->lockFile());
					}
				}

				throw $e;
			}
		}

		protected function reportProgress($callback, $percent, $title, $detail = '')
		{
			if (!$callback) { return; }
			call_user_func($callback, array(
				'percent' => max(0, min(100, (int) $percent)),
				'title' => (string) $title,
				'detail' => (string) $detail,
			));
		}

		public function validateDatabase(array $database)
		{
			$out = array(
				'dbhost' => trim(isset($database['dbhost']) ? (string) $database['dbhost'] : ''),
				'dbport' => max(0, (int) (isset($database['dbport']) ? $database['dbport'] : 3306)),
				'dbname' => trim(isset($database['dbname']) ? (string) $database['dbname'] : ''),
				'dbuser' => trim(isset($database['dbuser']) ? (string) $database['dbuser'] : ''),
				'dbpass' => isset($database['dbpass']) ? (string) $database['dbpass'] : '',
				'dbpref' => $this->validatePrefix(isset($database['dbpref']) ? $database['dbpref'] : ''),
				'dbchar' => 'utf8mb4',
				'dbsock' => null,
			);
			if ($out['dbhost'] === '' || $out['dbname'] === '' || $out['dbuser'] === '') {
				throw new InvalidArgumentException('Заполните адрес, имя базы и пользователя БД.');
			}

			if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $out['dbname'])) {
				throw new InvalidArgumentException('Некорректное имя базы данных.');
			}

			return $out;
		}

		public function validateSite(array $site)
		{
			$out = array(
				'site_name' => trim(isset($site['site_name']) ? (string) $site['site_name'] : ''),
				'admin_name' => trim(isset($site['admin_name']) ? (string) $site['admin_name'] : ''),
				'admin_login' => trim(isset($site['admin_login']) ? (string) $site['admin_login'] : ''),
				'admin_email' => trim(isset($site['admin_email']) ? (string) $site['admin_email'] : ''),
					'admin_password' => isset($site['admin_password']) ? (string) $site['admin_password'] : '',
					'admin_directory' => $this->validateAdminDirectory(isset($site['admin_directory']) ? $site['admin_directory'] : 'adminx'),
					'admin_language' => isset($site['admin_language']) && in_array($site['admin_language'], array('ru', 'en'), true)
						? (string) $site['admin_language']
						: $this->locale,
					'site_url' => rtrim(trim(isset($site['site_url']) ? (string) $site['site_url'] : ''), '/'),
			);
			if ($out['site_name'] === '' || $out['admin_name'] === '' || $out['admin_login'] === '') {
				throw new InvalidArgumentException('Укажите название сайта, имя и логин администратора.');
			}

			if (!filter_var($out['admin_email'], FILTER_VALIDATE_EMAIL)) {
				throw new InvalidArgumentException('Укажите корректный email администратора.');
			}

			$siteUrl = parse_url($out['site_url']);
			if (!is_array($siteUrl) || !in_array(strtolower(isset($siteUrl['scheme']) ? $siteUrl['scheme'] : ''), array('http', 'https'), true)
				|| empty($siteUrl['host']) || isset($siteUrl['user']) || isset($siteUrl['pass']) || isset($siteUrl['query']) || isset($siteUrl['fragment'])) {
				throw new InvalidArgumentException('Укажите полный публичный адрес сайта без параметров, например https://example.org.');
			}

			if (!preg_match('/^[A-Za-z0-9_.@-]{3,150}$/', $out['admin_login'])) {
				throw new InvalidArgumentException('Логин содержит недопустимые символы.');
			}

			if (mb_strlen($out['admin_password'], 'UTF-8') < self::ADMIN_PASSWORD_MIN_LENGTH) {
				throw new InvalidArgumentException(
					'Пароль администратора должен содержать не менее ' . self::ADMIN_PASSWORD_MIN_LENGTH . ' символов.'
				);
			}

			return $out;
		}

		public function configuredAdminDirectory()
		{
			$file = $this->root . '/configs/public.config.php';
			$config = is_file($file) ? include $file : array();
			$directory = is_array($config) && isset($config['admin_directory']) ? $config['admin_directory'] : 'adminx';
			try {
				return $this->validateAdminDirectory($directory, false);
			} catch (Throwable $e) {
				return 'adminx';
			}
		}

		protected function validateAdminDirectory($directory, $checkTarget = true)
		{
			$directory = trim((string) $directory);
			if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,47}$/', $directory)) {
				throw new InvalidArgumentException('Имя папки панели: латинская буква в начале, далее буквы, цифры, - или _.');
			}

			$reserved = array('api', 'configs', 'fields', 'install', 'modules', 'setup', 'storage', 'system', 'templates', 'tmp', 'uploads');
			if (in_array(strtolower($directory), $reserved, true)) {
				throw new InvalidArgumentException('Это имя зарезервировано системным каталогом: ' . $directory);
			}

			$source = $this->root . '/adminx';
			$target = $this->root . '/' . $directory;
			if ($checkTarget && !is_dir($source)) {
				throw new RuntimeException('В сборке отсутствует исходная папка панели управления.');
			}

			if ($checkTarget && $directory !== 'adminx' && file_exists($target)) {
				throw new InvalidArgumentException('Каталог /' . $directory . ' уже существует. Выберите другое имя панели.');
			}

			if ($checkTarget && $directory !== 'adminx' && !is_writable($this->root)) {
				throw new RuntimeException('Сервер не может переименовать исходную папку панели. Проверьте права на корень сайта.');
			}

			return $directory;
		}

		protected function seed(mysqli $db, $prefix, array $site, array $repositories)
		{
			$now = time();
			$date = date('Y-m-d H:i:s', $now);
			$password = password_hash($site['admin_password'], PASSWORD_BCRYPT, array('cost' => 12));

			$roles = array(
				1 => array('admin', 'Администратор'),
				2 => array('guest', 'Гостевая'),
				3 => array('moderator', 'Модератор'),
				4 => array('user', 'Пользователи'),
			);
			foreach ($roles as $id => $role) {
				$this->execute($db, 'INSERT INTO `' . $prefix . '_roles` (id,code,name,is_system,created_at,updated_at) VALUES (?,?,?,?,?,?)',
					'ississ', array($id, $role[0], $role[1], 1, $date, $date));
			}

			$this->execute($db, 'INSERT INTO `' . $prefix . '_users` (id,name,email,password_hash,role,is_active,created_at,updated_at,login,first_name,last_name,legacy_id) VALUES (1,?,?,?,?,?,?,?,?,?,?,1)',
				'ssssisssss', array($site['admin_name'], $site['admin_email'], $password, 'admin', 1, $date, $date, $site['admin_login'], $site['admin_name'], ''));

			$permissions = array(
				array('admin_panel', 'core', 'Доступ в панель управления', 'Разрешает вход в панель управления.', 1),
				array('all_permissions', 'core', 'Полный доступ', 'Даёт администратору доступ ко всем возможностям.', 2),
				array('view_public_debug', 'core', 'Публичная панель отладки', 'Разрешает просмотр служебной панели публичного сайта.', 3),
				array('view_development_site', 'core', 'Просмотр сайта в режиме разработки', 'Разрешает видеть публичный сайт, закрытый для посетителей и поисковых систем.', 4),
			);
			foreach ($permissions as $permission) {
				$this->execute($db, 'INSERT INTO `' . $prefix . '_permissions` (code,module,group_code,name,description,sort_order) VALUES (?,\'admin\',?,?,?,?)',
					'ssssi', array($permission[0], $permission[1], $permission[2], $permission[3], $permission[4]));
			}

			$this->execute($db, 'INSERT INTO `' . $prefix . '_role_permissions` (role_id,permission_code) VALUES (1,\'all_permissions\')');

				$this->seedSettings($db, $prefix, $site['site_name'], $site['admin_language'], $repositories);
			$this->seedConstants($db, $prefix, $date, isset($site['site_url']) ? $site['site_url'] : '');
			$this->seedCoreMigrationLedger($db, $prefix, $date);
			$this->seedPublicIdentity($db, $prefix, $site['site_name'], $site['admin_name'], $site['admin_login'], $site['admin_email'], $password, $date);
			$this->seedContent($db, $prefix, $site['site_name'], $now);

			return array(
				'admin_id' => 1,
				'template_id' => 1,
				'rubric_id' => 1,
				'field_id' => 1,
				'document_id' => 1,
				'not_found_document_id' => 2,
				'repository_mode' => isset($repositories['mode']) ? (string) $repositories['mode'] : 'disabled',
				'repository_name' => isset($repositories['profile']['name']) ? (string) $repositories['profile']['name'] : '',
			);
		}

		protected function seedSettings(mysqli $db, $prefix, $siteName, $adminLanguage, array $repositories)
		{
			$profile = isset($repositories['profile']) && is_array($repositories['profile']) ? $repositories['profile'] : null;
			$enabled = $profile !== null;
			$settings = array(
					'site_name' => array($siteName, 'string'),
					'admin_default_language' => array($adminLanguage, 'string'),
				'site_access_mode' => array('public', 'string'),
				'site_development_title' => array('Сайт готовится к запуску', 'string'),
				'site_development_message' => array('Мы завершаем настройку сайта. Пожалуйста, зайдите немного позже.', 'string'),
				'page_not_found_id' => array('2', 'int'),
				'date_format' => array('%d.%m.%Y', 'string'),
				'time_format' => array('%d.%m.%Y, %H:%M', 'string'),
				'default_country' => array('RU', 'string'),
				'mail_type' => array('mail', 'string'),
				'mail_content_type' => array('text/plain', 'string'),
				'mail_from' => array('', 'string'),
				'mail_from_name' => array($siteName, 'string'),
				'mail_new_user' => array("Здравствуйте, %NAME%!\n\nВаш аккаунт на сайте %HOST% создан. Теперь вы можете войти, используя данные регистрации.", 'string'),
				'mail_signature' => array('С уважением,' . "\n" . $siteName, 'string'),
				'navi_box' => array('<ul class="pagination">%s</ul>', 'string'),
				'start_label' => array('Первая', 'string'),
				'end_label' => array('Последняя', 'string'),
				'separator_label' => array('…', 'string'),
				'next_label' => array('»', 'string'),
				'prev_label' => array('«', 'string'),
				'total_label' => array('Страница %d из %d', 'string'),
				'link_box' => array('<li>%s</li>', 'string'),
				'total_box' => array('<span>%s</span>', 'string'),
				'active_box' => array('<li class="active">%s</li>', 'string'),
				'separator_box' => array('<li>%s</li>', 'string'),
				'bread_box' => array('<ol class="breadcrumb">%s</ol>', 'string'),
				'bread_separator' => array('<li aria-hidden="true">/</li>', 'string'),
				'bread_link_box' => array('<li>%s</li>', 'string'),
				'bread_link_template' => array('<a href="[link]">[name]</a>', 'string'),
				'bread_self_box' => array('<li aria-current="page">%s</li>', 'string'),
				'distribution.repository_mode' => array(isset($repositories['mode']) ? (string) $repositories['mode'] : 'disabled', 'string'),
				'distribution.repository_name' => array($enabled && isset($profile['name']) ? (string) $profile['name'] : '', 'string'),
				'core_updates.repository_enabled' => array($enabled ? '1' : '0', 'bool'),
				'core_updates.repository_url' => array($enabled ? (string) $profile['core_updates']['url'] : '', 'string'),
				'core_updates.repository_public_key' => array($enabled ? (string) $profile['core_updates']['public_key'] : '', 'code'),
				'modules.repository_enabled' => array($enabled ? '1' : '0', 'bool'),
				'modules.repository_url' => array($enabled ? (string) $profile['modules']['url'] : '', 'string'),
				'modules.repository_public_key' => array($enabled ? (string) $profile['modules']['public_key'] : '', 'code'),
			);
			foreach ($settings as $key => $definition) {
				$this->execute($db, 'INSERT INTO `' . $prefix . '_settings` (param,value,type) VALUES (?,?,?)',
					'sss', array($key, $definition[0], $definition[1]));
			}

			$this->execute($db,
				'INSERT INTO `' . $prefix . '_paginations` (id,pagination_name,pagination_box,pagination_start_label,pagination_end_label,pagination_separator_box,pagination_separator_label,pagination_next_label,pagination_prev_label,pagination_link_box,pagination_active_link_box,pagination_link_template,pagination_link_active_template) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?)',
				'ssssssssssss', array('По умолчанию', '<nav><ul>%s</ul></nav>', 'Первая', 'Последняя', '<li>%s</li>', '…', '»', '«', '<li>%s</li>', '<li class="active">%s</li>', '<a href="[link]">[name]</a>', '<span>[name]</span>'));
		}

		protected function loadDistributionProfile()
		{
			if (!class_exists('App\\Common\\DistributionProfile', false)) {
				require_once $this->root . '/system/App/Common/DistributionProfile.php';
			}
		}

		protected function seedConstants(mysqli $db, $prefix, $date, $siteUrl = '')
		{
			if (!class_exists('App\\Common\\RuntimeConstantSchema', false)) {
				require_once $this->root . '/system/App/Common/RuntimeConstantSchema.php';
			}

			$position = 10;
			foreach (\App\Common\RuntimeConstantSchema::all(false) as $name => $definition) {
				$value = $definition['default'];
				if ($name === 'PUBLIC_SITE_URL' && trim((string) $siteUrl) !== '') {
					$value = rtrim(trim((string) $siteUrl), '/');
				}

				if (is_bool($value)) {
					$value = $value ? '1' : '0';
					$type = 'bool';
				} elseif (is_int($value)) {
					$value = (string) $value;
					$type = 'int';
				} else {
					$value = (string) $value;
					$type = isset($definition['type']) ? (string) $definition['type'] : 'string';
				}

				$options = !empty($definition['options'])
					? json_encode(array_values($definition['options']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
					: '';
				$this->execute($db,
					'INSERT INTO `' . $prefix . '_constants` (name,value,type,group_code,label,description,options,is_system,sort_order,updated_at) VALUES (?,?,?,?,?,?,?,1,?,?)',
					'sssssssis', array($name, $value, $type, $definition['group_code'], $name, $definition['description'], $options, $position, $date));
				$position += 10;
			}
		}

		protected function seedCoreMigrationLedger(mysqli $db, $prefix, $date)
		{
			$manifest = require $this->root . '/setup/core-migrations.php';
			foreach ($manifest as $module => $definition) {
				$directory = $this->root . '/adminx/modules/' . $definition['directory'];
				if (!is_dir($directory) && !empty($definition['optional'])) {
					continue;
				}

				foreach ($definition['ids'] as $migration) {
					$base = $directory . '/migrations/' . $migration;
					$file = is_file($base . '.sql') ? $base . '.sql' : $base . '.php';
					if (!is_file($file)) {
						throw new RuntimeException('Core-миграция не найдена: ' . $module . '/' . $migration);
					}

					$checksum = sha1_file($file);
					$this->execute($db,
						'INSERT INTO `' . $prefix . '_module_migrations` (module,migration,checksum,applied_at) VALUES (?,?,?,?)',
						'ssss', array($module, $migration, $checksum, $date));
				}
			}
		}

		protected function seedPublicIdentity(mysqli $db, $prefix, $siteName, $adminName, $adminLogin, $adminEmail, $adminPassword, $date)
		{
			$publicName = mb_substr((string) $adminName, 0, 50, 'UTF-8');
			$publicLogin = mb_substr((string) $adminLogin, 0, 50, 'UTF-8');
			$groups = array(1 => 'Администраторы', 2 => 'Гости', 3 => 'Модераторы', 4 => 'Пользователи');
			foreach ($groups as $id => $name) {
				$this->execute($db, 'INSERT INTO `' . $prefix . '_public_user_groups` (user_group,user_group_name,status,set_default_avatar,default_avatar,user_group_permission) VALUES (?,?,\'1\',\'0\',\'\',\'\')',
					'is', array($id, $name));
			}

			$this->execute($db,
				'INSERT INTO `' . $prefix . '_public_users` (Id,password,email,email_verified_at,firstname,user_name,user_group,reg_time,status,country,deleted) VALUES (1,?,?,?,?,?,?,?,\'1\',\'RU\',\'0\')',
				'ssissii', array($adminPassword, $adminEmail, time(), $publicName, $publicLogin, 1, time()));

			$checkoutPolicy = 'Я согласен с политикой конфиденциальности и обработкой персональных данных';
			$checkoutSubject = 'Доступ к личному кабинету';
			$checkoutTemplate = '<h2>Личный кабинет создан</h2>'
				. '<p>{% if user.firstname %}{{ user.firstname }}, {% endif %}ваш заказ №{{ order.id }} оформлен, а личный кабинет уже доступен.</p>'
				. '<p>Логин: <strong>{{ user.email }}</strong></p>'
				. '<p><a href="{{ access_url }}">Задать пароль</a></p>'
				. '<p>Ссылка действует {{ expires_hours }} ч. Если она истечёт, запросите новую на странице восстановления пароля.</p>';
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_public_auth_settings` (id,registration_enabled,registration_mode,registration_gate,default_group,password_min_length,verification_ttl,reset_ttl,password_reset_enabled,require_firstname,show_lastname,require_lastname,show_phone,require_phone,show_company,require_company,deny_domains,deny_emails,checkout_registration_enabled,checkout_policy_url,checkout_policy_label,checkout_access_subject,checkout_access_template,pages_json,updated_at) VALUES (1,0,\'email\',\'email\',4,8,86400,3600,0,1,1,0,0,0,0,0,\'\',\'\',1,\'/privacy-policy\',?,?,?,NULL,?)',
				'ssss', array($checkoutPolicy, $checkoutSubject, $checkoutTemplate, $date));

			$message = "Здравствуйте %NAME%,\n\nВаш аккаунт на сайте %HOST% создан.\n";
			$signature = 'С уважением, ' . $siteName;
			$forbidden = '<h1>Доступ запрещён</h1><p>У вас нет прав для просмотра этой страницы.</p>';
			$hidden = '<div class="hidden-box">Содержимое скрыто.</div>';
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_public_settings` (Id,site_name,mail_type,mail_content_type,mail_port,mail_host,mail_smtp_login,mail_smtp_pass,mail_smtp_encrypt,mail_sendmail_path,mail_word_wrap,mail_from,mail_from_name,mail_new_user,mail_signature,page_not_found_id,message_forbidden,navi_box,start_label,end_label,separator_label,next_label,prev_label,total_label,link_box,total_box,active_box,separator_box,bread_box,bread_show_main,bread_show_host,bread_separator,bread_separator_use,bread_link_box,bread_link_template,bread_self_box,bread_link_box_last,date_format,time_format,default_country,use_doctime,hidden_text) VALUES (1,?,\'mail\',\'text/plain\',25,\'\',\'\',\'\',NULL,\'/usr/sbin/sendmail\',80,?,?,?, ?,2,?,\'<ul class="pagination">%s</ul>\',\'Первая\',\'Последняя\',\'…\',\'»\',\'«\',\'Страница %d из %d\',\'<li>%s</li>\',\'<span>%s</span>\',\'<span class="active">%s</span>\',\'<li>%s</li>\',\'<ol class="breadcrumb">%s</ol>\',\'1\',\'0\',\'<li>→</li>\',\'0\',\'<li>%s</li>\',\'<a href="[link]">[name]</a>\',\'<li>%s</li>\',\'1\',\'%d.%m.%Y\',\'%d.%m.%Y, %H:%M\',\'RU\',\'0\',?)',
				'sssssss', array($siteName, $adminEmail, $siteName, $message, $signature, $forbidden, $hidden));
		}

		protected function seedContent(mysqli $db, $prefix, $siteName, $now)
		{
			$template = '<!doctype html>' . "\n"
				. '<html lang="ru">' . "\n<head>\n"
				. '  <meta charset="utf-8">' . "\n"
				. '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
				. '  <title>[tag:title]</title>' . "\n"
				. '  <meta name="description" content="[tag:description]">' . "\n"
				. '  <meta name="keywords" content="[tag:keywords]">' . "\n"
				. '  <meta name="robots" content="[tag:robots]">' . "\n"
				. '  <link rel="canonical" href="[tag:canonical]">' . "\n"
				. '  <meta property="og:type" content="website">' . "\n"
				. '  <meta property="og:title" content="[tag:og:title]">' . "\n"
				. '  <meta property="og:description" content="[tag:og:description]">' . "\n"
				. '  <meta property="og:url" content="[tag:og:url]">' . "\n"
				. '  <meta property="og:site_name" content="[tag:og:sitename]">' . "\n"
				. '</head>' . "\n<body>\n"
				. '[tag:navigation:main]' . "\n"
				. '[tag:maincontent]' . "\n"
				. '</body>' . "\n</html>";
			$this->execute($db, 'INSERT INTO `' . $prefix . '_templates` (Id,template_title,template_text,template_author_id,template_created) VALUES (1,?,?,1,?)',
				'ssi', array('Основной шаблон', $template, $now));

			$this->execute($db,
				'INSERT INTO `' . $prefix . '_rubrics` (Id,rubric_title,rubric_alias,rubric_alias_history,rubric_template,rubric_template_id,rubric_author_id,rubric_created,rubric_docs_active,rubric_start_code,rubric_code_start,rubric_code_end,rubric_teaser_template,rubric_header_template,rubric_og_template,rubric_footer_template,rubric_linked_rubric,rubric_description,rubric_meta_gen,rubric_position,rubric_changed,rubric_changed_fields) VALUES (1,\'Главная страница\',\'\',\'0\',\'<main><h1>[tag:fld:title]</h1><div class="page-content">[tag:fld:content]</div></main>\',1,1,?,1,\'\',\'\',\'\',\'\',\'\',\'\',\'\',\'0\',\'Стартовая рубрика чистой установки\',\'0\',10,?,?)',
				'iii', array($now, $now, $now));
			$titleFieldSettings = json_encode(array('width' => 'full'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$contentFieldSettings = json_encode(array('width' => 'full', 'height' => 300), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_rubric_fields` (Id,rubric_id,rubric_field_group,rubric_field_alias,rubric_field_title,rubric_field_type,rubric_field_numeric,rubric_field_position,rubric_field_default,rubric_field_settings,rubric_field_search,rubric_field_template,rubric_field_template_request,rubric_field_description) VALUES (1,1,NULL,\'title\',\'Заголовок страницы\',\'single_line\',\'0\',1,\'\',?,\'1\',\'\',\'\',\'Заголовок внутри содержимого страницы\')',
				's', array($titleFieldSettings));
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_rubric_fields` (Id,rubric_id,rubric_field_group,rubric_field_alias,rubric_field_title,rubric_field_type,rubric_field_numeric,rubric_field_position,rubric_field_default,rubric_field_settings,rubric_field_search,rubric_field_template,rubric_field_template_request,rubric_field_description) VALUES (2,1,NULL,\'content\',\'Содержимое страницы\',\'richtext\',\'0\',2,\'\',?,\'1\',\'\',\'\',\'Основной текст страницы с визуальным редактором\')',
				's', array($contentFieldSettings));

			foreach (array(1 => 'docread|alles|new|newnow|editown|delrev', 2 => 'docread', 3 => 'docread|alles|new|newnow|editown|delrev', 4 => 'docread') as $group => $permission) {
				$this->execute($db, 'INSERT INTO `' . $prefix . '_rubric_permissions` (rubric_id,user_group_id,rubric_permission) VALUES (1,?,?)',
					'is', array($group, $permission));
			}

			$title = 'AVE.cms установлена';
			$description = 'Чистая установка ' . $siteName . ' завершена успешно.';
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_documents` (Id,rubric_id,rubric_tmpl_id,document_parent,document_alias,document_alias_header,document_alias_history,document_short_alias,document_title,document_breadcrumb_title,document_published,document_expire,document_changed,document_author_id,document_in_search,document_meta_keywords,document_meta_description,document_meta_robots,document_sitemap_freq,document_sitemap_pr,document_status,document_deleted,document_count_print,document_count_view,document_linked_navi_id,document_excerpt,document_tags,document_property,document_position,module_catalog,guid) VALUES (1,1,0,0,\'/\',301,\'0\',\'\',?,?,?,0,?,1,\'1\',\'AVE.cms, установка\',?,\'index,follow\',3,0.5,\'1\',\'0\',0,0,0,\'\',\'\',\'\',0,\'\',\'\')',
				'ssiis', array($title, 'Главная', $now, $now, $description));
			$content = '<p>Чистая установка завершена успешно. Войдите в панель управления, создайте рубрики и документы, настройте шаблоны и подключите нужные модули.</p><p>Справка по разработке и возможностям системы находится в каталоге <code>help</code>.</p>';
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields` (Id,rubric_field_id,document_id,field_number_value,field_value,document_in_search) VALUES (1,1,1,0,\'Поздравляем с установкой AVE.cms!\',\'1\')');
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields` (Id,rubric_field_id,document_id,field_number_value,field_value,document_in_search) VALUES (2,2,1,0,\'\',\'1\')');
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields_text` (Id,rubric_field_id,document_id,field_value) VALUES (2,2,1,?)',
				's', array($content));

			$notFoundTitle = 'Страница не найдена';
			$notFoundDescription = 'Запрошенная страница не найдена.';
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_documents` (Id,rubric_id,rubric_tmpl_id,document_parent,document_alias,document_alias_header,document_alias_history,document_short_alias,document_title,document_breadcrumb_title,document_published,document_expire,document_changed,document_author_id,document_in_search,document_meta_keywords,document_meta_description,document_meta_robots,document_sitemap_freq,document_sitemap_pr,document_status,document_deleted,document_count_print,document_count_view,document_linked_navi_id,document_excerpt,document_tags,document_property,document_position,module_catalog,guid) VALUES (2,1,0,0,\'404\',301,\'0\',\'\',?,?,?,0,?,1,\'0\',\'\',?,\'noindex,nofollow\',3,0,\'1\',\'0\',0,0,0,\'\',\'\',\'\',2,\'\',\'\')',
				'ssiis', array($notFoundTitle, 'Ошибка 404', $now, $now, $notFoundDescription));
			$notFoundContent = '<p>Запрошенная страница не существует или была перемещена.</p><p><a href="/">Перейти на главную</a></p>';
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields` (Id,rubric_field_id,document_id,field_number_value,field_value,document_in_search) VALUES (3,1,2,0,\'Страница не найдена\',\'0\')');
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields` (Id,rubric_field_id,document_id,field_number_value,field_value,document_in_search) VALUES (4,2,2,0,\'\',\'0\')');
			$this->execute($db, 'INSERT INTO `' . $prefix . '_document_fields_text` (Id,rubric_field_id,document_id,field_value) VALUES (4,2,2,?)',
				's', array($notFoundContent));

			$this->execute($db,
				'INSERT INTO `' . $prefix . '_navigation` (navigation_id,alias,title,level1,level2,level3,level1_active,level2_active,level3_active,level1_begin,level1_end,level2_begin,level2_end,level3_begin,level3_end,begin,end,user_group,expand_ext) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
				'ssssssssssssssssss', array(
					'main', 'Основная навигация',
					'<li class="site-navigation-item"><a target="[tag:target]" href="[tag:link]">[tag:linkname]</a></li>', '', '',
					'<li class="site-navigation-item is-active"><a target="[tag:target]" href="[tag:link]" aria-current="page">[tag:linkname]</a></li>', '', '',
					'<ul class="site-navigation-list">[tag:content]', '</ul>', '', '', '', '',
					'<nav class="site-navigation" aria-label="Основная навигация">', '</nav>', '1,2,3,4', '1',
				));
			$this->execute($db,
				'INSERT INTO `' . $prefix . '_navigation_items` (navigation_item_id,navigation_id,document_id,alias,title,description,target,image,css_style,css_id,css_class,parent_id,level,position,status) VALUES (1,1,1,\'/\',\'Главная\',\'Главная страница сайта\',\'_self\',\'\',NULL,NULL,\'home\',0,\'1\',1,\'1\')');
		}

		protected function applySchema(mysqli $db, $prefix)
		{
			$sql = file_get_contents(__DIR__ . '/schema.sql');
			if ($sql === false || trim($sql) === '') {
				throw new RuntimeException('Core-схема setup/schema.sql пуста или недоступна.');
			}

			$sql = str_replace('{{prefix}}', $prefix, $sql);
			if (strpos($sql, '{{') !== false) {
				throw new RuntimeException('В core-схеме остались неизвестные placeholder.');
			}

			if (!$db->multi_query($sql)) {
				throw new RuntimeException('Ошибка применения схемы: ' . $db->error);
			}

			do {
				if ($result = $db->store_result()) {
					$result->free();
				}

				if (!$db->more_results()) {
					break;
				}
			} while ($db->next_result());
			if ($db->errno) {
				throw new RuntimeException('Ошибка применения схемы: ' . $db->error);
			}
		}

		protected function assertPristine(mysqli $db, $prefix)
		{
			foreach (array('users', 'documents', 'rubrics', 'templates', 'modules') as $suffix) {
				$table = $prefix . '_' . $suffix;
				if ($this->tableExists($db, $table) && $this->tableCount($db, $table) > 0) {
					throw new RuntimeException('Префикс «' . $prefix . '» уже содержит данные AVE.cms. Выберите пустой префикс.');
				}
			}
		}

		protected function connect(array $config)
		{
			mysqli_report(MYSQLI_REPORT_OFF);
			$port = !empty($config['dbport']) ? (int) $config['dbport'] : 3306;
			$db = @new mysqli($config['dbhost'], $config['dbuser'], $config['dbpass'], $config['dbname'], $port);
			if ($db->connect_errno) {
				throw new RuntimeException('Не удалось подключиться к БД: ' . $db->connect_error);
			}

			if (!$db->set_charset('utf8mb4')) {
				throw new RuntimeException('База данных не поддерживает utf8mb4.');
			}

			return $db;
		}

		protected function execute(mysqli $db, $sql, $types = '', array $values = array())
		{
			$statement = $db->prepare($sql);
			if (!$statement) {
				throw new RuntimeException('Ошибка подготовки seed-запроса: ' . $db->error);
			}

			if ($types !== '') {
				$params = array($types);
				foreach ($values as $key => $value) {
					$params[] = &$values[$key];
				}

				call_user_func_array(array($statement, 'bind_param'), $params);
			}

			if (!$statement->execute()) {
				$message = $statement->error;
				$statement->close();
				throw new RuntimeException('Ошибка заполнения core-данных: ' . $message);
			}

			$statement->close();
		}

		protected function writeDatabaseConfig(array $config)
		{
			$file = $this->root . '/configs/db.config.php';
			$payload = array(
				'dbhost' => $config['dbhost'],
				'dbuser' => $config['dbuser'],
				'dbpass' => $config['dbpass'],
				'dbname' => $config['dbname'],
				'dbpref' => $config['dbpref'],
				'dbchar' => 'utf8mb4',
				'dbport' => $config['dbport'] ?: null,
				'dbsock' => null,
			);
			$php = "<?php\n\n\$config = " . var_export($payload, true) . ";\n";
			$tmp = $file . '.tmp-' . bin2hex(random_bytes(6));
			if (file_put_contents($tmp, $php, LOCK_EX) === false || !@rename($tmp, $file)) {
				@unlink($tmp);
				throw new RuntimeException('Не удалось записать configs/db.config.php.');
			}

			@chmod($file, 0640);
		}

		protected function applyAdminDirectory($directory)
		{
			$directory = $this->validateAdminDirectory($directory);
			$source = $this->root . '/adminx';
			$target = $this->root . '/' . $directory;
			$configFile = $this->root . '/configs/public.config.php';
			$backup = is_file($configFile) ? (string) file_get_contents($configFile) : '';
			$renamed = false;

			try {
				if ($directory !== 'adminx') {
					if (!@rename($source, $target)) {
						throw new RuntimeException('Не удалось переименовать папку панели управления.');
					}

					$renamed = true;
				}

				$this->writeAdminDirectoryConfig($directory, $backup);
			} catch (Throwable $e) {
				if ($renamed && is_dir($target) && !file_exists($source)) {
					@rename($target, $source);
				}

				$this->restoreFile($configFile, $backup);
				throw $e;
			}

			return array(
				'directory' => $directory,
				'source' => $source,
				'target' => $target,
				'renamed' => $renamed,
				'config_file' => $configFile,
				'config_backup' => $backup,
			);
		}

		protected function rollbackAdminDirectory(array $change)
		{
			if (!empty($change['renamed']) && is_dir($change['target']) && !file_exists($change['source'])) {
				@rename($change['target'], $change['source']);
			}

			$this->restoreFile($change['config_file'], $change['config_backup']);
		}

		protected function writeAdminDirectoryConfig($directory, $content)
		{
			$file = $this->root . '/configs/public.config.php';
			$replacement = "\t\t'admin_directory' => " . var_export((string) $directory, true) . ',';
			$count = 0;
			$content = preg_replace(
				"/^\\s*'admin_directory'\\s*=>\\s*[^,]+,/m",
				$replacement,
				(string) $content,
				1,
				$count
			);
			if ($count !== 1 || !is_string($content)) {
				throw new RuntimeException('В configs/public.config.php отсутствует настройка admin_directory.');
			}

			$this->writeFile($file, $content, 0644, 'Не удалось записать имя папки панели управления.');
		}

		protected function restoreFile($file, $content)
		{
			if ((string) $content === '') {
				return;
			}

			$this->writeFile($file, (string) $content, 0644, '');
		}

		protected function writeFile($file, $content, $mode, $error)
		{
			$tmp = $file . '.tmp-' . bin2hex(random_bytes(6));
			if (file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) {
				@unlink($tmp);
				if ($error !== '') {
					throw new RuntimeException($error);
				}

				return false;
			}

			@chmod($file, $mode);
			return true;
		}

		protected function writeLock($prefix, $email, $adminDirectory)
		{
			$release = is_file($this->root . '/system/release.php')
				? require $this->root . '/system/release.php'
				: array();
			$packageManifestFile = $this->root . '/PACKAGE-MANIFEST.json';
			$packageManifest = is_file($packageManifestFile)
				? json_decode((string) file_get_contents($packageManifestFile), true)
				: array();
			$payload = json_encode(array(
				'installed_at' => date(DATE_ATOM),
				'version' => isset($release['version']) ? (string) $release['version'] : '3.3',
				'build' => isset($release['build']) ? (string) $release['build'] : '0.20',
				'prefix' => $prefix,
				'administrator' => $email,
				'admin_directory' => $adminDirectory,
				'core_fingerprint' => is_array($packageManifest) && isset($packageManifest['core_fingerprint'])
					? (string) $packageManifest['core_fingerprint']
					: '',
				'package_manifest_sha256' => is_file($packageManifestFile) ? hash_file('sha256', $packageManifestFile) : '',
			), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (file_put_contents($this->lockFile(), $payload . "\n", LOCK_EX) === false) {
				throw new RuntimeException('Не удалось создать storage/installed.lock.');
			}

			@chmod($this->lockFile(), 0640);
		}

		protected function clearRuntimeCache()
		{
			foreach (array($this->root . '/tmp/cache', $this->root . '/tmp/sessions') as $directory) {
				$this->clearDirectory($directory);
			}
		}

		protected function clearDirectory($directory)
		{
			if (!is_dir($directory)) {
				@mkdir($directory, 0775, true);
				return;
			}

			foreach (scandir($directory) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..') { continue; }
				$path = $directory . '/' . $entry;
				if (is_dir($path) && !is_link($path)) {
					$this->clearDirectory($path);
					@rmdir($path);
				} else {
					@unlink($path);
				}
			}
		}

		protected function dropPrefixedTables(mysqli $db, $prefix)
		{
			$this->dropPrefixedObjects($db, $prefix);
		}

		protected function dropPrefixedObjects(mysqli $db, $prefix)
		{
			$pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $prefix) . '\\_%';
			$statement = $db->prepare(
				'SELECT table_name, table_type FROM information_schema.tables '
				. 'WHERE table_schema=DATABASE() AND table_name LIKE ? ORDER BY table_type DESC, table_name'
			);
			if (!$statement) {
				throw new RuntimeException('Не удалось получить объекты базы данных: ' . $db->error);
			}

			$statement->bind_param('s', $pattern);
			if (!$statement->execute()) {
				throw new RuntimeException('Не удалось получить объекты префикса: ' . $statement->error);
			}

			$statement->bind_result($tableName, $tableType);

			$tables = array();
			$views = array();
			while ($statement->fetch()) {
				$name = '`' . str_replace('`', '``', (string) $tableName) . '`';
				if (strtoupper((string) $tableType) === 'VIEW') {
					$views[] = $name;
				} else {
					$tables[] = $name;
				}
			}

			$statement->close();

			$db->query('SET FOREIGN_KEY_CHECKS=0');
			try {
				foreach (array_chunk($views, 50) as $chunk) {
					if (!$db->query('DROP VIEW IF EXISTS ' . implode(',', $chunk))) {
						throw new RuntimeException('Не удалось удалить представления: ' . $db->error);
					}
				}

				foreach (array_chunk($tables, 50) as $chunk) {
					if (!$db->query('DROP TABLE IF EXISTS ' . implode(',', $chunk))) {
						throw new RuntimeException('Не удалось удалить таблицы: ' . $db->error);
					}
				}
			} finally {
				$db->query('SET FOREIGN_KEY_CHECKS=1');
			}

			return count($tables) + count($views);
		}

		protected function prefixedTables(mysqli $db, $prefix)
		{
			$pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $prefix) . '\\_%';
			$statement = $db->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ?');
			if (!$statement) {
				throw new RuntimeException('Не удалось получить список таблиц: ' . $db->error);
			}

			$statement->bind_param('s', $pattern);
			$statement->execute();
			$statement->bind_result($tableName);
			$tables = array();
			while ($statement->fetch()) {
				$tables[] = (string) $tableName;
			}

			$statement->close();
			return $tables;
		}

		protected function tableExists(mysqli $db, $table)
		{
			$statement = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
			if (!$statement) { return false; }
			$statement->bind_param('s', $table);
			$statement->execute();
			$statement->store_result();
			$exists = $statement->num_rows > 0;
			$statement->close();
			return $exists;
		}

		protected function tableCount(mysqli $db, $table)
		{
			if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				throw new InvalidArgumentException('Некорректное имя таблицы.');
			}

			$result = $db->query('SELECT COUNT(*) AS amount FROM `' . $table . '`');
			$row = $result ? $result->fetch_assoc() : null;
			return $row ? (int) $row['amount'] : 0;
		}

		protected function validatePrefix($prefix)
		{
			$prefix = trim((string) $prefix);
			if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $prefix)) {
				throw new InvalidArgumentException('Префикс должен начинаться с буквы и содержать только буквы, цифры и _.');
			}

			return $prefix;
		}

		protected function check($label, $passed, $detail = '', $required = true)
		{
			return array('label' => $label, 'passed' => (bool) $passed, 'detail' => (string) $detail, 'required' => (bool) $required);
		}

		protected function writablePath($path)
		{
			return is_dir($path) ? is_writable($path) : is_writable(dirname($path));
		}

		protected function gdWebpAvailable()
		{
			if (!extension_loaded('gd') || !function_exists('imagewebp')) {
				return false;
			}

			$info = function_exists('gd_info') ? gd_info() : array();
			return !array_key_exists('WebP Support', $info) || !empty($info['WebP Support']);
		}

		protected function lockFile()
		{
			return $this->root . '/storage/installed.lock';
		}
	}
