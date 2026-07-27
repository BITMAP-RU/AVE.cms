<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         setup/Localization.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('AVE_SETUP') || die('Direct access to this location is not allowed.');

	class AveSetupLocale
	{
		const DEFAULT_LOCALE = 'ru';
		const SESSION_KEY = 'setup_language';

		protected static $locale = self::DEFAULT_LOCALE;
		protected static $translations = array();

		public static function boot($requested = '')
		{
			$requested = strtolower(trim((string) $requested));
			$stored = isset($_SESSION[self::SESSION_KEY])
				? strtolower(trim((string) $_SESSION[self::SESSION_KEY]))
				: '';
			$locale = self::supports($requested)
				? $requested
				: (self::supports($stored) ? $stored : self::DEFAULT_LOCALE);

			self::$locale = $locale;
			$_SESSION[self::SESSION_KEY] = $locale;
			$file = __DIR__ . '/language/' . $locale . '.php';
			$translations = is_file($file) ? require $file : array();
			self::$translations = is_array($translations) ? $translations : array();

			uksort(self::$translations, function ($left, $right) {
				return strlen((string) $right) <=> strlen((string) $left);
			});
		}

		public static function current()
		{
			return self::$locale;
		}

		public static function supports($locale)
		{
			$locale = strtolower(trim((string) $locale));
			return $locale !== '' && is_file(__DIR__ . '/language/' . $locale . '.php');
		}

		public static function locales()
		{
			return array(
				'ru' => 'Русский',
				'en' => 'English',
			);
		}

		public static function translate($value)
		{
			$value = (string) $value;
			if ($value === '' || !self::$translations) {
				return $value;
			}

			if (isset(self::$translations[$value])) {
				return self::$translations[$value];
			}

			foreach (self::$translations as $source => $target) {
				$startsWithWord = preg_match('/^[\p{L}\p{N}_]/u', (string) $source);
				$endsWithWord = preg_match('/[\p{L}\p{N}_]$/u', (string) $source);
				if (!$startsWithWord && !$endsWithWord) {
					$value = str_replace($source, $target, $value);
					continue;
				}

				$pattern = '~'
					. ($startsWithWord ? '(?<![\p{L}\p{N}_])' : '')
					. preg_quote($source, '~')
					. ($endsWithWord ? '(?![\p{L}\p{N}_])' : '')
					. '~u';
				$replacement = str_replace(array('\\', '$'), array('\\\\', '\\$'), $target);
				$value = preg_replace($pattern, $replacement, $value);
			}

			return $value;
		}

		public static function translatePayload(array $payload)
		{
			foreach ($payload as $key => $value) {
				if (is_array($value)) {
					$payload[$key] = self::translatePayload($value);
				} elseif (is_string($value)) {
					$payload[$key] = self::translate($value);
				}
			}

			return $payload;
		}

		public static function clientTranslations()
		{
			return self::$translations;
		}
	}
