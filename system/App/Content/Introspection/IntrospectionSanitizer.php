<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Introspection/IntrospectionSanitizer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Introspection;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Removes secrets and oversized values from diagnostic payloads. */
	class IntrospectionSanitizer
	{
		const REDACTED = '[скрыто]';

		public static function value($value, $key = '', $depth = 0)
		{
			if (self::sensitive($key)) {
				return self::REDACTED;
			}

			if ($depth > 8) {
				return '[превышена глубина]';
			}

			if (is_object($value)) {
				$value = get_object_vars($value);
			}

			if (is_array($value)) {
				$result = array();
				$count = 0;
				foreach ($value as $itemKey => $item) {
					if ($count++ >= 250) {
						$result['_truncated'] = true;
						break;
					}

					$result[$itemKey] = self::value($item, (string) $itemKey, $depth + 1);
				}

				return $result;
			}

			if (is_resource($value)) {
				return '[resource]';
			}

			if (is_string($value)) {
				if (strpos($value, "\0") !== false) {
					return '[binary]';
				}

				if (mb_strlen($value) > 5000) {
					return mb_substr($value, 0, 5000) . "\n[значение сокращено]";
				}
			}

			return $value;
		}

		public static function fieldValue($value, $alias, $title)
		{
			$key = trim((string) $alias . ' ' . (string) $title);
			return self::value($value, $key);
		}

		protected static function sensitive($key)
		{
			return (bool) preg_match(
				'/(?:pass(?:word)?|secret|token|cookie|session|authorization|credential|private[_ -]?key|api[_ -]?key|db[_ -]?(?:pass|password)|парол|секрет|токен|ключ[_ -]?api)/iu',
				(string) $key
			);
		}
	}
