<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleSecretStore.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Protected local secret data owned by an installable module. */
	class ModuleSecretStore
	{
		public static function read($module)
		{
			$file = self::file($module);
			$data = is_file($file) ? include $file : array();
			return is_array($data) ? $data : array();
		}

		public static function write($module, array $values)
		{
			$file = self::file($module);
			$dir = dirname($file);
			if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
				throw new \RuntimeException('Не удалось создать каталог секретов модуля');
			}

			@chmod($dir, 0700);
			$content = "<?php\n\ndefined('BASEPATH') || die('Direct access to this location is not allowed.');\n\nreturn "
				. var_export($values, true) . ";\n";
			$tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
			if (file_put_contents($tmp, $content, LOCK_EX) === false) {
				throw new \RuntimeException('Не удалось записать секреты модуля');
			}

			@chmod($tmp, 0600);
			if (!@rename($tmp, $file)) {
				@unlink($tmp);
				throw new \RuntimeException('Не удалось сохранить секреты модуля');
			}

			@chmod($file, 0600);
			if (function_exists('opcache_invalidate')) {
				@opcache_invalidate($file, true);
			}
		}

		/** Удалить локальные секреты вместе с физически удаляемым пакетом. */
		public static function remove($module)
		{
			$file = self::file($module);
			if (is_file($file) && !@unlink($file)) {
				throw new \RuntimeException('Не удалось удалить секреты модуля');
			}

			if (function_exists('opcache_invalidate')) {
				@opcache_invalidate($file, true);
			}
		}

		protected static function file($module)
		{
			$module = strtolower(trim((string) $module));
			if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $module)) {
				throw new \InvalidArgumentException('Некорректный код модуля');
			}

			return BASEPATH . DS . 'storage' . DS . 'secrets' . DS . 'modules' . DS . $module . '.php';
		}
	}
