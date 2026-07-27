<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Twig/TwigExtensions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Twig;

	use App\Common\Language;
	use App\Common\Permission;
	use App\Common\Session;
	use App\Helpers\Locales;
	use App\Helpers\Html;
	use App\Helpers\Request;
	use Twig\Extension\AbstractExtension;
	use Twig\TwigFilter;
	use Twig\TwigFunction;

	class TwigExtensions extends AbstractExtension
	{
		public function __construct()
		{
			//
		}


		public function getFilters ()
		{
			return [
				// Языковые переменные
				new TwigFilter('_', [$this, 'translate']),

				new TwigFilter('html_entity_decode', [$this, 'htmlEntityDecode']),
				new TwigFilter('file_exists', [$this, 'fileExists']),

				// Работа с данными
				new TwigFilter('translate_date', [$this, 'translateDate']),
				new TwigFilter('pretty_date', [$this, 'prettyDate']),
				new TwigFilter('date_format', [$this, 'dateFormat']),
				new TwigFilter('time_format', [$this, 'timeFormat']),
				new TwigFilter('human_date', [$this, 'humanDate']),

				// Глобальные данные
				new TwigFilter('_session', [$this, 'sessionGet']),
				new TwigFilter('_get', [$this, 'requestGet']),
				new TwigFilter('_post', [$this, 'requestPost']),
				new TwigFilter('_request', [$this, 'requestRequest']),

				// Проверка прав
				new TwigFilter('_perms', [$this, 'checkPermissions']),

				// PHP методы
				new TwigFilter('stripslashes', 'stripslashes'),
			];
		}


		public function getFunctions ()
		{
			return [
				// Языковые переменные
				new TwigFunction('_', [$this, 'translate']),

				new TwigFunction('html_entity_decode', [$this, 'htmlEntityDecode']),
				new TwigFunction('file_exists', [$this, 'fileExists']),

				// Работа с данными
				new TwigFunction('translate_date', [$this, 'translateDate']),
				new TwigFunction('pretty_date', [$this, 'prettyDate']),
				new TwigFunction('date_format', [$this, 'dateFormat']),
				new TwigFunction('time_format', [$this, 'timeFormat']),
				new TwigFunction('human_date', [$this, 'humanDate']),

				// Глобальные данные
				new TwigFunction('_session', [$this, 'sessionGet']),
				new TwigFunction('_get', [$this, 'requestGet']),
				new TwigFunction('_post', [$this, 'requestPost']),
				new TwigFunction('_request', [$this, 'requestRequest']),

				// Проверка прав
				new TwigFunction('_perms', [$this, 'checkPermissions']),


				// PHP методы
				new TwigFunction('is_numeric', 'is_numeric'),
				new TwigFunction('is_iterable', 'is_iterable'),
				new TwigFunction('is_countable', 'is_countable'),
				new TwigFunction('is_null', 'is_null'),
				new TwigFunction('is_string', 'is_string'),
				new TwigFunction('is_array', 'is_array'),
				new TwigFunction('is_object', 'is_object'),
				new TwigFunction('count', 'count'),
				new TwigFunction('array_diff', 'array_diff'),
				new TwigFunction('stripslashes', 'stripslashes'),
			];
		}


		public function translate ($var): string
		{
			return Language::get($var);
		}


		public function htmlEntityDecode ($var): string
		{
			return Html::htmlDecode($var);
		}


		public function fileExists ($filename): bool
		{
			if (! $this->checkFilename($filename)) {
				return false;
			}

			return file_exists($filename);
		}


		private function checkFilename ($filename): bool
		{
			return is_string($filename) && strpos($filename, '://') === false;
		}


		public function checkPermissions ($perm): bool
		{
			return Permission::check($perm);
		}


		public function dateFormat ($string, $format = DATE_FORMAT)
		{
			if ($string != '')
			{
				$timestamp = Date::_make_timestamp($string);
			}
			else
			{
				return false;
			}

			return strftime($format, $timestamp);
		}


		public function timeFormat ($string, $format = TIME_FORMAT)
		{
			if ($string != '')
			{
				$timestamp = Date::_make_timestamp($string);
			}
			else
			{
				return false;
			}

			return strftime($format, $timestamp);
		}


		public function humanDate ($string)
		{
			if ($string != '')
			{
				$timestamp = Date::_make_timestamp($string);
			}
			else
			{
				return false;
			}

			return Locales::humanDate($timestamp);
		}


		public function translateDate ($string)
		{
			return \App\Helpers\Locales::translateDate($string);
		}


		public function prettyDate ($string)
		{
			return \App\Helpers\Locales::prettyDate($string);
		}


		public static function sessionGet ($string)
		{
			return Session::get($string);
		}


		public static function requestGet ($string)
		{
			return Request::get($string);
		}


		public static function requestPost ($string)
		{
			return Request::post($string);
		}


		public static function requestRequest ($string)
		{
			return Request::request($string);
		}
	}
