<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/RuntimeDirectoryGuard.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Creates runtime directories and blocks direct HTTP access to their contents. */
	class RuntimeDirectoryGuard
	{
		/** Прежняя защита состояла из одной строки синтаксиса Apache 2.2. */
		const LEGACY_CONTENTS = "Deny from all\n";

		/**
		 * Содержимое сторожевого файла.
		 *
		 * Apache 2.4 понимает `Deny from all`, только пока загружен
		 * mod_access_compat, поэтому основной директивой идёт `Require all
		 * denied`, а старый синтаксис остаётся запасным для Apache 2.2.
		 * Оба варианта нужны: сервер клиента может быть любым.
		 */
		public static function contents()
		{
			return "Options -Indexes\n\n"
				. "<IfModule mod_authz_core.c>\n"
				. "  Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "  Order allow,deny\n"
				. "  Deny from all\n"
				. "</IfModule>\n";
		}

		public static function protect($directory)
		{
			$directory = rtrim((string) $directory, '/\\');
			if ($directory === '') {
				return false;
			}

			if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
				return false;
			}

			$file = $directory . '/.htaccess';
			if (is_file($file) && !self::upgradable($file)) {
				return true;
			}

			return @file_put_contents($file, self::contents(), LOCK_EX) !== false;
		}

		/**
		 * Сторож переписывается, только если это ровно прежний однострочник.
		 * Файл, изменённый администратором, остаётся нетронутым.
		 */
		protected static function upgradable($file)
		{
			$current = @file_get_contents($file);
			return $current !== false && trim((string) $current) === trim(self::LEGACY_CONTENTS);
		}
	}
