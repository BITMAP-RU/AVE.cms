<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/NativeModuleAdapter.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Modules;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\ModuleExtensions;
	use App\Adminx\Support\InterfaceSettings;
	use App\Common\AdminLocation;
	use App\Common\ModuleManager;
	use App\Common\ModuleMigrator;
	use App\Common\ModuleSettings;

	/** Нативный lifecycle-адаптер manifest-файлов adminx-модулей. */
	class NativeModuleAdapter
	{
		public static function all()
		{
			$items = array();
			foreach (ModuleExtensions::catalog() as $extension) {
				if (empty($extension['module']['managed'])) {
					continue;
				}

				$items[] = self::normalize($extension);
			}

			usort($items, array(self::class, 'compareByName'));

			return $items;
		}

		public static function compareByName(array $left, array $right)
		{
			$name = strnatcasecmp((string) $left['name'], (string) $right['name']);
			return $name !== 0 ? $name : strnatcasecmp((string) $left['code'], (string) $right['code']);
		}

		public static function errors()
		{
			$errors = array();
			foreach (array_merge(ModuleManager::errors(), \App\Common\PackageModuleRuntime::errors()) as $message) {
				$errors[] = array('code' => 'manifest', 'message' => (string) $message);
			}

			return $errors;
		}

		public static function stats(array $items)
		{
			$stats = array('total' => count($items), 'active' => 0, 'inactive' => 0, 'available' => 0, 'updates' => 0, 'errors' => 0);
			foreach ($items as $item) {
				if (!empty($item['error']) || !empty($item['recoverable'])) { $stats['errors']++; }
				if (empty($item['installed'])) { $stats['available']++; }
				elseif (!empty($item['enabled'])) { $stats['active']++; }
				else { $stats['inactive']++; }
				if (!empty($item['needs_update'])) { $stats['updates']++; }
			}

			return $stats;
		}

		public static function handles($code)
		{
			foreach (ModuleExtensions::catalog() as $extension) {
				if ($extension['code'] === (string) $code && !empty($extension['module']['managed'])) {
					return true;
				}
			}

			return false;
		}

		public static function one($code)
		{
			foreach (ModuleExtensions::catalog() as $extension) {
				if ($extension['code'] === (string) $code && !empty($extension['module']['managed'])) {
					return self::normalize($extension);
				}
			}

			throw new \RuntimeException('Нативный модуль не найден');
		}

		public static function install($code) { ModuleManager::install($code); return self::one($code); }
		public static function update($code) { ModuleManager::update($code); return self::one($code); }
		public static function preflight($code, $operation) { return ModuleManager::preflight($code, $operation); }
		public static function reinstall($code, $token = '') { ModuleManager::reinstall($code, $token); return self::one($code); }
		public static function repair($code) { ModuleManager::repair($code); return self::one($code); }
		public static function uninstall($code, $token = '') { ModuleManager::uninstall($code, $token); return self::one($code); }
		public static function remove($code) { return ModuleManager::removePackageFiles($code); }
		public static function toggle($code, $enabled) { ModuleManager::toggle($code, $enabled); return self::one($code); }

		public static function savePresentation($code, $headerEnabled, $dashboardEnabled)
		{
			$item = self::one($code);
			if (empty($item['installed'])) {
				throw new \RuntimeException('Сначала установите модуль');
			}

			if (!empty($item['has_header'])) {
				ModuleSettings::set('adminx_header_enabled', (bool) $headerEnabled, $code, 'bool');
			}

			if (!empty($item['has_dashboard'])) {
				ModuleSettings::set('adminx_dashboard_enabled', (bool) $dashboardEnabled, $code, 'bool');
				InterfaceSettings::setModuleDashboardVisibility(
					$code,
					(bool) $dashboardEnabled,
					ModuleExtensions::dashboardDefinitions()
				);
			}

			return self::one($code);
		}

		protected static function normalize(array $extension)
		{
			$module = $extension['module'];
			$descriptor = ModuleManager::descriptor($extension['code']);
			$installed = !empty($module['installed']);
			$fileVersion = isset($module['version']) ? (string) $module['version'] : '';
			$dbVersion = isset($module['db_version']) ? (string) $module['db_version'] : '';
			$extensionConfig = isset($module['admin_extension']) && is_array($module['admin_extension'])
				? $module['admin_extension']
				: array();
			$location = self::location(isset($module['base_path']) ? $module['base_path'] : '');
			$scope = self::scope($location, $extension['code']);
			$hasHeader = !empty($extensionConfig['header']);
			$hasDashboard = !empty($extensionConfig['dashboard']);
			$migrationState = array('pending' => 0);
			if ($installed && is_array($descriptor) && !empty($descriptor['migrations'])) {
				$migrationState = ModuleMigrator::inspect(
					$extension['code'],
					$descriptor['migrations'],
					isset($descriptor['_base_path']) ? $descriptor['_base_path'] : ''
				);
			}

			$migrationPending = !empty($migrationState['pending']);
			// The lifecycle version records the package that completed installation.
			// A source version bump alone does not require a database operation.
			$needsUpdate = $installed && $migrationPending;
			return array(
				'id' => 0,
				'code' => $extension['code'],
				'folder' => $location['folder'],
				'name' => $extension['name'],
				'description' => $extension['description'],
				'author' => $extension['author'],
				'copyright' => '',
				'installed' => $installed,
				'enabled' => $installed && !empty($module['enabled']),
				'status' => isset($module['status']) ? (string) $module['status'] : 'available',
				'recoverable' => !empty($module['recoverable']),
				'operation' => isset($module['operation']) ? (string) $module['operation'] : '',
				'last_error' => isset($module['last_error']) ? (string) $module['last_error'] : '',
				'missing' => false,
				'error' => '',
				'file_version' => $fileVersion,
				'db_version' => $dbVersion,
				'needs_update' => $needsUpdate,
				'migration_pending' => $migrationPending,
				'template_id' => 0,
				'supports_template' => false,
				'admin_edit' => $extension['admin_url'] !== '',
				'admin_url' => $extension['admin_url'],
				'tag' => '',
				'function' => $extension['feature'],
				'has_sql' => !empty($module['migrations_count']),
				'has_admin' => $extension['admin_url'] !== '',
				'modern_available' => true,
				'managed' => !empty($module['managed']),
				'removable' => is_array($descriptor) && !empty($descriptor['package']['removable']),
				'toggleable' => $installed,
				'native_admin' => true,
				'icon' => $extension['icon'],
				'path' => $location['path'],
				'scope' => $scope,
				'has_header' => $hasHeader,
				'header_enabled' => $hasHeader && (bool) ModuleSettings::get('adminx_header_enabled', true, $extension['code']),
				'has_dashboard' => $hasDashboard,
				'dashboard_enabled' => $hasDashboard && (bool) ModuleSettings::get('adminx_dashboard_enabled', true, $extension['code']),
			);
		}

		protected static function location($basePath)
		{
			$basePath = rtrim(str_replace('\\', '/', (string) $basePath), '/');
			$packageAdmin = basename($basePath) === 'admin' ? dirname($basePath) : '';
			$packagesRoot = str_replace('\\', '/', BASEPATH . DS . 'modules');
			if ($packageAdmin !== '' && dirname($packageAdmin) === $packagesRoot) {
				$folder = basename($packageAdmin);
				return array('folder' => $folder, 'path' => 'modules/' . $folder, 'package' => true);
			}

			$folder = basename($basePath);
			return array('folder' => $folder, 'path' => AdminLocation::directory() . '/modules/' . $folder, 'package' => false);
		}

		protected static function scope(array $location, $code)
		{
			$root = rtrim(BASEPATH, '/\\') . DS . 'modules' . DS
				. (empty($location['package']) ? (string) $code : $location['folder']);
			$hasPublic = is_file($root . DS . 'app' . DS . 'module.php');
			$hasAdmin = empty($location['package']) || is_file($root . DS . 'admin' . DS . 'module.php');
			if ($hasPublic && $hasAdmin) { return 'mixed'; }
			return $hasPublic ? 'public' : 'admin';
		}
	}
