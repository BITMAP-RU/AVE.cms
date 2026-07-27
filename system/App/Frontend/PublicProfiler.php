<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicProfiler.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Request-scoped timeline used by the public debug toolbar. */
	class PublicProfiler
	{
		protected static $timeline = array();

		public static function record(array $path, $value)
		{
			if (!$path) { return; }
			$cursor =& self::$timeline;
			foreach ($path as $segment) {
				$segment = (string) $segment;
				if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
					$cursor[$segment] = array();
				}

				$cursor =& $cursor[$segment];
			}

			$cursor = $value;
			unset($cursor);
		}

		public static function timeline()
		{
			return self::$timeline;
		}

		public static function reset()
		{
			self::$timeline = array();
		}
	}
