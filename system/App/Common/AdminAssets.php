<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/AdminAssets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Реестр CSS/JS ресурсов новой админки, включая assets отдельных модулей. */
	class AdminAssets
	{
		protected static $styles = array();
		protected static $scripts = array();

		public static function addStyle($href, $priority = 100)
		{
			self::add(self::$styles, $href, $priority);
		}

		public static function addScript($src, $priority = 100)
		{
			self::add(self::$scripts, $src, $priority);
		}

		public static function styles()
		{
			return self::sorted(self::$styles);
		}

		public static function scripts()
		{
			return self::sorted(self::$scripts);
		}

		protected static function add(array &$bucket, $url, $priority)
		{
			$url = trim((string) $url);
			if ($url === '') {
				return;
			}

			$bucket[$url] = array(
				'url' => $url,
				'priority' => (int) $priority,
			);
		}

		protected static function sorted(array $items)
		{
			usort($items, function ($a, $b) {
				if ($a['priority'] === $b['priority']) {
					return strcmp($a['url'], $b['url']);
				}

				return $a['priority'] < $b['priority'] ? -1 : 1;
			});
			return $items;
		}
	}
