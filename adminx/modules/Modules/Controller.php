<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Modules;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			AdminAssets::addStyle($this->base() . '/modules/Modules/assets/modules.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Modules/assets/modules.js', 50);

			try {
				$result = array(
					'items' => NativeModuleAdapter::all(),
					'errors' => NativeModuleAdapter::errors(),
				);
				$fatal = '';
			} catch (\Throwable $e) {
				$result = array('items' => array(), 'errors' => array());
				$fatal = $e->getMessage();
			}

			$repository = ModuleRepository::catalog(false);
			return $this->render('@modules/index.twig', array(
				'modules' => $result['items'],
				'errors' => $result['errors'],
				'fatal' => $fatal,
				'stats' => NativeModuleAdapter::stats($result['items']),
				'can_manage' => Permission::check('manage_modules'),
				'can_install_code' => Permission::check('install_module_code'),
				'repository' => $repository,
				'repository_settings' => ModuleRepository::settings(),
				'official_repository' => ModuleRepository::officialSettings(),
			));
		}

		public function toggle(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$enabled = Request::postBool('enabled', false);
			return $this->perform($params, $enabled ? 'module.enabled' : 'module.disabled', function ($code) use ($enabled) {
				return $this->moduleAction($code, 'toggle', array($enabled));
			}, $enabled ? 'Модуль включён' : 'Модуль отключён');
		}

		public function install(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			return $this->perform($params, 'module.installed', function ($code) { return $this->moduleAction($code, 'install'); }, 'Модуль установлен');
		}

		public function update(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			return $this->perform($params, 'module.updated', function ($code) { return $this->moduleAction($code, 'update'); }, 'Миграции модуля применены');
		}

		public function reinstall(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$token = Request::postStr('preflight_token', '');
			return $this->perform($params, 'module.reinstalled', function ($code) use ($token) { return $this->moduleAction($code, 'reinstall', array($token)); }, 'Модуль переустановлен');
		}

		public function repair(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			return $this->perform($params, 'module.repaired', function ($code) { return $this->moduleAction($code, 'repair'); }, 'Операция модуля восстановлена');
		}

		public function uninstall(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$token = Request::postStr('preflight_token', '');
			return $this->perform($params, 'module.uninstalled', function ($code) use ($token) { return $this->moduleAction($code, 'uninstall', array($token)); }, 'Модуль деинсталлирован');
		}

		public function preflight(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			$operation = isset($params['operation']) ? (string) $params['operation'] : '';
			try {
				$result = NativeModuleAdapter::preflight($code, $operation);
				return $this->success('Предварительная проверка выполнена', array('data' => $result));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function remove(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			return $this->perform($params, 'module.files_removed', function ($code) {
				return NativeModuleAdapter::remove($code);
			}, 'Файлы модуля удалены');
		}

		public function presentation(array $params = array())
		{
			if (($guard = $this->guard()) !== null) { return $guard; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try {
				$item = NativeModuleAdapter::savePresentation(
					$code,
					Request::postBool('header_enabled', false),
					Request::postBool('dashboard_enabled', false)
				);
				$this->auditPresentation($item);
				return $this->success('Размещение модуля сохранено', array('data' => array('module' => $item)));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function installArchive(array $params = array())
		{
			if (($guard = $this->guard('manage_modules', 'install_module_code')) !== null) { return $guard; }
			try {
				$file = isset($_FILES['module_archive']) && is_array($_FILES['module_archive'])
					? $_FILES['module_archive']
					: array();
				$item = ModuleArchiveInstaller::install($file);
				$user = Auth::user();
				AuditLog::record('module.archive_installed', array(
					'actor_id' => Auth::id(),
					'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
					'target_type' => 'native_module',
					'target_id' => null,
					'meta' => array(
						'code' => $item['code'],
						'version' => $item['version'],
						'folder' => $item['folder'],
						'manifest_sha256' => isset($item['manifest_sha256']) ? $item['manifest_sha256'] : '',
					),
				));
				$adminUrl = isset($item['admin_url']) ? trim((string) $item['admin_url']) : '';
				$redirect = $adminUrl !== '' && strpos($adminUrl, '/') === 0
					? $this->base() . $adminUrl
					: $this->base() . '/modules';
				return $this->success('Модуль «' . $item['name'] . '» установлен', array(
					'data' => array('module' => $item),
					'redirect' => $redirect,
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function saveRepositorySettings(array $params = array())
		{
			if (($guard = $this->guard('manage_modules', 'install_module_code')) !== null) { return $guard; }
			try {
				$settings = ModuleRepository::saveSettings(array(
					'enabled' => Request::postBool('enabled', false),
					'url' => Request::postStr('url', ''),
					'public_key' => Request::postStr('public_key', ''),
				));
				$this->auditRepository('module.repository_settings_updated', array(
					'enabled' => !empty($settings['enabled']),
					'url' => isset($settings['url']) ? $settings['url'] : '',
				));
				return $this->success('Настройки каталога сохранены', array('data' => array('settings' => $settings)));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function restoreOfficialRepository(array $params = array())
		{
			if (($guard = $this->guard('manage_modules', 'install_module_code')) !== null) { return $guard; }
			try {
				$settings = ModuleRepository::restoreOfficial();
				$this->auditRepository('module.repository_official_restored', array('url' => $settings['url']));
				return $this->success('Официальный каталог модулей восстановлен', array('data' => array('settings' => $settings)));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		public function refreshRepository(array $params = array())
		{
			if (($guard = $this->guard('view_modules')) !== null) { return $guard; }
			$catalog = ModuleRepository::catalog(true);
			if (!empty($catalog['error'])) { return $this->error($catalog['error'], array(), 422); }

			return $this->success('Каталог модулей обновлён', array('data' => array('catalog' => $catalog)));
		}

		public function installRepository(array $params = array())
		{
			if (($guard = $this->guard('manage_modules', 'install_module_code')) !== null) { return $guard; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try {
				$item = ModuleRepository::install($code);
				$updated = isset($item['repository_operation']) && $item['repository_operation'] === 'update';
				$this->auditRepository($updated ? 'module.repository_updated' : 'module.repository_installed', array(
					'code' => $code,
					'version' => isset($item['version']) ? $item['version'] : (isset($item['file_version']) ? $item['file_version'] : ''),
					'manifest_sha256' => isset($item['manifest_sha256']) ? $item['manifest_sha256'] : '',
				));
				$adminUrl = isset($item['admin_url']) ? trim((string) $item['admin_url']) : '';
				$redirect = $adminUrl !== '' && strpos($adminUrl, '/') === 0
					? $this->base() . $adminUrl
					: $this->base() . '/modules';
				return $this->success('Модуль «' . (isset($item['name']) ? $item['name'] : $code) . '» '
					. ($updated ? 'обновлён из каталога' : 'установлен из каталога'), array(
					'data' => array('module' => $item),
					'redirect' => $updated ? $this->base() . '/modules?tab=catalog' : $redirect,
				));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		protected function perform(array $params, $action, $callback, $message)
		{
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try {
				$item = call_user_func($callback, $code);
				$user = Auth::user();
				AuditLog::record($action, array(
					'actor_id' => Auth::id(),
					'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
					'target_type' => 'native_module',
					'target_id' => !empty($item['id']) ? (int) $item['id'] : null,
					'meta' => array('code' => $code, 'version' => isset($item['file_version']) ? $item['file_version'] : ''),
				));
				return $this->success($message, array('data' => array('module' => $item)));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}
		}

		protected function moduleAction($code, $method, array $arguments = array())
		{
			return call_user_func_array(array(NativeModuleAdapter::class, $method), array_merge(array($code), $arguments));
		}

		protected function auditPresentation(array $item)
		{
			$user = Auth::user();
			AuditLog::record('module.presentation_updated', array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'native_module',
				'target_id' => null,
				'meta' => array(
					'code' => $item['code'],
					'header_enabled' => !empty($item['header_enabled']),
					'dashboard_enabled' => !empty($item['dashboard_enabled']),
				),
			));
		}

		protected function auditRepository($action, array $meta)
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'module_repository',
				'target_id' => null,
				'meta' => $meta,
			));
		}

		protected function guard($permission = 'manage_modules', $additionalPermission = '')
		{
			return $this->guardPermission($permission, $additionalPermission);
		}
	}
