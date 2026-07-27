<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/DebugChannel.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Request-scoped structured diagnostics consumed by development interfaces. */
	class DebugChannel
	{
		protected static $entries = array();

		public static function push($value, $label = '', $file = '', $line = 0)
		{
			$seen = array();
			self::$entries[] = array(
				'label' => trim((string) $label) !== '' ? trim((string) $label) : 'Данные',
				'type' => self::type($value),
				'value' => self::normalize($value, 0, $seen),
				'file' => self::relativePath($file),
				'line' => (int) $line,
				'time' => microtime(true),
			);
		}

		public static function all()
		{
			return self::$entries;
		}

		public static function reset()
		{
			self::$entries = array();
		}

		protected static function normalize($value, $depth, array &$seen)
		{
			if ($depth >= 8) { return '[maximum depth]'; }
			if (is_array($value)) {
				$result = array();
				foreach ($value as $key => $item) {
					$result[$key] = self::normalize($item, $depth + 1, $seen);
				}

				return $result;
			}

			if (is_object($value)) {
				$hash = spl_object_hash($value);
				if (isset($seen[$hash])) { return '[recursive ' . get_class($value) . ']'; }
				$seen[$hash] = true;
				$result = array('@class' => get_class($value));
				foreach (get_object_vars($value) as $key => $item) {
					$result[$key] = self::normalize($item, $depth + 1, $seen);
				}

				unset($seen[$hash]);
				return $result;
			}

			if (is_resource($value)) { return '[resource ' . get_resource_type($value) . ']'; }
			return $value;
		}

		protected static function type($value)
		{
			if (is_object($value)) { return get_class($value); }
			if (is_array($value)) { return 'array (' . count($value) . ')'; }
			return gettype($value);
		}

		protected static function relativePath($file)
		{
			$file = (string) $file;
			if (defined('BASEPATH')) {
				return str_replace(rtrim(BASEPATH, '/\\') . DIRECTORY_SEPARATOR, '', $file);
			}

			return $file;
		}
	}
