<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/CompiledModuleRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	use App\Common\Loader\Load;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** JSON cache for discovered module descriptors. */
	class CompiledModuleRegistry
	{
		const FORMAT_VERSION = 1;
		const VALIDATE_INTERVAL = 60;

		protected static $payload;

		protected function __construct()
		{
			//
		}

		/** Return a compiled scope, or null when discovery must run. */
		public static function load($scope, array $expectedRoots = array())
		{
			$scope = self::scopeName($scope);
			$payload = self::payload();
			if ($scope === '' || empty($payload['scopes'][$scope]) || !is_array($payload['scopes'][$scope])) {
				return null;
			}

			$data = $payload['scopes'][$scope];
			if (!self::containsRoots($data, $expectedRoots)) {
				self::invalidate($scope);
				return null;
			}

			$validatedAt = isset($data['validated_at']) ? (int) $data['validated_at'] : 0;
			$validateNow = $scope === 'adminx' || $validatedAt < time() - self::VALIDATE_INTERVAL;
			if ($validateNow) {
				if (!self::validateScope($data)) {
					self::invalidate($scope);
					return null;
				}

				self::touch($scope);
			}

			return isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
		}

		/** Persist one independently discoverable module scope. */
		public static function store($scope, array $entries, array $roots)
		{
			$scope = self::scopeName($scope);
			if ($scope === '') {
				return false;
			}

			$rootState = array();
			foreach ($roots as $root) {
				$relative = self::relativePath($root);
				if ($relative === '' || isset($rootState[$relative])) {
					continue;
				}

				$rootState[$relative] = array(
					'path' => $relative,
					'exists' => is_dir($root),
					'mtime' => is_dir($root) ? (int) @filemtime($root) : 0,
				);
			}

			$now = time();
			return self::mutate(function (array $payload) use ($scope, $entries, $rootState, $now) {
				$payload['scopes'][$scope] = array(
					'generated_at' => $now,
					'validated_at' => $now,
					'roots' => array_values($rootState),
					'entries' => array_values($entries),
				);

				return $payload;
			});
		}

		/** Remove one scope or the complete registry. */
		public static function invalidate($scope = null)
		{
			if ($scope === null) {
				self::$payload = null;
				$file = self::file();
				return !is_file($file) || @unlink($file);
			}

			$scope = self::scopeName($scope);
			if ($scope === '') {
				return false;
			}

			return self::mutate(function (array $payload) use ($scope) {
				unset($payload['scopes'][$scope]);
				return $payload;
			});
		}

		/** Build a portable entry from a descriptor already evaluated by discovery. */
		public static function entry($folder, $basePath, $manifest, array $namespaces, array $descriptor)
		{
			$cached = $descriptor;
			unset($cached['_base_path']);

			$namespacePaths = array();
			foreach ($namespaces as $namespace => $path) {
				$relative = self::relativePath($path);
				if ($relative !== '') {
					$namespacePaths[trim((string) $namespace, '\\')] = $relative;
				}
			}

			$manifestPath = self::relativePath($manifest);
			$cacheable = empty($cached['registry']['dynamic']) && self::isCacheable($cached);
			if ($cacheable && !is_string(json_encode($cached))) {
				$cacheable = false;
			}

			return array(
				'folder' => (string) $folder,
				'code' => isset($descriptor['code']) ? (string) $descriptor['code'] : (string) $folder,
				'base_path' => self::relativePath($basePath),
				'manifest' => $manifestPath,
				'manifest_mtime' => (int) @filemtime($manifest),
				'manifest_size' => (int) @filesize($manifest),
				'namespaces' => $namespacePaths,
				'descriptor' => $cacheable ? $cached : null,
				'dynamic' => !$cacheable,
			);
		}

		/** Register namespaces and activate one compiled descriptor. */
		public static function activate(array $entry)
		{
			$descriptor = self::descriptor($entry);
			if (!$descriptor) {
				return null;
			}

			ModuleManager::register($descriptor);
			return $descriptor;
		}

		/** Read one descriptor without registering its runtime contributions. */
		public static function descriptor(array $entry)
		{
			$basePath = self::absolutePath(isset($entry['base_path']) ? $entry['base_path'] : '');
			$manifest = self::absolutePath(isset($entry['manifest']) ? $entry['manifest'] : '');
			if ($basePath === '' || !is_dir($basePath) || $manifest === '' || !is_file($manifest)) {
				return null;
			}

			foreach (isset($entry['namespaces']) && is_array($entry['namespaces']) ? $entry['namespaces'] : array() as $namespace => $path) {
				$path = self::absolutePath($path);
				if ($namespace !== '' && $path !== '' && is_dir($path)) {
					Load::regNamespace((string) $namespace, $path);
				}
			}

			if (isset($entry['descriptor']) && is_array($entry['descriptor'])) {
				$descriptor = $entry['descriptor'];
				$descriptor['_base_path'] = $basePath;
				return $descriptor;
			}

			$descriptor = include $manifest;
			if (!is_array($descriptor)) {
				return null;
			}

			$descriptor['_base_path'] = $basePath;
			return $descriptor;
		}

		public static function file()
		{
			return rtrim(str_replace('\\', '/', BASEPATH), '/') . '/storage/runtime/module-registry.json';
		}

		protected static function validateScope(array $scope)
		{
			foreach (isset($scope['roots']) && is_array($scope['roots']) ? $scope['roots'] : array() as $root) {
				$path = self::absolutePath(isset($root['path']) ? $root['path'] : '');
				$exists = !empty($root['exists']);
				if ($path === '' || is_dir($path) !== $exists || ($exists && (int) @filemtime($path) !== (int) $root['mtime'])) {
					return false;
				}
			}

			foreach (isset($scope['entries']) && is_array($scope['entries']) ? $scope['entries'] : array() as $entry) {
				$manifest = self::absolutePath(isset($entry['manifest']) ? $entry['manifest'] : '');
				if ($manifest === '' || !is_file($manifest)
					|| (int) @filemtime($manifest) !== (int) $entry['manifest_mtime']
					|| (int) @filesize($manifest) !== (int) $entry['manifest_size']) {
					return false;
				}
			}

			return true;
		}

		protected static function containsRoots(array $scope, array $expectedRoots)
		{
			if (!$expectedRoots) {
				return true;
			}

			$known = array();
			foreach (isset($scope['roots']) && is_array($scope['roots']) ? $scope['roots'] : array() as $root) {
				if (isset($root['path'])) {
					$known[(string) $root['path']] = true;
				}
			}

			foreach ($expectedRoots as $root) {
				$relative = self::relativePath($root);
				if ($relative === '' || !isset($known[$relative])) {
					return false;
				}
			}

			return true;
		}

		protected static function isCacheable($value)
		{
			if (is_array($value)) {
				foreach ($value as $item) {
					if (!self::isCacheable($item)) {
						return false;
					}
				}

				return true;
			}

			return $value === null || is_scalar($value);
		}

		protected static function touch($scope)
		{
			$now = time();
			self::mutate(function (array $payload) use ($scope, $now) {
				if (isset($payload['scopes'][$scope])) {
					$payload['scopes'][$scope]['validated_at'] = $now;
				}

				return $payload;
			});
		}

		protected static function payload($fresh = false)
		{
			if (!$fresh && is_array(self::$payload)) {
				return self::$payload;
			}

			$payload = array('format' => self::FORMAT_VERSION, 'scopes' => array());
			$file = self::file();
			if (is_file($file)) {
				$decoded = json_decode((string) @file_get_contents($file), true);
				if (is_array($decoded) && isset($decoded['format']) && (int) $decoded['format'] === self::FORMAT_VERSION
					&& isset($decoded['scopes']) && is_array($decoded['scopes'])) {
					$payload = $decoded;
				}
			}

			self::$payload = $payload;
			return $payload;
		}

		protected static function mutate($callback)
		{
			$directory = dirname(self::file());
			if (!RuntimeDirectoryGuard::protect($directory)) {
				return false;
			}

			$lock = @fopen($directory . '/module-registry.lock', 'c');
			if (!$lock || !@flock($lock, LOCK_EX)) {
				if ($lock) { @fclose($lock); }
				return false;
			}

			try {
				$payload = self::payload(true);
				$payload = call_user_func($callback, $payload);
				$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				if (!is_string($json)) {
					return false;
				}

				$tmp = self::file() . '.tmp-' . str_replace('.', '', uniqid('', true));
				if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, self::file())) {
					@unlink($tmp);
					return false;
				}

				@chmod(self::file(), 0664);
				self::$payload = $payload;
				return true;
			} finally {
				@flock($lock, LOCK_UN);
				@fclose($lock);
			}
		}

		protected static function relativePath($path)
		{
			$base = rtrim(str_replace('\\', '/', BASEPATH), '/');
			$path = rtrim(str_replace('\\', '/', (string) $path), '/');
			if ($path === $base) {
				return '.';
			}

			return strpos($path, $base . '/') === 0 ? substr($path, strlen($base) + 1) : '';
		}

		protected static function absolutePath($path)
		{
			$path = trim(str_replace('\\', '/', (string) $path), '/');
			if ($path === '' || $path === '.' || strpos('/' . $path . '/', '/../') !== false || strpos($path, "\0") !== false) {
				return $path === '.' ? rtrim(str_replace('\\', '/', BASEPATH), '/') : '';
			}

			return rtrim(str_replace('\\', '/', BASEPATH), '/') . '/' . $path;
		}

		protected static function scopeName($scope)
		{
			$scope = (string) $scope;
			return preg_match('/^[a-z][a-z0-9_]{0,31}$/', $scope) ? $scope : '';
		}
	}
