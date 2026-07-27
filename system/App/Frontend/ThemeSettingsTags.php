<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/ThemeSettingsTags.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Exposes scalar settings declared by the active theme to native templates. */
	class ThemeSettingsTags
	{
		public static function render(array $matches)
		{
			$key = isset($matches[1]) ? (string) $matches[1] : '';
			$values = ThemeSettings::publicValues();
			if ($key === '' || !array_key_exists($key, $values) || !is_scalar($values[$key])) {
				return '';
			}

			return htmlspecialchars((string) $values[$key], ENT_QUOTES, 'UTF-8');
		}
	}
