<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleManager.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	use App\Common\Loader\Load;
	use App\Content\Fields\FieldRegistry;
	use App\Content\Blocks\ContentBlockRegistry;
	use App\Content\Presentation\PresentationDataRegistry;
	use App\Content\Requests\RequestRendererRegistry;
	use App\Content\Rubrics\FieldSetRegistry;
	use App\Helpers\Hooks;
	use App\Helpers\Dir;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Загрузчик модулей.
	 *
	 * Модуль описывает себя декларативно в module.php и возвращает массив-дескриптор:
	 *   code, name, version, lifecycle, config, settings, migrations, files,
	 *   helpers, routes, permissions, navigation, hooks, view_globals, admin_extension.
	 * ModuleManager регистрирует всё это в общих реестрах ядра
	 * (Permission, Navigation, Hooks) и хранит глобальные переменные для шаблонов.
	 *
	 * Ядро не знает о конкретных модулях — знание живёт только в module.php.
	 */
	class ModuleManager
	{
		/** @var array code => ['code','name','version'] */
		protected static $modules = [];

		/** @var array code => полный module.php descriptor */
		protected static $descriptors = [];

		/** @var array key => value|callable */
		protected static $viewGlobals = [];

		/** @var string[] */
		protected static $publicRoutes = [];

		/** @var string[] */
		protected static $publicPrefixes = [];

		/** @var string[] */
		protected static $errors = [];

		/** Режим загрузки: 'admin' | 'public' | 'all'. */
		protected static $mode = 'admin';

		/** @var bool */
		protected static $lifecycleTableEnsured = false;

		/** @var array|null Состояния модулей, загруженные одним запросом в public runtime. */
		protected static $publicLifecycleStates;

		protected function __construct()
		{
			//
		}

		public static function mode()
		{
			return self::$mode;
		}

		/**
		 * Установить режим для последующей точечной загрузки через load().
		 * Нужен public runtime, где manifests лежат в app-папках legacy-модулей.
		 */
		public static function setMode($mode)
		{
			self::$mode = in_array($mode, array('admin', 'public', 'all', 'shared'), true) ? $mode : 'admin';
		}

		/** Загрузить модуль из файла-дескриптора (module.php). */
		public static function load($file)
		{
			if (!is_file($file)) {
				return null;
			}

			$descriptor = include $file;
			if (!is_array($descriptor)) {
				return null;
			}

			$descriptor['_base_path'] = dirname($file);
			self::register($descriptor);
			return $descriptor;
		}

		/**
		 * Автоматически загрузить все модули из директории.
		 *
		 * Ожидаемый формат:
		 *   system/App/Marketplace/module.php
		 *   system/App/SomeModule/module.php
		 *
		 * Для каждой директории с module.php регистрируется PSR-4 namespace:
		 *   App\Marketplace => system/App/Marketplace
		 *
		 * @param string $baseDir Директория с модулями
		 * @param string $namespaceRoot Корневой namespace
		 * @return array ['loaded' => [...], 'errors' => [...]]
		 */
		public static function loadAll($baseDir, $mode = 'admin', $namespaceRoot = 'App')
		{
			self::$mode = in_array($mode, ['admin', 'public', 'all', 'shared'], true) ? $mode : 'admin';

			$result = [
				'loaded' => [],
				'errors' => [],
			];

			$baseDir = rtrim((string) $baseDir, DS);
			if (!is_dir($baseDir)) {
				$result['errors'][] = $baseDir;
				self::$errors[] = $baseDir;
				return $result;
			}

			$compiledScope = self::compiledScope($baseDir, $namespaceRoot);
			$compiled = $compiledScope !== '' ? CompiledModuleRegistry::load($compiledScope, array($baseDir)) : null;
			if (is_array($compiled)) {
				foreach ($compiled as $entry) {
					$descriptor = is_array($entry) ? CompiledModuleRegistry::activate($entry) : null;
					if (!$descriptor) {
						$name = is_array($entry) && isset($entry['folder']) ? (string) $entry['folder'] : 'unknown';
						$result['errors'][] = $name;
						self::$errors[] = $name;
						CompiledModuleRegistry::invalidate($compiledScope);
						continue;
					}

					$result['loaded'][] = isset($descriptor['code']) ? (string) $descriptor['code'] : (string) $entry['folder'];
				}

				return $result;
			}

			$entries = scandir($baseDir);
			if (!$entries) {
				return $result;
			}

			$compiledEntries = array();
			foreach ($entries as $entry) {
				if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
					continue;
				}

				$dir = $baseDir . DS . $entry;
				if (!is_dir($dir)) {
					continue;
				}

				$moduleFile = $dir . DS . 'module.php';
				if (!is_file($moduleFile)) {
					continue;
				}

				Load::regNamespace(trim($namespaceRoot, '\\') . '\\' . $entry, $dir);

				$descriptor = self::load($moduleFile);
				if (!$descriptor) {
					$result['errors'][] = $entry;
					self::$errors[] = $entry;
					continue;
				}

				$result['loaded'][] = isset($descriptor['code']) ? (string) $descriptor['code'] : $entry;
				if ($compiledScope !== '') {
					$compiledEntries[] = CompiledModuleRegistry::entry(
						$entry,
						$dir,
						$moduleFile,
						array(trim($namespaceRoot, '\\') . '\\' . $entry => $dir),
						$descriptor
					);
				}
			}

			if ($compiledScope !== '') {
				CompiledModuleRegistry::store($compiledScope, $compiledEntries, array($baseDir));
			}

			return $result;
		}

		protected static function compiledScope($baseDir, $namespaceRoot)
		{
			$expected = AdminLocation::path('modules');
			$actual = rtrim(str_replace('\\', '/', (string) $baseDir), '/');
			return $actual === $expected && trim((string) $namespaceRoot, '\\') === 'App\\Adminx'
				? 'adminx'
				: '';
		}

		/** Зарегистрировать модуль из массива-дескриптора. */
		public static function register(array $descriptor)
		{
			if (empty($descriptor['code'])) {
				return;
			}

			$code = (string) $descriptor['code'];
			$basePath = isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '';
			self::registerNamespaces($basePath, isset($descriptor['namespaces']) ? $descriptor['namespaces'] : null);
			$lifecycle = self::normalizeLifecycle(isset($descriptor['lifecycle']) ? $descriptor['lifecycle'] : null);
			$state = self::resolveLifecycleState($code, $descriptor, $lifecycle);
			self::$descriptors[$code] = $descriptor;
			self::$modules[$code] = [
				'code' => $code,
				'name' => isset($descriptor['name']) ? (string) $descriptor['name'] : $code,
				'version' => isset($descriptor['version']) ? (string) $descriptor['version'] : '',
				'base_path' => $basePath,
				'description' => isset($descriptor['description']) ? (string) $descriptor['description'] : '',
				'author' => isset($descriptor['author']) ? (string) $descriptor['author'] : '',
				'lifecycle' => $lifecycle,
				'managed' => !empty($lifecycle['managed']),
				'installed' => $state['installed'],
				'enabled' => $state['enabled'],
				'status' => $state['status'],
				'db_version' => $state['version'],
				'recoverable' => !empty($state['recoverable']),
				'operation' => isset($state['operation']) ? $state['operation'] : '',
				'previous_status' => isset($state['previous_status']) ? $state['previous_status'] : '',
				'last_error' => isset($state['last_error']) ? $state['last_error'] : '',
				'admin_extension' => isset($descriptor['admin_extension']) && is_array($descriptor['admin_extension']) ? $descriptor['admin_extension'] : array(),
				'dependencies' => self::normalizeDependencies(isset($descriptor['requires']) ? $descriptor['requires'] : null),
				'recommended_dependencies' => self::normalizeDependencies(isset($descriptor['recommends']) ? $descriptor['recommends'] : null),
				'permissions_count' => self::countPermissions(isset($descriptor['permissions']) ? $descriptor['permissions'] : null),
				'navigation_count' => self::countItems(isset($descriptor['navigation']) ? $descriptor['navigation'] : null),
				'hooks_count' => self::countItems(isset($descriptor['hooks']) ? $descriptor['hooks'] : null),
				'field_types_count' => self::countItems(isset($descriptor['field_types']) ? $descriptor['field_types'] : null),
				'field_sets_count' => self::countItems(isset($descriptor['field_sets']) ? $descriptor['field_sets'] : null),
				'request_renderers_count' => self::countItems(isset($descriptor['request_renderers']) ? $descriptor['request_renderers'] : null),
				'content_blocks_count' => self::countItems(isset($descriptor['content_blocks']) ? $descriptor['content_blocks'] : null),
				'presentation_data_count' => self::countItems(isset($descriptor['presentation_data']) ? $descriptor['presentation_data'] : null),
				'config_count' => self::countConfig(isset($descriptor['config']) ? $descriptor['config'] : null),
				'settings_count' => self::countItems(isset($descriptor['settings']) ? $descriptor['settings'] : null),
				'migrations_count' => self::countFiles(isset($descriptor['migrations']) ? $descriptor['migrations'] : null),
				'helpers_count' => self::countFiles(isset($descriptor['helpers']) ? $descriptor['helpers'] : null),
				'files_count' => self::countFiles(isset($descriptor['files']) ? $descriptor['files'] : null),
				'routes_count' => self::countFiles(isset($descriptor['routes']) ? $descriptor['routes'] : null),
			];

			$mode = self::$mode;
			$admin = (isset($descriptor['admin']) && is_array($descriptor['admin'])) ? $descriptor['admin'] : array();
			$public = (isset($descriptor['public']) && is_array($descriptor['public'])) ? $descriptor['public'] : array();

			//-- Управляемый модуль без записи lifecycle только обнаруживается.
			//-- Его код, миграции и UI подключатся после явной установки.
			if (!empty($lifecycle['managed']) && !$state['installed']) {
				Hooks::action('module.registered', $code);
				return;
			}

			//-- Shared (в любом режиме): config, files, helpers, services,
			//-- view-globals, shared-хуки, публичный доступ к роутам (login-bypass).
			self::registerConfig($basePath, isset($descriptor['config']) ? $descriptor['config'] : null);
			self::registerSettings($code, isset($descriptor['settings']) ? $descriptor['settings'] : null);
			self::registerFiles($basePath, isset($descriptor['files']) ? $descriptor['files'] : null);
			self::registerFiles($basePath, isset($descriptor['helpers']) ? $descriptor['helpers'] : null);
			self::registerFiles($basePath, isset($descriptor['services']) ? $descriptor['services'] : null);
			self::registerViewGlobals(isset($descriptor['view_globals']) ? $descriptor['view_globals'] : null);
			HookCatalog::registerMany(isset($descriptor['hook_definitions']) && is_array($descriptor['hook_definitions']) ? $descriptor['hook_definitions'] : array(), 'module:' . $code);
			self::registerFieldTypes($code, isset($descriptor['field_types']) ? $descriptor['field_types'] : null, !empty($state['enabled']));
			self::registerFieldSets($code, isset($descriptor['field_sets']) ? $descriptor['field_sets'] : null, !empty($state['enabled']));
			self::registerRequestRenderers($code, isset($descriptor['request_renderers']) ? $descriptor['request_renderers'] : null, !empty($state['enabled']));
			self::registerContentBlocks($code, isset($descriptor['content_blocks']) ? $descriptor['content_blocks'] : null, !empty($state['enabled']));
			self::registerPresentationData($code, isset($descriptor['presentation_data']) ? $descriptor['presentation_data'] : null, !empty($state['enabled']));
			self::registerHooks(isset($descriptor['hooks']) ? $descriptor['hooks'] : null);
			self::registerPublicAccess($descriptor);

			//-- Реестр модулей (§16.10): disabled-модуль грузит shared (данные/схема),
			//-- но НЕ admin/public (routes/menu/hooks). Core не выключается.
			$disabled = !empty($lifecycle['managed']) ? !$state['enabled'] : self::isDisabled($code);

			//-- Admin runtime: admin-секция или legacy top-level (обратная совместимость).
			if (!$disabled && ($mode === 'admin' || $mode === 'all')) {
				self::registerFiles($basePath, isset($admin['services']) ? $admin['services'] : null);
				self::registerPermissions($code, isset($admin['permissions']) ? $admin['permissions'] : (isset($descriptor['permissions']) ? $descriptor['permissions'] : null));
				self::registerNavigation(isset($admin['navigation']) ? $admin['navigation'] : (isset($descriptor['navigation']) ? $descriptor['navigation'] : null));
				self::registerHooks(isset($admin['hooks']) ? $admin['hooks'] : null);
				self::registerAdminAssets(isset($admin['assets']) ? $admin['assets'] : (isset($descriptor['assets']) ? $descriptor['assets'] : null));
				self::registerRoutes(
					$basePath,
					isset($admin['routes']) ? $admin['routes'] : (isset($descriptor['routes']) ? $descriptor['routes'] : null),
					self::adminRouteOptions($descriptor, $admin)
				);
			}

			//-- Public runtime: только public-секция (не зависит от админки).
			if (!$disabled && ($mode === 'public' || $mode === 'all')) {
				self::registerFiles($basePath, isset($public['services']) ? $public['services'] : null);
				self::registerRoutes($basePath, isset($public['routes']) ? $public['routes'] : null);
				self::registerHooks(isset($public['hooks']) ? $public['hooks'] : null);
			}

			Hooks::action('module.registered', $code);
		}

		/** Зарегистрированные модули (метаданные). */
		public static function all()
		{
			return array_values(self::$modules);
		}

		/** Полный descriptor зарегистрированного модуля. */
		public static function descriptor($code)
		{
			return isset(self::$descriptors[$code]) ? self::$descriptors[$code] : null;
		}

		/** Метаданные одного зарегистрированного модуля. */
		public static function get($code)
		{
			return self::moduleMeta((string) $code);
		}

		public static function isManaged($code)
		{
			$module = self::moduleMeta($code);
			return $module && !empty($module['managed']);
		}

		/** Prepare a destructive lifecycle action and issue a server-side confirmation token. */
		public static function preflight($code, $operation)
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (empty($meta['installed'])) {
				throw new \RuntimeException('Модуль не установлен');
			}

			$operation = self::normalizePreflightOperation($operation);
			$preview = self::lifecyclePreview($descriptor, $operation);
			if ($preview === null) {
				return array('required' => false, 'operation' => $operation, 'preview' => array());
			}

			$token = ModuleLifecyclePreflight::issue($code, $operation, Auth::id(), $preview);
			return array(
				'required' => true,
				'operation' => $operation,
				'token' => $token,
				'expires_in' => ModuleLifecyclePreflight::TTL,
				'preview' => $preview,
			);
		}

		/** Установить обнаруженный управляемый модуль. */
		public static function install($code)
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (isset($meta['status']) && $meta['status'] === 'dirty') {
				throw new \RuntimeException('Предыдущая операция не завершена. Используйте восстановление модуля');
			}

			if (!empty($meta['installed'])) {
				throw new \RuntimeException('Модуль уже установлен');
			}

			self::assertCoreSchemaReady();
			self::assertDependencies($descriptor);
			self::runLifecycleChange($descriptor, 'install', 'available');
			self::writeLifecycleEvent($code, 'installed', 'Модуль установлен');
			Hooks::action('module.installed', new LifecycleEvent('module', 'installed', $code, array(
				'module' => self::refreshLifecycleMeta($code),
			), null, array(), 'module_manager'));
			return self::refreshLifecycleMeta($code);
		}

		/** Применить новые миграции и обновить версию файлов. */
		public static function update($code)
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (empty($meta['installed'])) {
				throw new \RuntimeException('Модуль не установлен');
			}

			$previousStatus = !empty($meta['enabled']) ? 'enabled' : 'disabled';
			self::runLifecycleChange($descriptor, 'update', $previousStatus);
			self::writeLifecycleEvent($code, 'updated', 'Модуль обновлен');
			Hooks::action('module.updated', new LifecycleEvent('module', 'updated', $code, array(
				'module' => self::refreshLifecycleMeta($code),
			), null, array(), 'module_manager'));
			return self::refreshLifecycleMeta($code);
		}

		/** Повторить оборванную установку или обновление по журналу состояния. */
		public static function repair($code)
		{
			$descriptor = self::managedDescriptor($code);
			$state = self::lifecycleState($code, true);
			if (!$state || !isset($state['status']) || (string) $state['status'] !== 'dirty') {
				throw new \RuntimeException('Модуль не требует восстановления');
			}

			$operation = isset($state['operation']) ? (string) $state['operation'] : '';
			if (!in_array($operation, array('install', 'update', 'uninstall'), true)) {
				throw new \RuntimeException('Неизвестна оборванная операция модуля');
			}

			$previousStatus = isset($state['previous_status']) ? (string) $state['previous_status'] : 'available';
			if (!in_array($previousStatus, array('available', 'enabled', 'disabled'), true)) {
				$previousStatus = $operation === 'install' ? 'available' : 'disabled';
			}

			if ($operation === 'uninstall') {
				self::runUninstallChange($descriptor, $previousStatus);
			} else {
				self::assertDependencies($descriptor);
				self::runLifecycleChange($descriptor, $operation, $previousStatus);
			}

			self::writeLifecycleEvent($code, 'repaired', 'Операция модуля восстановлена');
			Hooks::action('module.repaired', new LifecycleEvent('module', 'repaired', $code, array(
				'module' => self::refreshLifecycleMeta($code),
				'operation' => $operation,
			), null, array(), 'module_manager'));
			return self::refreshLifecycleMeta($code);
		}

		/**
		 * Применить миграции встроенных разделов явной web-операцией.
		 * Управляемые пакеты обновляются только через install()/update().
		 */
		public static function updateCoreSchema()
		{
			$results = array();
			foreach (self::$descriptors as $code => $descriptor) {
				$lifecycle = self::normalizeLifecycle(isset($descriptor['lifecycle']) ? $descriptor['lifecycle'] : null);
				$migrations = isset($descriptor['migrations']) ? $descriptor['migrations'] : null;
				if (!empty($lifecycle['managed']) || empty($migrations)) {
					continue;
				}

				$basePath = isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '';
				$results = array_merge($results, ModuleMigrator::apply($code, $migrations, $basePath));
			}

			CompiledModuleRegistry::invalidate('adminx');

			return $results;
		}

		/** Состояние миграций встроенных разделов без изменения схемы. */
		public static function coreSchemaStatus()
		{
			$status = array('total' => 0, 'pending' => 0, 'changed' => 0);
			foreach (self::$descriptors as $code => $descriptor) {
				$lifecycle = self::normalizeLifecycle(isset($descriptor['lifecycle']) ? $descriptor['lifecycle'] : null);
				$migrations = isset($descriptor['migrations']) ? $descriptor['migrations'] : null;
				if (!empty($lifecycle['managed']) || empty($migrations)) {
					continue;
				}

				$basePath = isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '';
				$item = ModuleMigrator::inspect($code, $migrations, $basePath);
				$status['total'] += (int) $item['total'];
				$status['pending'] += (int) $item['pending'];
				$status['changed'] += (int) $item['changed'];
			}

			return $status;
		}

		public static function toggle($code, $enabled)
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (empty($meta['installed'])) {
				throw new \RuntimeException('Модуль не установлен');
			}

			if ($enabled) {
				self::assertDependencies($descriptor);
			} else {
				self::assertNoEnabledDependents($code);
			}

			$status = $enabled ? 'enabled' : 'disabled';
			self::writeLifecycleState($descriptor, $status);
			self::writeLifecycleEvent($code, $status, $enabled ? 'Модуль включен' : 'Модуль отключен');
			self::$disabledCodes = null;
			Hooks::action($enabled ? 'module.enabled' : 'module.disabled', new LifecycleEvent('module', $status, $code, array(
				'module' => self::refreshLifecycleMeta($code),
			), null, array(), 'module_manager'));
			return self::refreshLifecycleMeta($code);
		}

		/** Деинсталлировать модуль вместе с принадлежащими ему данными. */
		public static function uninstall($code, $preflightToken = '')
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (empty($meta['installed'])) {
				throw new \RuntimeException('Модуль уже не установлен');
			}

			self::assertNoEnabledDependents($code);
			self::assertNoUsedFieldTypes($code);
			self::confirmLifecyclePreflight($descriptor, 'uninstall', $preflightToken);

			return self::performUninstall($descriptor, $meta);
		}

		protected static function performUninstall(array $descriptor, array $meta)
		{
			$code = (string) $descriptor['code'];
			Hooks::action('module.uninstalling', new LifecycleEvent('module', 'uninstalling', $code, array(
				'module' => $meta,
			), null, array(), 'module_manager'));
			self::runUninstallChange($descriptor, !empty($meta['enabled']) ? 'enabled' : 'disabled');
			self::writeLifecycleEvent($code, 'uninstalled', 'Модуль деинсталлирован');
			self::$disabledCodes = null;
			Hooks::action('module.uninstalled', new LifecycleEvent('module', 'uninstalled', $code, array(
				'module' => self::refreshLifecycleMeta($code),
			), null, array(), 'module_manager'));
			return self::refreshLifecycleMeta($code);
		}

		public static function reinstall($code, $preflightToken = '')
		{
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (empty($meta['installed'])) {
				throw new \RuntimeException('Модуль не установлен');
			}

			self::assertNoEnabledDependents($code);
			self::assertNoUsedFieldTypes($code);
			self::confirmLifecyclePreflight($descriptor, 'reinstall', $preflightToken);
			self::performUninstall($descriptor, $meta);
			return self::install($code);
		}

		/** Physically remove an already uninstalled package from an allowed module root. */
		public static function removePackageFiles($code)
		{
			$code = (string) $code;
			$descriptor = self::managedDescriptor($code);
			$meta = self::moduleMeta($code);
			if (!empty($meta['installed'])) {
				throw new \RuntimeException('Сначала деинсталлируйте модуль');
			}

			if (empty($descriptor['package']['removable'])) {
				throw new \RuntimeException('Физическое удаление файлов для этого модуля запрещено');
			}

			$basePath = realpath(isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '');
			$packagesRoot = realpath(BASEPATH . DS . 'modules');
			$adminModulesRoot = realpath(defined('ADMINX_PATH') ? ADMINX_PATH . DS . 'modules' : BASEPATH . DS . 'adminx' . DS . 'modules');
			$packageRoot = false;
			$registryScopes = array();

			if ($basePath && $packagesRoot && basename($basePath) === 'admin') {
				$candidate = realpath(dirname($basePath));
				if ($candidate && dirname($candidate) === $packagesRoot && basename($candidate) === $code) {
					$packageRoot = $candidate;
					$registryScopes = array('package_admin', 'public');
				}
			}

			if (!$packageRoot && $basePath && $adminModulesRoot && dirname($basePath) === $adminModulesRoot) {
				$packageRoot = $basePath;
				$registryScopes = array('adminx');
			}

			if (!$packageRoot) {
				throw new \RuntimeException('Путь пакета не прошёл проверку безопасности');
			}

			Dir::delete($packageRoot);
			if (is_dir($packageRoot)) {
				throw new \RuntimeException('Не удалось удалить каталог пакета');
			}

			ModuleSecretStore::remove($code);
			ModuleSettings::purge($code);
			ModuleMigrator::forget($code);
			\DB::Delete(self::lifecycleTable('module_events'), 'module_code = %s', $code);
			\DB::Delete(self::lifecycleTable('modules'), 'code = %s', $code);
			unset(self::$modules[$code], self::$descriptors[$code]);
			foreach ($registryScopes as $scope) {
				CompiledModuleRegistry::invalidate($scope);
			}

			return array('code' => $code, 'path' => $packageRoot);
		}

		/** @var string[]|null Кеш кодов выключенных модулей (лениво из БД). */
		protected static $disabledCodes = null;

		/** Выключен ли модуль (по реестру modules; до миграции реестра — все включены). */
		protected static function isDisabled($code)
		{
			if (self::$disabledCodes === null) {
				self::$disabledCodes = [];
				if (self::$mode === 'public') {
					self::lifecycleState('__module_state_warmup__', false);
					foreach (self::$publicLifecycleStates as $moduleCode => $row) {
						$status = isset($row['status']) ? (string) $row['status'] : '';
						if (in_array($status, array('disabled', 'available'), true)) {
							self::$disabledCodes[] = (string) $moduleCode;
						}
					}

					return in_array((string) $code, self::$disabledCodes, true);
				}

				try {
					$table = self::lifecycleTable('modules');
					if (self::tableExists($table)) {
						$res = \DB::query('SELECT code FROM ' . $table . " WHERE status IN ('disabled', 'available')");
						while ($o = $res->getObject()) {
							self::$disabledCodes[] = (string) $o->code;
						}
					}
				} catch (\Throwable $e) {
					self::$disabledCodes = [];
				}
			}

			return in_array((string) $code, self::$disabledCodes, true);
		}

		protected static function tableExists($table)
		{
			try {
				$res = \DB::query('SHOW TABLES LIKE %s', $table);
				return $res && $res->getValue();
			} catch (\Throwable $e) {
				return false;
			}
		}

		/** Зарегистрировать settings schema модуля в общем SettingsRegistry. */
		protected static function registerSettings($moduleCode, $settings)
		{
			if (!is_array($settings) || empty($settings)) {
				return;
			}

			SettingsRegistry::addMany($settings, $moduleCode);
		}

		/** Все глобальные переменные шаблонов (callable уже вычислены). */
		public static function viewGlobals()
		{
			$out = array(
				'admin_assets' => array(
					'styles' => AdminAssets::styles(),
					'scripts' => AdminAssets::scripts(),
				),
			);
			foreach (self::$viewGlobals as $key => $value) {
				$out[$key] = is_callable($value) ? call_user_func($value) : $value;
			}

			return $out;
		}

		/** Одна глобальная переменная шаблона. */
		public static function viewGlobal($key, $default = null)
		{
			if (!array_key_exists($key, self::$viewGlobals)) {
				return $default;
			}

			$value = self::$viewGlobals[$key];
			return is_callable($value) ? call_user_func($value) : $value;
		}

		public static function publicRoutes()
		{
			return array_values(array_unique(self::$publicRoutes));
		}

		public static function publicPrefixes()
		{
			return array_values(array_unique(self::$publicPrefixes));
		}

		public static function errors()
		{
			return self::$errors;
		}

		public static function summary()
		{
			return [
				'modules' => self::all(),
				'errors' => self::errors(),
				'public_routes' => self::publicRoutes(),
				'public_prefixes' => self::publicPrefixes(),
			];
		}

		protected static function registerPermissions($code, $permissions)
		{
			if (empty($permissions) || !is_array($permissions) || empty($permissions['items'])) {
				return;
			}

			Permission::add(
				isset($permissions['key']) ? $permissions['key'] : $code,
				(array) $permissions['items'],
				isset($permissions['icon']) ? $permissions['icon'] : '',
				isset($permissions['priority']) ? (int) $permissions['priority'] : 10
			);
		}

		protected static function countPermissions($permissions)
		{
			if (empty($permissions) || !is_array($permissions) || empty($permissions['items']) || !is_array($permissions['items'])) {
				return 0;
			}

			return count($permissions['items']);
		}

		protected static function countItems($items)
		{
			return is_array($items) ? count($items) : 0;
		}

		protected static function countConfig($config)
		{
			if (empty($config)) {
				return 0;
			}

			if (isset($config['file']) || isset($config['class'])) {
				return 1;
			}

			return is_array($config) ? count($config) : 0;
		}

		protected static function countFiles($files)
		{
			if (empty($files)) {
				return 0;
			}

			return is_array($files) ? count($files) : 1;
		}

		protected static function registerConfig($basePath, $configs)
		{
			if (empty($configs)) {
				return;
			}

			if (isset($configs['file']) || isset($configs['class'])) {
				$configs = [$configs];
			}

			if (!is_array($configs)) {
				return;
			}

			foreach ($configs as $config) {
				if (!is_array($config) || empty($config['file']) || empty($config['class'])) {
					continue;
				}

				$path = self::resolveModulePath($basePath, (string) $config['file']);
				$class = (string) $config['class'];

				if (!is_file($path) || !class_exists($class) || !method_exists($class, 'load')) {
					continue;
				}

				$data = include $path;
				$class::load(is_array($data) ? $data : []);
			}
		}

		protected static function registerAdminAssets($assets)
		{
			if (empty($assets) || !is_array($assets)) {
				return;
			}

			foreach (array('styles' => 'addStyle', 'scripts' => 'addScript') as $key => $method) {
				if (empty($assets[$key]) || !is_array($assets[$key])) {
					continue;
				}

				foreach ($assets[$key] as $asset) {
					if (is_string($asset)) {
						call_user_func(array(AdminAssets::class, $method), $asset, 100);
						continue;
					}

					if (!is_array($asset) || empty($asset['url'])) {
						continue;
					}

					call_user_func(array(AdminAssets::class, $method), $asset['url'], isset($asset['priority']) ? (int) $asset['priority'] : 100);
				}
			}
		}

		protected static function registerMigrations($code, $basePath, $migrations, array $context = array())
		{
			if (empty($migrations)) {
				return;
			}

			ModuleMigrator::apply($code, $migrations, $basePath, $context);
		}

		protected static function registerFiles($basePath, $files)
		{
			if (empty($files)) {
				return;
			}

			if (is_string($files)) {
				$files = [$files];
			}

			if (!is_array($files)) {
				return;
			}

			foreach ($files as $file) {
				$file = (string) $file;
				if ($file === '') {
					continue;
				}

				$path = self::resolveModulePath($basePath, $file);
				if (is_file($path)) {
					require_once $path;
				}
			}
		}

		/**
		 * Register route files or compact descriptor definitions.
		 *
		 * A definition uses array(method, pattern, handler). Keeping route files
		 * supported preserves compatibility with modules that build routes
		 * dynamically.
		 */
		protected static function registerRoutes($basePath, $routes, array $defaultOptions = array())
		{
			if (empty($routes)) {
				return;
			}

			if (is_string($routes)) {
				self::registerFiles($basePath, $routes);
				return;
			}

			if (!is_array($routes)) {
				return;
			}

			foreach ($routes as $route) {
				if (is_string($route)) {
					self::registerFiles($basePath, $route);
					continue;
				}

				if (!is_array($route)) {
					continue;
				}

				$method = isset($route['method']) ? $route['method'] : (isset($route[0]) ? $route[0] : '');
				$pattern = isset($route['pattern']) ? $route['pattern'] : (isset($route[1]) ? $route[1] : '');
				$handler = isset($route['handler']) ? $route['handler'] : (isset($route[2]) ? $route[2] : null);
				$options = isset($route['options']) && is_array($route['options'])
					? $route['options']
					: (isset($route[3]) && is_array($route[3]) ? $route[3] : array());
				$options = array_merge($defaultOptions, $options);
				$method = strtolower(trim((string) $method));

				if ($pattern === '' || $handler === null
					|| !in_array($method, array('get', 'post', 'put', 'patch', 'delete', 'any'), true)) {
					continue;
				}

				call_user_func(array(Router::class, $method), (string) $pattern, $handler, $options);
			}
		}

		protected static function adminRouteOptions(array $descriptor, array $admin)
		{
			$permission = self::routePermission(isset($admin['navigation']) ? $admin['navigation'] : null);
			if ($permission === '') {
				$permission = self::routePermission(isset($descriptor['navigation']) ? $descriptor['navigation'] : null);
			}

			if ($permission === '' && !empty($descriptor['admin_extension']['menu'])) {
				$permission = self::routePermission($descriptor['admin_extension']['menu']);
			}

			$permissions = isset($admin['permissions']) ? $admin['permissions'] : (isset($descriptor['permissions']) ? $descriptor['permissions'] : null);
			if ($permission === '' && is_array($permissions)) {
				$items = isset($permissions['items']) && is_array($permissions['items']) ? $permissions['items'] : $permissions;
				foreach ($items as $item) {
					$code = is_array($item) && isset($item['code']) ? trim((string) $item['code']) : '';
					if (strpos($code, 'view_') === 0) {
						$permission = $code;
						break;
					}
				}
			}

			$options = array('module' => isset($descriptor['code']) ? (string) $descriptor['code'] : '');
			if ($permission !== '') {
				$options['permission'] = $permission;
			}

			return $options;
		}

		protected static function routePermission($items)
		{
			foreach (is_array($items) ? $items : array() as $item) {
				if (!is_array($item)) {
					continue;
				}

				$permission = isset($item['permission']) ? trim((string) $item['permission']) : '';
				if ($permission !== '') {
					return $permission;
				}

				$permission = self::routePermission(isset($item['children']) ? $item['children'] : array());
				if ($permission !== '') {
					return $permission;
				}
			}

			return '';
		}

		protected static function registerNamespaces($basePath, $namespaces)
		{
			if (!is_array($namespaces)) { return; }
			foreach ($namespaces as $namespace => $path) {
				$namespace = trim((string) $namespace, '\\');
				$path = self::resolveModulePath($basePath, (string) $path);
				if ($namespace !== '' && is_dir($path)) {
					Load::regNamespace($namespace, $path);
				}
			}
		}

		protected static function resolveModulePath($basePath, $file)
		{
			if ($file !== '' && $file[0] === '/') {
				return $file;
			}

			return rtrim((string) $basePath, DS) . DS . ltrim((string) $file, DS);
		}

		protected static function registerNavigation($navigation)
		{
			if (!empty($navigation) && is_array($navigation)) {
				Navigation::addMany($navigation);
			}
		}

		protected static function registerHooks($hooks)
		{
			if (empty($hooks) || !is_array($hooks)) {
				return;
			}

			foreach ($hooks as $hook) {
				if (!is_array($hook) || empty($hook['name']) || !isset($hook['handler'])) {
					continue;
				}

				Hooks::add(
					$hook['name'],
					$hook['handler'],
					isset($hook['priority']) ? (int) $hook['priority'] : 10
				);
			}
		}

		protected static function registerFieldTypes($moduleCode, $types, $creatable)
		{
			if (!is_array($types)) {
				return;
			}

			foreach ($types as $definition) {
				$class = is_string($definition)
					? $definition
					: (is_array($definition) && isset($definition['class']) ? $definition['class'] : '');
				if ($class === '') {
					continue;
				}

				$canCreate = is_array($definition) && array_key_exists('creatable', $definition)
					? (bool) $definition['creatable'] && (bool) $creatable
					: (bool) $creatable;
				try {
					FieldRegistry::registerClassName($class, 'module:' . (string) $moduleCode, $canCreate);
				} catch (\Throwable $e) {
					self::$errors[] = 'Field type ' . $class . ': ' . $e->getMessage();
				}
			}
		}

		protected static function registerFieldSets($moduleCode, $sets, $available)
		{
			if (!is_array($sets)) { return; }
			try {
				FieldSetRegistry::registerMany('module:' . (string) $moduleCode, $sets, (bool) $available);
			} catch (\Throwable $e) {
				self::$errors[] = 'Field sets ' . (string) $moduleCode . ': ' . $e->getMessage();
			}
		}

		protected static function registerRequestRenderers($moduleCode, $renderers, $available)
		{
			if (!is_array($renderers)) { return; }
			try {
				RequestRendererRegistry::registerMany('module:' . (string) $moduleCode, $renderers, (bool) $available);
			} catch (\Throwable $e) {
				self::$errors[] = 'Request renderers ' . (string) $moduleCode . ': ' . $e->getMessage();
			}
		}

		protected static function registerContentBlocks($moduleCode, $blocks, $available)
		{
			if (!is_array($blocks)) { return; }
			try {
				ContentBlockRegistry::registerMany('module:' . (string) $moduleCode, $blocks, (bool) $available);
			} catch (\Throwable $e) {
				self::$errors[] = 'Content blocks ' . (string) $moduleCode . ': ' . $e->getMessage();
			}
		}

		protected static function registerPresentationData($moduleCode, $items, $available)
		{
			if (!is_array($items)) { return; }
			try {
				PresentationDataRegistry::registerMany('module:' . (string) $moduleCode, $items, (bool) $available);
			} catch (\Throwable $e) {
				self::$errors[] = 'Presentation data ' . (string) $moduleCode . ': ' . $e->getMessage();
			}
		}

		protected static function assertNoUsedFieldTypes($moduleCode)
		{
			$codes = FieldRegistry::codesBySource('module:' . (string) $moduleCode);
			if (!$codes) {
				return;
			}

			$table = \App\Content\ContentTables::table('rubric_fields');
			$used = array();
			foreach ($codes as $code) {
				try {
					$count = (int) \DB::query(
						'SELECT COUNT(*) FROM %b WHERE rubric_field_type=%s',
						$table,
						$code
					)->getValue();
				} catch (\Throwable $e) {
					$count = 0;
				}

				if ($count > 0) {
					$used[$code] = $count;
				}
			}

			if (!$used) {
				return;
			}

			$parts = array();
			foreach ($used as $code => $count) {
				$parts[] = $code . ' (' . $count . ')';
			}

			throw new \RuntimeException('Модуль содержит используемые типы полей: ' . implode(', ', $parts));
		}

		protected static function registerViewGlobals($globals)
		{
			if (empty($globals) || !is_array($globals)) {
				return;
			}

			foreach ($globals as $key => $value) {
				self::$viewGlobals[(string) $key] = $value;
			}
		}

		protected static function registerPublicAccess(array $descriptor)
		{
			if (!empty($descriptor['public_routes']) && is_array($descriptor['public_routes'])) {
				foreach ($descriptor['public_routes'] as $route) {
					self::$publicRoutes[] = (string) $route;
				}
			}

			if (!empty($descriptor['public_prefixes']) && is_array($descriptor['public_prefixes'])) {
				foreach ($descriptor['public_prefixes'] as $prefix) {
					self::$publicPrefixes[] = (string) $prefix;
				}
			}
		}

		protected static function normalizeLifecycle($lifecycle)
		{
			if (!is_array($lifecycle) || empty($lifecycle['managed'])) {
				return array('managed' => false);
			}

			return array(
				'managed' => true,
				'adopt_existing' => !empty($lifecycle['adopt_existing']),
				'install' => isset($lifecycle['install']) ? $lifecycle['install'] : null,
				'update' => isset($lifecycle['update']) ? $lifecycle['update'] : null,
				'uninstall' => isset($lifecycle['uninstall']) ? $lifecycle['uninstall'] : null,
				'preflight' => isset($lifecycle['preflight']) && is_array($lifecycle['preflight'])
					? $lifecycle['preflight']
					: array(),
			);
		}

		protected static function normalizePreflightOperation($operation)
		{
			$operation = strtolower(trim((string) $operation));
			if (!in_array($operation, array('uninstall', 'reinstall'), true)) {
				throw new \InvalidArgumentException('Предварительная проверка для этой lifecycle-операции недоступна');
			}

			return $operation;
		}

		protected static function lifecyclePreview(array $descriptor, $operation)
		{
			$lifecycle = self::normalizeLifecycle(isset($descriptor['lifecycle']) ? $descriptor['lifecycle'] : null);
			$handlers = isset($lifecycle['preflight']) ? $lifecycle['preflight'] : array();
			$handler = isset($handlers[$operation]) ? $handlers[$operation] : null;
			if ($handler === null && $operation === 'reinstall' && isset($handlers['uninstall'])) {
				$handler = $handlers['uninstall'];
			}

			if ($handler === null) {
				return null;
			}

			if (!is_callable($handler)) {
				throw new \RuntimeException('Обработчик lifecycle-preflight недоступен');
			}

			$preview = call_user_func($handler, $descriptor, $operation);
			if (!is_array($preview)) {
				throw new \RuntimeException('Обработчик lifecycle-preflight должен вернуть массив');
			}

			return $preview;
		}

		protected static function confirmLifecyclePreflight(array $descriptor, $operation, $token)
		{
			$preview = self::lifecyclePreview($descriptor, $operation);
			if ($preview === null) {
				return;
			}

			if ((string) $token === '') {
				throw new \RuntimeException('Сначала выполните предварительную проверку lifecycle-операции');
			}

			ModuleLifecyclePreflight::consume($token, $descriptor['code'], $operation, Auth::id(), $preview);
		}

		protected static function normalizeDependencies($dependencies)
		{
			$result = array();
			foreach (is_array($dependencies) ? $dependencies : array() as $dependency) {
				$code = is_array($dependency) && isset($dependency['code'])
					? (string) $dependency['code']
					: (string) $dependency;
				$code = strtolower(trim($code));
				if ($code !== '' && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code)) {
					$result[] = $code;
				}
			}

			return array_values(array_unique($result));
		}

		protected static function assertDependencies(array $descriptor)
		{
			$missing = array();
			foreach (self::normalizeDependencies(isset($descriptor['requires']) ? $descriptor['requires'] : null) as $code) {
				$module = self::moduleMeta($code);
				if (!$module || empty($module['installed']) || empty($module['enabled'])) {
					$missing[] = self::moduleDisplayName($code);
				}
			}

			if ($missing) {
				$message = count($missing) === 1
					? 'Сначала установите и включите обязательный модуль '
					: 'Сначала установите и включите обязательные модули: ';
				throw new \RuntimeException($message . implode(', ', $missing) . '.');
			}
		}

		protected static function assertCoreSchemaReady()
		{
			$status = self::coreSchemaStatus();
			if ((int) $status['pending'] === 0 && (int) $status['changed'] === 0) {
				return;
			}

			throw new \RuntimeException(
				'Перед установкой модуля обновите схему ядра в разделе «База данных → Миграции». '
					. 'Неприменённых миграций: ' . (int) $status['pending']
					. ', изменённых: ' . (int) $status['changed'] . '.'
			);
		}

		protected static function assertNoEnabledDependents($code)
		{
			$dependents = array();
			foreach (self::$descriptors as $candidate => $descriptor) {
				if ($candidate === (string) $code
					|| !in_array((string) $code, self::normalizeDependencies(isset($descriptor['requires']) ? $descriptor['requires'] : null), true)) {
					continue;
				}

				$module = self::moduleMeta($candidate);
				if ($module && !empty($module['installed']) && !empty($module['enabled'])) {
					$dependents[] = self::moduleDisplayName($candidate);
				}
			}

			if ($dependents) {
				$message = count($dependents) === 1
					? 'Сначала отключите зависимый модуль '
					: 'Сначала отключите зависимые модули: ';
				throw new \RuntimeException($message . implode(', ', $dependents) . '.');
			}
		}

		protected static function moduleDisplayName($code)
		{
			$code = strtolower(trim((string) $code));
			$descriptor = isset(self::$descriptors[$code]) && is_array(self::$descriptors[$code])
				? self::$descriptors[$code]
				: array();
			$name = trim(isset($descriptor['name']) ? (string) $descriptor['name'] : '');
			return '«' . ($name !== '' ? $name : $code) . '»';
		}

		protected static function resolveLifecycleState($code, array $descriptor, array $lifecycle)
		{
			if (empty($lifecycle['managed'])) {
				return array(
					'installed' => true,
					'enabled' => !self::isDisabled($code),
					'status' => self::isDisabled($code) ? 'disabled' : 'enabled',
					'version' => isset($descriptor['version']) ? (string) $descriptor['version'] : '',
					'recoverable' => false,
				);
			}

			$row = self::lifecycleState($code, self::$mode !== 'public');

			if (!$row && self::$mode !== 'public' && !empty($lifecycle['adopt_existing'])) {
				self::writeLifecycleState($descriptor, 'enabled');
				$row = array('status' => 'enabled', 'version' => isset($descriptor['version']) ? (string) $descriptor['version'] : '');
			}

			$status = $row && isset($row['status']) ? (string) $row['status'] : 'available';
			return array(
				'installed' => in_array($status, array('enabled', 'disabled'), true),
				'enabled' => $status === 'enabled',
				'status' => $status,
				'version' => $row && isset($row['version']) ? (string) $row['version'] : '',
				'recoverable' => $status === 'dirty',
				'operation' => $row && isset($row['operation']) ? (string) $row['operation'] : '',
				'previous_status' => $row && isset($row['previous_status']) ? (string) $row['previous_status'] : '',
				'last_error' => $row && isset($row['last_error']) ? (string) $row['last_error'] : '',
			);
		}

		protected static function lifecycleState($code, $allowEnsure)
		{
			if (self::$publicLifecycleStates === null) {
				self::$publicLifecycleStates = array();
				try {
					self::loadLifecycleStates();
				} catch (\Throwable $e) {
					if ($allowEnsure) {
						self::ensureLifecycleTable();
						self::loadLifecycleStates();
					}
				}
			}

			return isset(self::$publicLifecycleStates[$code]) ? self::$publicLifecycleStates[$code] : null;
		}

		protected static function loadLifecycleStates()
		{
			$rows = \DB::query(
				'SELECT * FROM ' . self::lifecycleTable('modules')
			)->getAll();
			foreach ($rows as $row) {
				if (!empty($row['code'])) {
					self::$publicLifecycleStates[(string) $row['code']] = $row;
				}
			}
		}

		protected static function ensureLifecycleTable()
		{
			if (self::$lifecycleTableEnsured) {
				return;
			}

			\DB::query(
				'CREATE TABLE IF NOT EXISTS ' . self::lifecycleTable('modules') . " (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              code VARCHAR(100) NOT NULL,
              name VARCHAR(255) NOT NULL,
              version VARCHAR(50) NOT NULL DEFAULT '',
              schema_version INT UNSIGNED NOT NULL DEFAULT 0,
              status VARCHAR(30) NOT NULL DEFAULT 'available',
              base_path VARCHAR(500) NULL,
              description TEXT NULL,
              installed_at DATETIME NULL,
              enabled_at DATETIME NULL,
              disabled_at DATETIME NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uniq_code (code),
              KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
			\DB::query(
				'CREATE TABLE IF NOT EXISTS ' . self::lifecycleTable('module_events') . " (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              module_code VARCHAR(100) NOT NULL,
              event_type VARCHAR(50) NOT NULL,
              status VARCHAR(30) NOT NULL DEFAULT 'ok',
              message TEXT NULL,
              context LONGTEXT NULL,
              created_by INT UNSIGNED NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id),
              KEY idx_module_created (module_code, created_at),
              KEY idx_type (event_type)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
			$modules = self::lifecycleTable('modules');
			$columns = array(
				'previous_status' => "VARCHAR(30) NOT NULL DEFAULT '' AFTER disabled_at",
				'operation' => "VARCHAR(20) NOT NULL DEFAULT '' AFTER previous_status",
				'operation_started_at' => 'DATETIME NULL AFTER operation',
				'failed_at' => 'DATETIME NULL AFTER operation_started_at',
				'last_error' => 'TEXT NULL AFTER failed_at',
			);
			foreach ($columns as $name => $definition) {
				$exists = \DB::query(
					'SELECT 1 FROM information_schema.COLUMNS'
						. ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s LIMIT 1',
					$modules,
					$name
				)->getValue();
				if (!$exists) { \DB::query('ALTER TABLE ' . $modules . ' ADD COLUMN ' . $name . ' ' . $definition); }
			}

			self::$lifecycleTableEnsured = true;
		}

		protected static function runLifecycleChange(array $descriptor, $operation, $previousStatus)
		{
			$code = (string) $descriptor['code'];
			$transition = $operation === 'install' ? 'installing' : 'updating';
			self::writeLifecycleState($descriptor, $transition, array(
				'previous_status' => (string) $previousStatus,
				'operation' => (string) $operation,
			));
			self::writeLifecycleEvent($code, $transition, 'Операция модуля начата', 'running');

			try {
				$basePath = isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '';
				self::registerMigrations(
					$code,
					$basePath,
					isset($descriptor['migrations']) ? $descriptor['migrations'] : null,
					array('operation' => $operation, 'actor_id' => Auth::id())
				);
				self::invokeLifecycleHandler($descriptor, $operation);
				self::registerPermissions($code, self::adminValue($descriptor, 'permissions'));
				Permission::syncRegistryToDb();
				$stableStatus = $operation === 'install' ? 'enabled' : (string) $previousStatus;
				self::writeLifecycleState($descriptor, $stableStatus);
			} catch (\Throwable $e) {
				self::markLifecycleFailure($descriptor, $operation, $previousStatus, $e);
				throw $e;
			}
		}

		protected static function runUninstallChange(array $descriptor, $previousStatus)
		{
			$code = (string) $descriptor['code'];
			self::writeLifecycleState($descriptor, 'uninstalling', array(
				'previous_status' => (string) $previousStatus,
				'operation' => 'uninstall',
			));
			self::writeLifecycleEvent($code, 'uninstalling', 'Деинсталляция модуля начата', 'running');

			try {
				$hasUninstallHandler = !empty($descriptor['lifecycle']['uninstall']);
				self::invokeLifecycleHandler($descriptor, 'uninstall');
				ModuleSettings::purge($code);
				ModuleSecretStore::remove($code);
				PublicRouteRegistry::removeOwner($code);
				self::removeModulePermissions($descriptor);
				if ($hasUninstallHandler) { ModuleMigrator::forget($code); }
				self::writeLifecycleState($descriptor, 'available');
			} catch (\Throwable $e) {
				self::markLifecycleFailure($descriptor, 'uninstall', $previousStatus, $e);
				throw $e;
			}
		}

		protected static function markLifecycleFailure(array $descriptor, $operation, $previousStatus, \Throwable $error)
		{
			self::writeLifecycleState($descriptor, 'dirty', array(
				'previous_status' => (string) $previousStatus,
				'operation' => (string) $operation,
				'last_error' => $error->getMessage(),
			));
			self::writeLifecycleEvent(
				(string) $descriptor['code'],
				$operation . '_failed',
				$error->getMessage(),
				'failed'
			);
		}

		protected static function writeLifecycleState(array $descriptor, $status, array $context = array())
		{
			self::ensureLifecycleTable();
			self::$publicLifecycleStates = null;
			$code = (string) $descriptor['code'];
			$now = date('Y-m-d H:i:s');
			$table = self::lifecycleTable('modules');
			$existing = \DB::query('SELECT * FROM ' . $table . ' WHERE code = %s LIMIT 1', $code)->getAssoc();
			$stable = in_array((string) $status, array('available', 'enabled', 'disabled'), true);
			$data = array(
				'name' => isset($descriptor['name']) ? (string) $descriptor['name'] : $code,
				'version' => isset($descriptor['version']) ? (string) $descriptor['version'] : '',
				'status' => (string) $status,
				'base_path' => isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '',
				'description' => isset($descriptor['description']) ? (string) $descriptor['description'] : '',
				'installed_at' => $status === 'available' ? null : ($existing && !empty($existing['installed_at']) ? $existing['installed_at'] : $now),
				'enabled_at' => $status === 'available' ? null : ($status === 'enabled' ? $now : (isset($existing['enabled_at']) ? $existing['enabled_at'] : null)),
				'disabled_at' => $status === 'available' ? null : ($status === 'disabled' ? $now : (isset($existing['disabled_at']) ? $existing['disabled_at'] : null)),
				'previous_status' => $stable ? '' : (isset($context['previous_status']) ? (string) $context['previous_status'] : ''),
				'operation' => $stable ? '' : (isset($context['operation']) ? (string) $context['operation'] : ''),
				'operation_started_at' => $stable ? null : ($existing && !empty($existing['operation_started_at']) ? $existing['operation_started_at'] : $now),
				'failed_at' => $status === 'dirty' ? $now : null,
				'last_error' => $status === 'dirty' && isset($context['last_error']) ? (string) $context['last_error'] : null,
				'updated_at' => $now,
			);
			if ($existing) {
				\DB::Update($table, $data, 'id = %i', (int) $existing['id']);
			} else {
				$data['code'] = $code;
				\DB::Insert($table, $data);
			}
		}

		protected static function writeLifecycleEvent($code, $event, $message, $status = 'ok')
		{
			self::ensureLifecycleTable();
			\DB::Insert(self::lifecycleTable('module_events'), array(
				'module_code' => (string) $code,
				'event_type' => (string) $event,
				'status' => (string) $status,
				'message' => (string) $message,
				'context' => null,
				'created_by' => Auth::id() ?: null,
				'created_at' => date('Y-m-d H:i:s'),
			));
		}

		/** Служебные таблицы модулей общие для admin/public runtime. */
		protected static function lifecycleTable($suffix)
		{
			$suffix = (string) $suffix;
			if (!in_array($suffix, array('modules', 'module_events'), true)) {
				throw new \InvalidArgumentException('Некорректная lifecycle-таблица модулей');
			}

			return SystemTables::table($suffix);
		}

		protected static function managedDescriptor($code)
		{
			$descriptor = self::descriptor((string) $code);
			if (!$descriptor || empty($descriptor['lifecycle']['managed'])) {
				throw new \RuntimeException('Нативный управляемый модуль не найден');
			}

			return $descriptor;
		}

		protected static function moduleMeta($code)
		{
			return isset(self::$modules[$code]) ? self::$modules[$code] : null;
		}

		protected static function refreshLifecycleMeta($code)
		{
			$descriptor = self::descriptor($code);
			$lifecycle = self::normalizeLifecycle(isset($descriptor['lifecycle']) ? $descriptor['lifecycle'] : null);
			$state = self::resolveLifecycleState($code, $descriptor, $lifecycle);
			self::$modules[$code]['installed'] = $state['installed'];
			self::$modules[$code]['enabled'] = $state['enabled'];
			self::$modules[$code]['status'] = $state['status'];
			self::$modules[$code]['db_version'] = $state['version'];
			self::$modules[$code]['recoverable'] = !empty($state['recoverable']);
			self::$modules[$code]['operation'] = isset($state['operation']) ? $state['operation'] : '';
			self::$modules[$code]['previous_status'] = isset($state['previous_status']) ? $state['previous_status'] : '';
			self::$modules[$code]['last_error'] = isset($state['last_error']) ? $state['last_error'] : '';
			return self::$modules[$code];
		}

		protected static function adminValue(array $descriptor, $key)
		{
			$admin = isset($descriptor['admin']) && is_array($descriptor['admin']) ? $descriptor['admin'] : array();
			return isset($admin[$key]) ? $admin[$key] : (isset($descriptor[$key]) ? $descriptor[$key] : null);
		}

		protected static function invokeLifecycleHandler(array $descriptor, $operation)
		{
			$lifecycle = isset($descriptor['lifecycle']) && is_array($descriptor['lifecycle']) ? $descriptor['lifecycle'] : array();
			if (empty($lifecycle[$operation])) {
				return;
			}

			$handler = $lifecycle[$operation];
			if (is_callable($handler)) {
				call_user_func($handler, $descriptor);
				return;
			}

			$files = is_array($handler) ? $handler : array($handler);
			$basePath = isset($descriptor['_base_path']) ? (string) $descriptor['_base_path'] : '';
			foreach ($files as $file) {
				$path = self::resolveModulePath($basePath, (string) $file);
				if (!is_file($path)) {
					throw new \RuntimeException('Lifecycle SQL file not found: ' . $path);
				}

				$sql = ModuleMigrator::expandPrefixes((string) file_get_contents($path));
				foreach (ModuleMigrator::splitSql($sql) as $statement) {
					\DB::query($statement);
				}
			}
		}

		protected static function removeModulePermissions(array $descriptor)
		{
			$permissions = self::adminValue($descriptor, 'permissions');
			if (!is_array($permissions) || empty($permissions['items'])) {
				return;
			}

			foreach ((array) $permissions['items'] as $permission) {
				$code = is_array($permission) && isset($permission['code']) ? (string) $permission['code'] : (string) $permission;
				if ($code === '') {
					continue;
				}

				if (self::tableExists(SystemTables::table('role_permissions'))) {
					\DB::Delete(SystemTables::table('role_permissions'), 'permission_code = %s', $code);
				}

				if (self::tableExists(SystemTables::table('permissions'))) {
					\DB::Delete(SystemTables::table('permissions'), 'code = %s', $code);
				}
			}
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
