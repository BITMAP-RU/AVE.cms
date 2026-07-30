<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicRouteRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/**
	 * Реестр URL, которыми владеют публичные системные страницы и модули.
	 * Документы используют его как второй источник конфликтов после своих alias.
	 */
	class PublicRouteRegistry
	{
		const SETTINGS_KEY = 'public_url_registry';
		protected static $definitions;

		public static function conflict($alias, $exceptOwners = array())
		{
			$path = self::normalize($alias);
			if ($path === '') { return null; }

			$exceptOwners = self::owners($exceptOwners);

			foreach (self::definitions() as $item) {
				if (isset($exceptOwners[$item['owner']])) { continue; }
				if (self::matches($path, $item)) {
					return $item;
				}
			}

			return null;
		}

		public static function available($alias, $exceptOwners = array())
		{
			return self::conflict($alias, $exceptOwners) === null;
		}

		/** Проверка URL перед тем, как модуль заберёт его себе. */
		public static function claimConflict($alias, $exceptOwners = array(), $prefix = false)
		{
			$path = self::normalize($alias);
			if ($path === '') { return array('type' => 'invalid', 'label' => 'Некорректный URL'); }
			$exceptOwners = self::owners($exceptOwners);
			$routeConflict = self::conflict($path, array_keys($exceptOwners));
			if ($routeConflict) { return $routeConflict; }

			if ($prefix) {
				$needle = rtrim($path, '/') . '/';
				foreach (self::definitions() as $item) {
					if (isset($exceptOwners[$item['owner']])) { continue; }
					if (strpos(rtrim($item['pattern'], '/') . '/', $needle) === 0) { return $item; }
				}
			}

			try {
				$aliasValue = ltrim($path, '/');
				$documentSql = 'SELECT Id FROM `' . ContentTables::table('documents') . '` WHERE document_alias = %s';
				$historySql = 'SELECT id FROM `' . ContentTables::table('document_alias_history') . '` WHERE document_alias = %s';
				$args = array($aliasValue);
				if ($prefix) {
					$documentSql .= ' OR document_alias LIKE %s';
					$historySql .= ' OR document_alias LIKE %s';
					$args[] = $aliasValue . '/%';
				}

				$documentSql .= ' LIMIT 1';
				$historySql .= ' LIMIT 1';
				if ((int) call_user_func_array(array('DB', 'query'), array_merge(array($documentSql), $args))->getValue() > 0) {
					return array('type' => 'document', 'owner' => 'documents', 'label' => 'Документы');
				}

				if ((int) call_user_func_array(array('DB', 'query'), array_merge(array($historySql), $args))->getValue() > 0) {
					return array('type' => 'history', 'owner' => 'document-history', 'label' => 'Редиректы документов');
				}
			} catch (\Throwable $e) {
				error_log('Public URL claim check: ' . $e->getMessage());
			}

			return null;
		}

		/** Сохранить назначенные модулю URL. Вызывается из экрана его настроек. */
		public static function replaceOwner($owner, $label, array $routes, array $prefixes = array())
		{
			$owner = preg_replace('/[^a-z0-9_.-]+/i', '', trim((string) $owner));
			if ($owner === '') { throw new \InvalidArgumentException('Не указан владелец публичных URL'); }

			$stored = Settings::get(self::SETTINGS_KEY, array());
			if (!is_array($stored)) { $stored = array(); }
			$value = array(
				'label' => trim((string) $label) !== '' ? trim((string) $label) : $owner,
				'routes' => self::normalizeList($routes),
				'prefixes' => self::normalizeList($prefixes),
			);
			if (isset($stored[$owner]) && $stored[$owner] === $value) { return; }
			$stored[$owner] = $value;
			Settings::set(self::SETTINGS_KEY, $stored, 'json');
			self::$definitions = null;
		}

		public static function removeOwner($owner)
		{
			$stored = Settings::get(self::SETTINGS_KEY, array());
			if (!is_array($stored) || !isset($stored[$owner])) { return; }
			unset($stored[$owner]);
			Settings::set(self::SETTINGS_KEY, $stored, 'json');
			self::$definitions = null;
		}

		public static function definitions()
		{
			if (is_array(self::$definitions)) {
				return self::$definitions;
			}

			$items = self::coreDefinitions();
			$items = array_merge($items, self::authDefinitions(), self::basketDefinitions(), self::moduleDefinitions(), self::storedDefinitions());
			$unique = array();
			foreach ($items as $item) {
				$key = $item['type'] . ':' . $item['pattern'];
				if (!isset($unique[$key])) { $unique[$key] = $item; }
			}

			self::$definitions = array_values($unique);
			return self::$definitions;
		}

		protected static function coreDefinitions()
		{
			$items = array();
			$adminPath = '/' . trim(AdminLocation::directory(), '/');
			self::append($items, 'Панель управления', 'control-panel', array($adminPath), array($adminPath . '/'));
			self::append($items, 'API документов', 'document-api', array('/api/v1/documents/by-alias', '/api/v1/documents/{id}', '/api/v1/documents'), array('/api/'));
			self::append($items, 'Системные файлы', 'system', array('/inc/captcha.php', '/inc/thumb.php'), array('/inc/'));
			self::append($items, 'Карта сайта', 'sitemap', array('/sitemap.xml', '/sitemap-{part}.xml'));
			self::append($items, 'Товарные фиды', 'feeds', array('/feeds/{alias}'), array('/feeds/'));
			if (class_exists('App\\Frontend\\Feeds\\FeedPresets')) {
				foreach (array_keys(\App\Frontend\Feeds\FeedPresets::paths()) as $path) {
					self::append($items, 'Товарные фиды', 'feeds', array('/' . ltrim($path, '/')));
				}
			}

			return $items;
		}

		protected static function authDefinitions()
		{
			$items = array();
			try {
				$settings = PublicAuthSettings::all();
				$routes = array('/login','/logout','/register','/register/verify','/remember','/password/reset','/personal','/personal/overview','/personal/password','/auth/oauth/{provider}','/auth/oauth/{provider}/callback','/auth/phone/request','/auth/phone/verify');
				foreach (isset($settings['pages']) && is_array($settings['pages']) ? $settings['pages'] : array() as $page) {
					if (!empty($page['path'])) { $routes[] = $page['path']; }
				}

				self::append($items, 'Авторизация и профиль', 'auth', $routes);
			} catch (\Throwable $e) {
				self::append($items, 'Авторизация и профиль', 'auth', array('/login','/logout','/register','/register/verify','/remember','/password/reset','/personal','/personal/overview','/personal/password','/auth/oauth/{provider}','/auth/oauth/{provider}/callback','/auth/phone/request','/auth/phone/verify'));
			}

			return $items;
		}

		protected static function basketDefinitions()
		{
			$items = array();
			try {
				$module = ModuleManager::get('commerce');
				if (!$module || empty($module['enabled']) || !class_exists('App\\Frontend\\Basket\\PageSettings')) { return $items; }
				$pages = \App\Frontend\Basket\PageSettings::all();
				$routes = array();
				foreach ($pages as $key => $page) {
					if (empty($page['path'])) { continue; }
					$routes[] = $page['path'];
					if ($key === 'orders') {
						$routes[] = rtrim($page['path'], '/') . '/{id}';
						$routes[] = rtrim($page['path'], '/') . '/{id}/pay';
					}
				}

				self::append($items, 'Корзина: публичные страницы', 'basket-pages', $routes, array(rtrim($pages['orders']['path'], '/') . '/'));
			} catch (\Throwable $e) {
				error_log('Basket public URL registry: ' . $e->getMessage());
			}

			return $items;
		}

		protected static function moduleDefinitions()
		{
			$items = array();
			foreach (self::publicModuleEntries() as $entry) {
				try { $descriptor = CompiledModuleRegistry::descriptor($entry); } catch (\Throwable $e) { continue; }
				if (!is_array($descriptor) || empty($descriptor['code'])) { continue; }
				$module = ModuleManager::get((string) $descriptor['code']);
				if (!$module || empty($module['enabled'])) { continue; }
				$public = isset($descriptor['public']) && is_array($descriptor['public']) ? $descriptor['public'] : array();
				$routes = isset($public['reserved_routes']) && is_array($public['reserved_routes']) ? $public['reserved_routes'] : array();
				$prefixes = isset($public['reserved_prefixes']) && is_array($public['reserved_prefixes']) ? $public['reserved_prefixes'] : array();
				self::append($items, isset($descriptor['name']) ? $descriptor['name'] : $descriptor['code'], $descriptor['code'], $routes, $prefixes);
			}

			foreach (ModuleManager::all() as $module) {
				$descriptor = ModuleManager::descriptor($module['code']);
				if (!$descriptor || empty($module['enabled'])) { continue; }
				self::append(
					$items,
					isset($module['name']) ? $module['name'] : $module['code'],
					$module['code'],
					isset($descriptor['public_routes']) && is_array($descriptor['public_routes']) ? $descriptor['public_routes'] : array(),
					isset($descriptor['public_prefixes']) && is_array($descriptor['public_prefixes']) ? $descriptor['public_prefixes'] : array()
				);
			}

			return $items;
		}

		protected static function publicModuleEntries()
		{
			$compiled = CompiledModuleRegistry::load('public');
			if (is_array($compiled)) {
				return $compiled;
			}

			$root = BASEPATH . DS . 'modules';
			$registryEntries = array();
			$registryRoots = array($root);
			foreach (is_dir($root) ? ((array) scandir($root)) : array() as $code) {
				if ($code === '.' || $code === '..' || strpos($code, '.') === 0
					|| !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', (string) $code)) {
					continue;
				}

				$moduleRoot = $root . DS . $code;
				$appDir = $moduleRoot . DS . 'app';
				$manifest = $appDir . DS . 'module.php';
				if (!is_file($manifest)) {
					continue;
				}

				$registryRoots[] = $moduleRoot;
				$namespace = 'App\\Modules\\' . PackageModuleRuntime::classify($code);
				\App\Common\Loader\Load::regNamespace($namespace, $appDir);
				try { $descriptor = include $manifest; } catch (\Throwable $e) { continue; }
				if (!is_array($descriptor) || !isset($descriptor['code']) || (string) $descriptor['code'] !== (string) $code) {
					continue;
				}

				$registryEntries[] = CompiledModuleRegistry::entry(
					$code,
					$appDir,
					$manifest,
					array($namespace => $appDir),
					$descriptor
				);
			}

			CompiledModuleRegistry::store('public', $registryEntries, $registryRoots);
			return $registryEntries;
		}

		protected static function storedDefinitions()
		{
			$items = array();
			$stored = Settings::get(self::SETTINGS_KEY, array());
			foreach (is_array($stored) ? $stored : array() as $owner => $config) {
				if (!is_array($config)) { continue; }
				self::append(
					$items,
					isset($config['label']) ? $config['label'] : $owner,
					$owner,
					isset($config['routes']) && is_array($config['routes']) ? $config['routes'] : array(),
					isset($config['prefixes']) && is_array($config['prefixes']) ? $config['prefixes'] : array()
				);
			}

			return $items;
		}

		protected static function append(array &$items, $label, $owner, array $routes, array $prefixes = array())
		{
			foreach (self::normalizeList($routes) as $pattern) {
				$items[] = array('type' => 'route', 'pattern' => $pattern, 'owner' => (string) $owner, 'label' => (string) $label);
			}

			foreach (self::normalizeList($prefixes) as $pattern) {
				$items[] = array('type' => 'prefix', 'pattern' => rtrim($pattern, '/') . '/', 'owner' => (string) $owner, 'label' => (string) $label);
			}
		}

		protected static function matches($path, array $item)
		{
			if ($item['type'] === 'prefix') {
				$prefix = rtrim($item['pattern'], '/');
				return $path === $prefix || strpos($path . '/', $prefix . '/') === 0;
			}

			$quoted = preg_quote($item['pattern'], '#');
			$regex = preg_replace('/\\\\\{[a-zA-Z_][a-zA-Z0-9_]*\\\\\}/', '[^/]+', $quoted);
			return (bool) preg_match('#^' . $regex . '/?$#', $path);
		}

		protected static function normalizeList(array $items)
		{
			$out = array();
			foreach ($items as $item) {
				$path = self::normalize($item, true);
				if ($path !== '') { $out[] = $path; }
			}

			return array_values(array_unique($out));
		}

		protected static function normalize($path, $allowPattern = false)
		{
			$path = parse_url('/' . ltrim(trim((string) $path), '/'), PHP_URL_PATH);
			$path = is_string($path) ? preg_replace('#/+#', '/', $path) : '';
			if ($path === '' || $path === '/') { return $path; }
			if (!$allowPattern && strpos($path, '{') !== false) { return ''; }
			return rtrim($path, '/');
		}

		protected static function owners($owners)
		{
			if (!is_array($owners)) { $owners = array($owners); }
			$result = array();
			foreach ($owners as $owner) {
				$owner = trim((string) $owner);
				if ($owner !== '') { $result[$owner] = true; }
			}

			return $result;
		}
	}
