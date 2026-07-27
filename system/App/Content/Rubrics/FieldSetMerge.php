<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Rubrics/FieldSetMerge.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Three-way merge primitives for linked rubric field sets. */
	class FieldSetMerge
	{
		public static function value($current, $previous, $next)
		{
			return self::same($current, $previous) ? $next : $current;
		}

		public static function settings(array $current, array $previous, array $next)
		{
			$keys = array_unique(array_merge(array_keys($previous), array_keys($next)));
			foreach ($keys as $key) {
				$hadPrevious = array_key_exists($key, $previous);
				$hasCurrent = array_key_exists($key, $current);
				$hasNext = array_key_exists($key, $next);
				$currentValue = $hasCurrent ? $current[$key] : null;
				$previousValue = $hadPrevious ? $previous[$key] : null;

				if ($hasCurrent !== $hadPrevious || !self::same($currentValue, $previousValue)) {
					continue;
				}

				if ($hasNext) {
					$current[$key] = $next[$key];
				} else {
					unset($current[$key]);
				}
			}

			return $current;
		}

		public static function same($left, $right)
		{
			return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				=== json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}
