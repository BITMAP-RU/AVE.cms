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
			if (is_file($file)) {
				return true;
			}

			return @file_put_contents($file, "Deny from all\n", LOCK_EX) !== false;
		}
	}
