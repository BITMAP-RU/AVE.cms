<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicModuleRuntime.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Loader\Load;
	use App\Frontend\Bootstrap as FrontendBootstrap;
	use App\Frontend\PublicPageContext;
	use App\Frontend\ThemeAssets;
	/** Native public runtime for modules from modules/<code>/app/. */
	class PublicModuleRuntime
	{
		protected static $booted = false;
		protected static $loaded = array();
		protected static $errors = array();

		public static function boot(array $runtimeConfig = array())
		{
			if (self::$booted) {
				return;
			}

			self::$booted = true;
			PublicPageContext::reset();

			try {
				Twig::init();
				ModuleManager::setMode('public');
				FrontendBootstrap::boot($runtimeConfig);
				self::loadEnabledModules();
				ThemeAssets::registerViewOverrides();
			} catch (\Throwable $e) {
				self::$errors[] = $e->getMessage();
				error_log('PublicModuleRuntime: ' . $e->getMessage());
			}
		}

		public static function isBooted()
		{
			return self::$booted;
		}

		public static function parseTags($content, array $context = array(), $scope = 'all')
		{
			if (!self::$booted) {
				return $content;
			}

			return ModuleTagRegistry::parse($content, $context, $scope);
		}

		/**
		 * Выполнить современный public route. Возвращает true, если путь обработан.
		 */
		public static function dispatchCurrent()
		{
			if (!self::$booted) {
				return false;
			}

			$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
			try {
				$result = Dispatcher::handle($path, isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
			} catch (\Throwable $e) {
				self::$errors[] = 'route ' . $path . ': ' . $e->getMessage();
				error_log('Public module route [' . $path . ']: ' . $e->getMessage());
				return false;
			}

			if (!$result['matched']) {
				return false;
			}

			// Full module pages use the native document shell so the active site
			// template, sysblocks and assets remain the single layout source.
			if (self::hasDeferredPage()) {
				return false;
			}

			if (ob_get_level() > 0 && ob_get_length() > 0) {
				ob_clean();
			}

			if ($result['output'] !== null) {
				echo $result['output'];
			}

			return true;
		}

		public static function deferPage($html, array $meta = array())
		{
			PublicPageContext::deferPage($html, $meta);
		}

		public static function hasDeferredPage()
		{
			return PublicPageContext::hasDeferredPage();
		}

		public static function loaded()
		{
			return self::$loaded;
		}

		public static function errors()
		{
			return self::$errors;
		}

		protected static function loadEnabledModules()
		{
			$root = BASEPATH . DS . 'modules';
			$compiled = CompiledModuleRegistry::load('public');
			if (is_array($compiled)) {
				self::activateCompiled($compiled);
				return;
			}

			$entries = is_dir($root) ? scandir($root) : array();
			$registryEntries = array();
			$registryRoots = array($root);
			foreach ($entries ?: array() as $code) {
				if ($code === '.' || $code === '..' || strpos($code, '.') === 0 || !preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
					continue;
				}

				$appDir = $root . DS . $code . DS . 'app';
				$registryRoots[] = $root . DS . $code;
				$manifest = $appDir . DS . 'module.php';
				if (!is_file($manifest)) {
					continue;
				}

				try {
					$namespace = 'App\\Modules\\' . self::classify($code);
					Load::regNamespace($namespace, $appDir);
					$descriptor = ModuleManager::load($manifest);
					if (!is_array($descriptor)) {
						throw new \RuntimeException('manifest не вернул массив');
					}

					$registeredCode = isset($descriptor['code']) ? (string) $descriptor['code'] : '';
					if ($registeredCode !== $code) {
						throw new \RuntimeException('code manifest не совпадает с папкой модуля');
					}

					$registryEntries[] = CompiledModuleRegistry::entry(
						$code,
						$appDir,
						$manifest,
						array($namespace => $appDir),
						$descriptor
					);

					self::rememberEnabled($code, $appDir);
				} catch (\Throwable $e) {
					self::$errors[] = $code . ': ' . $e->getMessage();
					error_log('Public module [' . $code . ']: ' . $e->getMessage());
				}
			}

			CompiledModuleRegistry::store('public', $registryEntries, $registryRoots);
		}

		protected static function activateCompiled(array $entries)
		{
			foreach ($entries as $entry) {
				$code = is_array($entry) && isset($entry['folder']) ? (string) $entry['folder'] : '';
				try {
					$descriptor = is_array($entry) ? CompiledModuleRegistry::activate($entry) : null;
					if (!is_array($descriptor) || !isset($descriptor['code']) || (string) $descriptor['code'] !== $code) {
						throw new \RuntimeException('compiled manifest не прошёл проверку');
					}

					$appDir = BASEPATH . DS . str_replace('/', DS, (string) $entry['base_path']);
					self::rememberEnabled($code, $appDir);
				} catch (\Throwable $e) {
					self::$errors[] = ($code !== '' ? $code : 'unknown') . ': ' . $e->getMessage();
					error_log('Public module [' . $code . ']: ' . $e->getMessage());
					CompiledModuleRegistry::invalidate('public');
				}
			}
		}

		protected static function rememberEnabled($code, $appDir)
		{
			$meta = ModuleManager::get($code);
			if (!$meta || empty($meta['enabled'])) {
				return;
			}

			$view = $appDir . DS . 'view';
			if (is_dir($view)) {
				Twig::addPath($view, $code . '_public');
			}

			self::$loaded[$code] = array(
				'code' => $code,
				'version' => isset($meta['version']) ? (string) $meta['version'] : '',
				'path' => $appDir,
			);
		}

		protected static function classify($code)
		{
			$parts = preg_split('/[_-]+/', (string) $code);
			$name = '';
			foreach ($parts as $part) {
				$name .= ucfirst(strtolower($part));
			}

			return $name !== '' ? $name : 'Module';
		}
	}
