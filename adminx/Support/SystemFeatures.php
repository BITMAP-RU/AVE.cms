<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/SystemFeatures.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class SystemFeatures
	{
		public static function enabled($feature)
		{
			$global = getenv('ADMINX_SYSTEM_TOOLS');
			if ($global !== false && $global !== '' && !filter_var($global, FILTER_VALIDATE_BOOLEAN)) {
				return false;
			}

			$key = 'ADMINX_SYSTEM_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $feature));
			$value = getenv($key);
			return $value === false || $value === '' ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}
	}
