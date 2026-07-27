<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/FieldTemplateManifest.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Version of native field renderers and optional file template variants. */
	class FieldTemplateManifest
	{
		protected static $version;

		public static function version()
		{
			if (self::$version !== null) { return self::$version; }
			$files = array();
			foreach (array(
				BASEPATH . '/system/App/Content/Fields/Types',
				BASEPATH . '/system/App/Content/Fields/Templates',
			) as $directory) {
				self::collect($directory, $files);
			}

			sort($files, SORT_STRING);
			$parts = array();
			foreach ($files as $file) {
				$parts[] = substr($file, strlen(BASEPATH)) . ':' . (int) @filemtime($file) . ':' . (int) @filesize($file);
			}

			self::$version = 'sha256:' . hash('sha256', implode("\n", $parts));
			return self::$version;
		}

		protected static function collect($directory, array &$files)
		{
			if (!is_dir($directory)) { return; }
			foreach (scandir($directory) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..') { continue; }
				$path = $directory . '/' . $entry;
				if (is_dir($path)) { self::collect($path, $files); }
				elseif (is_file($path) && preg_match('/\.(php|twig)$/i', $entry)) { $files[] = $path; }
			}
		}
	}
