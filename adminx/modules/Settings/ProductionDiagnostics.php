<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Settings/ProductionDiagnostics.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Settings;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\ModuleManager;
	use App\Common\SystemTables;
	use App\Content\BasketTables;
	use App\Content\CatalogTables;
	use App\Content\ContactsTables;
	use App\Content\ContentTables;
	use App\Content\PublicShellTables;
	use App\Content\PublicUserTables;
	use App\Frontend\PublicSettings;

	/** Read-only production readiness checks. No mail, payment or data mutations. */
	class ProductionDiagnostics
	{
		public static function run()
		{
			$checks = array();
			$database = self::databaseInfo();
			self::checkRuntime($checks, $database);
			self::checkPaths($checks);
			self::checkMail($checks);
			self::checkContacts($checks);
			self::checkBasket($checks);
			self::checkPublicRuntime($checks);
			self::checkNativeDataTables($checks);
			self::checkStoredCodePrefixes($checks);
			self::checkMedia($checks);
			self::checkHosting($checks);
			$summary = array('ok' => 0, 'warning' => 0, 'error' => 0);
			foreach ($checks as $check) {
				$state = isset($summary[$check['state']]) ? $check['state'] : 'warning';
				$summary[$state]++;
			}

			$ready = $summary['error'] === 0;
			return array(
				'checks' => $checks,
				'groups' => self::groupChecks($checks),
				'summary' => $summary,
				'ready' => $ready,
				'database' => $database,
				'overview' => self::overview($summary, $database, $ready),
			);
		}

		protected static function checkRuntime(array &$checks, array $database)
		{
			self::add($checks, 'PHP', PHP_VERSION_ID >= 70300 && PHP_VERSION_ID < 80000 ? 'ok' : 'warning', PHP_VERSION, 'environment');
			self::add(
				$checks,
				'Сервер базы данных',
				$database['connected'] ? 'ok' : 'error',
				$database['connected']
					? $database['product'] . ' ' . $database['version'] . ' · ' . $database['charset'] . ' · ' . $database['collation']
					: 'не удалось получить версию сервера',
				'environment'
			);
			foreach (array('mysqli', 'mbstring', 'json', 'gd', 'xmlwriter', 'curl', 'zip') as $extension) {
				self::add($checks, 'Расширение ' . $extension, extension_loaded($extension) ? 'ok' : 'error', extension_loaded($extension) ? 'доступно' : 'не установлено', 'environment');
			}
		}

		protected static function checkPaths(array &$checks)
		{
			foreach (array('tmp/cache' => BASEPATH . '/tmp/cache', 'tmp/backup' => BASEPATH . '/tmp/backup', 'uploads' => BASEPATH . '/uploads') as $label => $path) {
				$ok = is_dir($path) && is_writable($path);
				self::add($checks, 'Запись ' . $label, $ok ? 'ok' : 'error', $ok ? 'доступна' : 'каталог отсутствует или недоступен', 'filesystem');
			}
		}

		protected static function checkMail(array &$checks)
		{
			$settings = PublicSettings::all();
			$type = isset($settings['mail_type']) ? strtolower(trim((string) $settings['mail_type'])) : '';
			self::add($checks, 'Почтовый транспорт', in_array($type, array('mail', 'smtp', 'sendmail'), true) ? 'ok' : 'error', $type !== '' ? $type : 'не задан', 'integrations');
			$from = isset($settings['mail_from']) ? trim((string) $settings['mail_from']) : '';
			self::add($checks, 'Email отправителя', filter_var($from, FILTER_VALIDATE_EMAIL) ? 'ok' : 'error', $from !== '' ? $from : 'не задан', 'integrations');
			if ($type === 'smtp') {
				$host = isset($settings['mail_host']) ? trim((string) $settings['mail_host']) : '';
				$port = isset($settings['mail_port']) ? (int) $settings['mail_port'] : 0;
				self::add($checks, 'SMTP endpoint', $host !== '' && $port > 0 ? 'ok' : 'error', $host . ($port ? ':' . $port : ''), 'integrations');
				$login = isset($settings['mail_smtp_login']) ? trim((string) $settings['mail_smtp_login']) : '';
				self::add($checks, 'SMTP авторизация', $login !== '' ? 'ok' : 'warning', $login !== '' ? 'логин задан' : 'логин не задан', 'integrations');
			}
		}

		protected static function checkContacts(array &$checks)
		{
			$module = ModuleManager::get('contacts');
			if (!$module || empty($module['enabled'])) {
				return;
			}

			$table = ContactsTables::table('module_contacts_forms');
			if (!self::tableExists($table)) { self::add($checks, 'Contacts', 'warning', 'таблица форм отсутствует', 'integrations'); return; }
			$rows = DB::query('SELECT id, title, mail_set FROM `' . $table . '`')->getAll();
			$forms = 0; $recipients = 0; $invalid = 0;
			foreach ($rows ?: array() as $row) {
				$forms++;
				$settings = self::decode((array) $row, 'mail_set');
				foreach ((array) (isset($settings['receivers']) ? $settings['receivers'] : array()) as $recipient) {
					$email = is_array($recipient) && isset($recipient['email']) ? trim((string) $recipient['email']) : '';
					if (filter_var($email, FILTER_VALIDATE_EMAIL)) { $recipients++; } else { $invalid++; }
				}
			}

			self::add($checks, 'Contacts', $forms > 0 && $recipients > 0 && $invalid === 0 ? 'ok' : ($invalid ? 'error' : 'warning'), $forms . ' форм, ' . $recipients . ' получателей' . ($invalid ? ', некорректных: ' . $invalid : ''), 'integrations');
		}

		protected static function checkBasket(array &$checks)
		{
			$module = ModuleManager::get('commerce');
			if (!$module || empty($module['enabled'])) { return; }
			$table = BasketTables::table('module_basket_settings');
			if (!self::tableExists($table)) { self::add($checks, 'Уведомления заказов', 'error', 'настройки корзины отсутствуют', 'integrations'); return; }
			$row = DB::query('SELECT receivers, from_email FROM `' . $table . '` ORDER BY id LIMIT 1')->getAssoc();
			$from = $row ? trim((string) $row['from_email']) : '';
			$valid = 0; $invalid = 0;
			foreach (preg_split('/[;,\s]+/', $row ? (string) $row['receivers'] : '') ?: array() as $email) {
				if ($email === '') { continue; }
				if (filter_var($email, FILTER_VALIDATE_EMAIL)) { $valid++; } else { $invalid++; }
			}

			self::add($checks, 'Уведомления заказов', $valid > 0 && !$invalid && filter_var($from, FILTER_VALIDATE_EMAIL) ? 'ok' : 'error', $valid . ' получателей, отправитель ' . ($from ?: 'не задан'), 'integrations');
		}

		protected static function checkPublicRuntime(array &$checks)
		{
			$native = array();
			foreach (ModuleManager::all() as $module) {
				$code = isset($module['code']) ? (string) $module['code'] : '';
				if ($code !== '' && !empty($module['enabled']) && is_file(BASEPATH . '/modules/' . $code . '/app/module.php')) {
					$native[] = $code;
				}
			}

			sort($native, SORT_NATURAL | SORT_FLAG_CASE);
			self::add($checks, 'Native-модули', $native ? 'ok' : 'warning', $native ? implode(', ', $native) : 'нет', 'runtime');
			self::add($checks, 'Публичный runtime', 'ok', 'native без fallback-переключателей', 'runtime');
		}

		protected static function checkNativeDataTables(array &$checks)
		{
			$tables = array();
			foreach (array(
				'documents', 'rubrics', 'rubric_fields', 'rubric_templates', 'templates',
				'request', 'request_conditions', 'navigation', 'navigation_items',
				'sysblocks', 'sysblocks_groups', 'view_count', 'document_relation_edges',
				'presentations', 'presentation_revisions', 'presentation_assignments',
				'directories', 'directory_items',
			) as $suffix) {
				$tables[] = ContentTables::table($suffix);
			}

			foreach (array('module_catalog_settings', 'module_catalog_items') as $suffix) {
				$tables[] = CatalogTables::table($suffix);
			}

			$contacts = ModuleManager::get('contacts');
			if ($contacts && !empty($contacts['enabled'])) {
				foreach (array('module_contacts_fields', 'module_contacts_forms', 'module_contacts_history') as $suffix) {
					$tables[] = ContactsTables::table($suffix);
				}
			}

			$commerce = ModuleManager::get('commerce');
			if ($commerce && !empty($commerce['enabled'])) {
				foreach (array(
					'module_basket', 'module_basket_delivery', 'module_basket_history',
					'module_basket_payment', 'module_basket_settings', 'module_basket_status',
				) as $suffix) {
					$tables[] = BasketTables::table($suffix);
				}
			}

			foreach (array('users', 'users_session', 'auth_tokens', 'auth_settings', 'user_profile_fields', 'user_profile_values', 'user_groups') as $suffix) {
				$tables[] = PublicUserTables::table($suffix);
			}

			foreach (array('settings', 'sessions') as $suffix) {
				$tables[] = PublicShellTables::table($suffix);
			}

			$tables[] = SystemTables::table('users');

			$missing = array();
			foreach (array_unique($tables) as $table) {
				if (!self::tableExists($table)) {
					$missing[] = $table;
				}
			}

			self::add($checks, 'Target-таблицы native', $missing ? 'error' : 'ok', $missing ? 'отсутствуют: ' . implode(', ', $missing) : 'все критичные владельцы данных доступны', 'data');
		}

		protected static function checkHosting(array &$checks)
		{
			self::add($checks, 'Маршрутизация панели', is_file(ADMINX_PATH . '/.htaccess') ? 'ok' : 'error', is_file(ADMINX_PATH . '/.htaccess') ? '.htaccess присутствует' : '.htaccess отсутствует', 'environment');
			$layout = @file_get_contents(ADMINX_PATH . '/view/main.twig');
			$cdn = $layout !== false && preg_match('#https?://#', $layout);
			self::add($checks, 'UI-ассеты', $cdn ? 'warning' : 'ok', $cdn ? 'есть внешние CDN-зависимости' : 'локальные', 'environment');
		}

		protected static function checkStoredCodePrefixes(array &$checks)
		{
			$prefix = ContentTables::prefix();

			$targets = array(
				'templates' => array('template_text'),
				'rubrics' => array('rubric_template', 'rubric_header_template', 'rubric_footer_template', 'rubric_start_code', 'rubric_code_start', 'rubric_code_end', 'rubric_teaser_template'),
				'rubric_templates' => array('template'),
				'sysblocks' => array('sysblock_text'),
				'blocks' => array('block_text'),
				'request' => array('request_template_item', 'request_template_main'),
			);
			$count = 0;
			try {
				foreach ($targets as $suffix => $columns) {
					$table = $prefix . '_' . $suffix;
					if (!self::tableExists($table)) { continue; }
					foreach ($columns as $column) {
						$count += (int) DB::query('SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` LIKE %s', '%PREFIX%')->getValue();
					}
				}

				self::add($checks, 'Префиксы DB-шаблонов', 'ok', $count . ' вхождений нормализуются при рендере шаблонов', 'data');
			} catch (\Throwable $e) {
				self::add($checks, 'Префиксы DB-шаблонов', 'warning', 'аудит не выполнен: ' . $e->getMessage(), 'data');
			}
		}

		protected static function checkMedia(array &$checks)
		{
			$cache = BASEPATH . '/tmp/cache/adminx-media-integrity.json';
			$result = null;
			if (is_file($cache) && filemtime($cache) > time() - 3600) {
				$decoded = json_decode((string) file_get_contents($cache), true);
				if (is_array($decoded)) { $result = $decoded; }
			}

			if ($result === null) {
				$paths = array();
				$prefix = ContentTables::prefix();
				$sources = array(
					array($prefix . '_document_fields', 'field_value', 12000),
					array($prefix . '_document_fields_text', 'field_value', 5000),
					array($prefix . '_blocks', 'block_text', 1000),
					array($prefix . '_sysblocks', 'sysblock_text', 1000),
					array($prefix . '_templates', 'template_text', 100),
					array($prefix . '_request', 'request_template_item', 1000),
					array($prefix . '_request', 'request_template_main', 1000),
				);
				foreach ($sources as $source) {
					if (!self::tableExists($source[0])) { continue; }
					$rows = DB::query('SELECT `' . $source[1] . '` AS content FROM `' . $source[0] . '` WHERE `' . $source[1] . '` LIKE %s LIMIT ' . (int) $source[2], '%/uploads/%')->getAll();
					foreach ($rows ?: array() as $row) {
						if (!preg_match_all('#/?uploads/[^\s\"\'<>|\)]+#u', (string) $row['content'], $matches)) { continue; }
						foreach ($matches[0] as $path) {
							$path = '/' . ltrim(rawurldecode(strtok((string) $path, '?#')), '/');
							$paths[$path] = true;
						}
					}
				}

				$missing = 0; $recoverable = 0;
				foreach (array_keys($paths) as $path) {
					if (is_file(BASEPATH . $path)) { continue; }
					$original = preg_replace('#/th/([^/]+)-(?:r|c|f|t|s)\d+x\d+r?(\.[A-Za-z0-9]+)$#', '/$1$2', $path);
					if ($original !== $path && is_file(BASEPATH . $original)) { $recoverable++; continue; }
					$missing++;
				}

				$result = array('references' => count($paths), 'missing' => $missing, 'recoverable' => $recoverable);
				@file_put_contents($cache, json_encode($result));
			}

			$production = defined('ENV_CMS') && strtolower((string) ENV_CMS) === 'production';
			$state = $result['missing'] > 0 && $production ? 'warning' : 'ok';
			$detail = $result['references'] . ' ссылок, отсутствуют исходники: ' . $result['missing'] . ', превью создадутся: ' . $result['recoverable'];
			if (!$production && $result['missing'] > 0) { $detail .= ' · локальная медиатека помечена как неполная'; }
			self::add($checks, 'Медиа-ссылки', $state, $detail, 'filesystem');
		}

		protected static function databaseInfo()
		{
			$info = array(
				'connected' => false,
				'product' => 'База данных',
				'version' => 'Недоступно',
				'raw_version' => '',
				'comment' => '',
				'charset' => 'неизвестно',
				'collation' => 'неизвестно',
			);

			try {
				$row = DB::query(
					'SELECT VERSION() AS server_version, @@version_comment AS server_comment,'
						. ' @@character_set_database AS database_charset, @@collation_database AS database_collation'
				)->getAssoc();
				$rawVersion = isset($row['server_version']) ? trim((string) $row['server_version']) : '';
				$comment = isset($row['server_comment']) ? trim((string) $row['server_comment']) : '';
				$identity = strtolower($rawVersion . ' ' . $comment);
				$product = strpos($identity, 'mariadb') !== false
					? 'MariaDB'
					: (strpos($identity, 'percona') !== false ? 'Percona Server' : 'MySQL');
				$version = $rawVersion;
				if (preg_match('/^\d+(?:\.\d+){1,2}/', $rawVersion, $match)) {
					$version = $match[0];
				}

				$info = array(
					'connected' => $rawVersion !== '',
					'product' => $product,
					'version' => $version !== '' ? $version : 'Недоступно',
					'raw_version' => $rawVersion,
					'comment' => $comment,
					'charset' => isset($row['database_charset']) ? (string) $row['database_charset'] : 'неизвестно',
					'collation' => isset($row['database_collation']) ? (string) $row['database_collation'] : 'неизвестно',
				);
			} catch (\Throwable $e) {
				$info['comment'] = $e->getMessage();
			}

			return $info;
		}

		protected static function overview(array $summary, array $database, $ready)
		{
			$databaseDetail = array();
			if ($database['comment'] !== '') {
				$databaseDetail[] = $database['comment'];
			}

			if ($database['raw_version'] !== '' && $database['raw_version'] !== $database['version']) {
				$databaseDetail[] = $database['raw_version'];
			}

			$databaseDetail[] = $database['charset'] . ' / ' . $database['collation'];

			return array(
				array(
					'label' => 'База данных',
					'value' => $database['connected'] ? $database['product'] . ' ' . $database['version'] : 'Недоступно',
					'detail' => $database['connected']
						? implode(' · ', $databaseDetail)
						: 'Соединение не установлено',
					'icon' => 'ti-database',
					'state' => $database['connected'] ? 'ok' : 'error',
				),
				array(
					'label' => 'PHP',
					'value' => PHP_VERSION,
					'detail' => PHP_SAPI . ' · ' . PHP_OS,
					'icon' => 'ti-brand-php',
					'state' => PHP_VERSION_ID >= 70300 && PHP_VERSION_ID < 80000 ? 'ok' : 'warning',
				),
				array(
					'label' => 'Проверки',
					'value' => (string) array_sum($summary),
					'detail' => $summary['ok'] . ' успешно · ' . $summary['warning'] . ' внимания · ' . $summary['error'] . ' ошибок',
					'icon' => 'ti-list-check',
					'state' => $summary['error'] > 0 ? 'error' : ($summary['warning'] > 0 ? 'warning' : 'ok'),
				),
				array(
					'label' => 'Готовность',
					'value' => $ready ? 'Готово' : 'Нужны исправления',
					'detail' => $ready ? 'Критических ошибок не найдено' : 'Устраните красные проверки',
					'icon' => $ready ? 'ti-shield-check' : 'ti-shield-exclamation',
					'state' => $ready ? 'ok' : 'error',
				),
			);
		}

		protected static function groupChecks(array $checks)
		{
			$definitions = array(
				'environment' => array('title' => 'Окружение', 'description' => 'Версии runtime, расширения и конфигурация веб-сервера.', 'icon' => 'ti-server'),
				'filesystem' => array('title' => 'Файлы и медиа', 'description' => 'Доступность рабочих каталогов и целостность медиа-ссылок.', 'icon' => 'ti-folders'),
				'data' => array('title' => 'Структура данных', 'description' => 'Критичные таблицы и совместимость хранимых шаблонов.', 'icon' => 'ti-database-check'),
				'integrations' => array('title' => 'Почта и интеграции', 'description' => 'Настройки отправки и включённых прикладных модулей.', 'icon' => 'ti-plug-connected'),
				'runtime' => array('title' => 'Публичный runtime', 'description' => 'Готовность публичного ядра и подключённых native-модулей.', 'icon' => 'ti-world'),
			);
			$groups = array();
			foreach ($definitions as $code => $definition) {
				$definition['code'] = $code;
				$definition['checks'] = array();
				$groups[$code] = $definition;
			}

			foreach ($checks as $check) {
				$code = isset($check['group']) && isset($groups[$check['group']]) ? $check['group'] : 'environment';
				$groups[$code]['checks'][] = $check;
			}

			return array_values(array_filter($groups, function ($group) {
				return !empty($group['checks']);
			}));
		}

		protected static function decode(array $row, $key)
		{
			$value = isset($row[$key]) ? $row[$key] : '';
			if (is_array($value)) { return $value; }
			$json = json_decode((string) $value, true);
			if (is_array($json)) { return $json; }
			$legacy = @unserialize((string) $value, array('allowed_classes' => false));
			return is_array($legacy) ? $legacy : array();
		}

		protected static function tableExists($table) { return (bool) DB::query('SHOW TABLES LIKE %s', $table)->getValue(); }
		protected static function add(array &$checks, $label, $state, $detail, $group = 'environment')
		{
			$checks[] = array(
				'label' => $label,
				'state' => $state,
				'detail' => trim((string) $detail),
				'group' => $group,
			);
		}
	}
