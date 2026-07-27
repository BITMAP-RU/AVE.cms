<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Phone.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Phone
	{
		public static function normalize($value, $defaultCountryCode = '7')
		{
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}

			$digits = preg_replace('/\D+/', '', $value);
			if (strpos($value, '00') === 0 && strpos($digits, '00') === 0) {
				$digits = substr($digits, 2);
			}

			$defaultCountryCode = preg_replace('/\D+/', '', (string) $defaultCountryCode);
			if ($defaultCountryCode === '7') {
				if (strlen($digits) === 11 && $digits[0] === '8') {
					$digits = '7' . substr($digits, 1);
				} elseif (strlen($digits) === 10) {
					$digits = '7' . $digits;
				}
			}

			if (!preg_match('/^[1-9][0-9]{9,14}$/', $digits)) {
				return '';
			}

			return '+' . $digits;
		}

		public static function valid($value)
		{
			return self::normalize($value) !== '';
		}

		public static function digits($value)
		{
			$normalized = self::normalize($value);
			return $normalized === '' ? '' : substr($normalized, 1);
		}

		public static function mask($value)
		{
			$normalized = self::normalize($value);
			if ($normalized === '') {
				return '';
			}

			$visible = substr($normalized, -4);
			return substr($normalized, 0, 2) . str_repeat('*', max(4, strlen($normalized) - 6)) . $visible;
		}
	}
