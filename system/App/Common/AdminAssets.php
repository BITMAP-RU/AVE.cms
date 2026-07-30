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
			$url = self::normalizeUrl($url);
			if ($url === '') {
				return;
			}

			$bucket[$url] = array(
				'url' => $url,
				'priority' => (int) $priority,
			);
		}

		/**
		 * Package manifests can be compiled with a CLI-relative ABS_PATH or with
		 * the control panel mount path. Resolve their public assets at request time.
		 */
		protected static function normalizeUrl($url)
		{
			$url = trim((string) $url);
			if ($url === '' || preg_match('#^(?:https?:)?//#i', $url)) {
				return $url;
			}

			$path = (string) parse_url($url, PHP_URL_PATH);
			$modulePosition = strpos('/' . ltrim($path, './'), '/modules/');
			$modulePath = $modulePosition !== false
				? substr('/' . ltrim($path, './'), $modulePosition)
				: '';
			if ($modulePath !== ''
				&& preg_match('#^/modules/[A-Z][A-Za-z0-9_-]*/assets/#', $modulePath)
				&& defined('ADMINX_BASE')) {
				$query = parse_url($url, PHP_URL_QUERY);
				$fragment = parse_url($url, PHP_URL_FRAGMENT);
				return rtrim((string) ADMINX_BASE, '/') . $modulePath
					. ($query !== null && $query !== false ? '?' . $query : '')
					. ($fragment !== null && $fragment !== false ? '#' . $fragment : '');
			}

			if ($modulePath === ''
				|| !preg_match('#^/modules/[a-z][a-z0-9_-]*/admin/assets/#', $modulePath)) {
				return $url;
			}

			$base = '';
			if (defined('ADMINX_BASE')) {
				$base = str_replace('\\', '/', dirname(ADMINX_BASE));
			} elseif (defined('ABS_PATH')) {
				$base = rtrim(str_replace('\\', '/', ABS_PATH), '/');
			}

			if ($base === '.' || $base === '/') {
				$base = '';
			}

			$query = parse_url($url, PHP_URL_QUERY);
			$fragment = parse_url($url, PHP_URL_FRAGMENT);

			return $base . $modulePath
				. ($query !== null && $query !== false ? '?' . $query : '')
				. ($fragment !== null && $fragment !== false ? '#' . $fragment : '');
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
