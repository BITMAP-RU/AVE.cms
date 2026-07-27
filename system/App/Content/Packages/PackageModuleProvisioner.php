<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Packages/PackageModuleProvisioner.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Packages;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ModuleManager;
	use App\Common\PackageModuleRuntime;
	use App\Common\Loader\Load;

	/** Installs and enables modules declared by a trusted content package. */
	class PackageModuleProvisioner
	{
		public function loadRuntime(array $codes)
		{
			foreach ($codes as $code) {
				$code = strtolower(trim((string) $code));
				if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code)) {
					throw new \InvalidArgumentException('Некорректный код модуля пакета');
				}

				$appDir = BASEPATH . DS . 'modules' . DS . $code . DS . 'app';
				if (!is_file($appDir . DS . 'module.php')) {
					throw new \RuntimeException('Публичная часть модуля не найдена: ' . $code);
				}

				Load::regNamespace('App\\Modules\\' . PackageModuleRuntime::classify($code), $appDir);
			}
		}

		public function activate(array $codes, array $originalStates = array(), callable $onState = null, $packageCode = '')
		{
			$this->loadRuntime($codes);
			$contextStates = PackageDependencyContext::states((string) $packageCode);
			$result = array();
			foreach ($codes as $code) {
				$code = strtolower(trim((string) $code));
				if ($code === '') {
					continue;
				}

				$module = ModuleManager::get($code);
				if (!$module || empty($module['managed'])) {
					throw new \RuntimeException('Для пакета отсутствует управляемый модуль: ' . $code);
				}

				$state = isset($originalStates[$code])
					? $this->normalizeState($originalStates[$code])
					: (isset($contextStates[$code])
						? $this->normalizeState($contextStates[$code])
						: $this->state($module));
				if ($onState !== null && !isset($originalStates[$code])) {
					call_user_func($onState, $code, $state);
				}

				try {
					if (!empty($module['recoverable']) || (isset($module['status']) && $module['status'] === 'dirty')) {
						$module = ModuleManager::repair($code);
					}

					if (empty($module['installed'])) {
						$module = ModuleManager::install($code);
					} else {
						if (!empty($module['version']) && isset($module['db_version'])
							&& (string) $module['version'] !== (string) $module['db_version']) {
							$module = ModuleManager::update($code);
						}

						if (empty($module['enabled'])) {
							$module = ModuleManager::toggle($code, true);
						}
					}
				} catch (\Throwable $e) {
					throw new \RuntimeException(
						'Не удалось подключить модуль «' . $code . '»: ' . $e->getMessage(),
						0,
						$e
					);
				}

				$result[$code] = array(
					'code' => $code,
					'original_state' => $state,
					'installed' => !empty($module['installed']),
					'enabled' => !empty($module['enabled']),
				);
			}

			return $result;
		}

		protected function state(array $module)
		{
			if (isset($module['status']) && $module['status'] === 'dirty') {
				$previous = isset($module['previous_status']) ? $module['previous_status'] : '';
				return $this->normalizeState($previous);
			}

			if (empty($module['installed'])) {
				return 'available';
			}

			return !empty($module['enabled']) ? 'enabled' : 'disabled';
		}

		protected function normalizeState($state)
		{
			$state = strtolower(trim((string) $state));
			return in_array($state, array('available', 'disabled', 'enabled'), true) ? $state : 'available';
		}
	}
