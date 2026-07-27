<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/TemporaryDirectory.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Creates private workspaces without relying on one server-specific temp path. */
	class TemporaryDirectory
	{
		public static function create($purpose)
		{
			$purpose = preg_replace('/[^a-z0-9-]+/i', '-', trim((string) $purpose));
			$purpose = trim((string) $purpose, '-');
			if ($purpose === '') {
				$purpose = 'runtime';
			}

			foreach (self::roots() as $root) {
				if (!self::prepareRoot($root)) {
					continue;
				}

				for ($attempt = 0; $attempt < 3; $attempt++) {
					$path = $root . DIRECTORY_SEPARATOR . 'ave-' . $purpose . '-' . bin2hex(random_bytes(6));
					if (@mkdir($path, 0700)) {
						return $path;
					}
				}
			}

			throw new \RuntimeException('Не удалось создать защищённый временный каталог. Проверьте права на tmp.');
		}

		public static function remove($path)
		{
			$path = rtrim(str_replace('\\', '/', (string) $path), '/');
			if ($path === '' || strpos(basename($path), 'ave-') !== 0 || !is_dir($path)) {
				return false;
			}

			return self::removeTree($path);
		}

		protected static function removeTree($path)
		{

			foreach (scandir($path) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..') {
					continue;
				}

				$item = $path . '/' . $entry;
				if (is_dir($item) && !is_link($item)) {
					self::removeTree($item);
				} else {
					@unlink($item);
				}
			}

			return @rmdir($path);
		}

		protected static function roots()
		{
			$roots = array(BASEPATH . '/tmp/runtime');
			$upload = trim((string) ini_get('upload_tmp_dir'));
			if ($upload !== '') {
				$roots[] = $upload;
			}

			$system = trim((string) sys_get_temp_dir());
			if ($system !== '') {
				$roots[] = $system;
			}

			return array_values(array_unique(array_map(function ($root) {
				return rtrim(str_replace('\\', '/', (string) $root), '/');
			}, $roots)));
		}

		protected static function prepareRoot($root)
		{
			if (!is_dir($root) && !@mkdir($root, 0700, true)) {
				return false;
			}

			return is_dir($root) && is_writable($root);
		}
	}
