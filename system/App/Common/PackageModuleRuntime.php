<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PackageModuleRuntime.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Loader\Load;

	/** Discovers installable modules that own separate public and control-panel parts. */
	class PackageModuleRuntime
	{
		protected static $adminLoaded = false;
		protected static $errors = array();

		public static function loadAdminModules()
		{
			if (self::$adminLoaded) {
				return array('loaded' => array(), 'errors' => self::$errors);
			}

			self::$adminLoaded = true;

			$loaded = array();
			$root = BASEPATH . DS . 'modules';
			$compiled = CompiledModuleRegistry::load('package_admin');
			if (is_array($compiled)) {
				foreach ($compiled as $entry) {
					try {
						$descriptor = is_array($entry) ? CompiledModuleRegistry::activate($entry) : null;
						$code = is_array($entry) && isset($entry['folder']) ? (string) $entry['folder'] : '';
						if (!is_array($descriptor) || !isset($descriptor['code']) || (string) $descriptor['code'] !== $code) {
							throw new \RuntimeException('compiled manifest не прошёл проверку');
						}

						$loaded[] = $code;
					} catch (\Throwable $e) {
						self::$errors[] = (isset($entry['folder']) ? (string) $entry['folder'] : 'unknown') . ': ' . $e->getMessage();
						CompiledModuleRegistry::invalidate('package_admin');
					}
				}

				return array('loaded' => $loaded, 'errors' => self::$errors);
			}

			$registryEntries = array();
			$registryRoots = array($root);
			foreach (self::entries($root) as $code) {
				$moduleRoot = $root . DS . $code;
				$registryRoots[] = $moduleRoot;
				$adminDir = $moduleRoot . DS . 'admin';
				$manifest = $adminDir . DS . 'module.php';
				if (!is_file($manifest)) {
					continue;
				}

				try {
					$class = self::classify($code);
					$appDir = $moduleRoot . DS . 'app';
					$namespaces = array();
					if (is_dir($appDir)) {
						$namespaces['App\\Modules\\' . $class] = $appDir;
						Load::regNamespace('App\\Modules\\' . $class, $appDir);
					}

					$namespaces['App\\Adminx\\Packages\\' . $class] = $adminDir;
					Load::regNamespace('App\\Adminx\\Packages\\' . $class, $adminDir);
					$descriptor = ModuleManager::load($manifest);
					if (!is_array($descriptor) || (string) $descriptor['code'] !== $code) {
						throw new \RuntimeException('code manifest не совпадает с папкой модуля');
					}

					$loaded[] = $code;
					$registryEntries[] = CompiledModuleRegistry::entry($code, $adminDir, $manifest, $namespaces, $descriptor);
				} catch (\Throwable $e) {
					self::$errors[] = $code . ': ' . $e->getMessage();
				}
			}

			CompiledModuleRegistry::store('package_admin', $registryEntries, $registryRoots);

			return array('loaded' => $loaded, 'errors' => self::$errors);
		}

		public static function errors()
		{
			return self::$errors;
		}

		public static function classify($code)
		{
			$parts = preg_split('/[_-]+/', (string) $code);
			$name = '';
			foreach ($parts as $part) {
				$name .= ucfirst(strtolower($part));
			}

			return $name !== '' ? $name : 'Module';
		}

		protected static function entries($root)
		{
			$items = array();
			foreach (is_dir($root) ? ((array) scandir($root)) : array() as $entry) {
				if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0
					|| !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', (string) $entry)
					|| !is_dir($root . DS . $entry)) {
					continue;
				}

				$items[] = (string) $entry;
			}

			sort($items, SORT_STRING);
			return $items;
		}
	}
