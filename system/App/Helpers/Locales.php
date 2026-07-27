<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Locales.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Locale operations used by the single-language public runtime. */
	class Locales
	{
		public static function lower($value)
		{
			return mb_strtolower((string) $value, 'UTF-8');
		}

		public static function transliterate($value)
		{
			return strtr((string) $value, self::transliterationMap());
		}

		/** Transliteration profile used by stable URL slug generation. */
		public static function transliterateSlug($value)
		{
			$map = array_replace(self::transliterationMap(), array(
				'Ё' => 'Yo', 'Ж' => 'Zh', 'Й' => 'Y', 'Х' => 'Kh',
				'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
				'Ю' => 'Yu', 'Я' => 'Ya',
				'й' => 'y', 'х' => 'kh', 'ц' => 'ts', 'щ' => 'shch',
			));

			return strtr((string) $value, $map);
		}

		protected static function transliterationMap()
		{
			return array(
				'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
				'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I',
				'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
				'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
				'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH',
				'Ш' => 'SH', 'Щ' => 'CSH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
				'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA',
				'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
				'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
				'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
				'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
				'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
				'ш' => 'sh', 'щ' => 'csh', 'ъ' => '', 'ы' => 'y', 'ь' => '',
				'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
			);
		}

		public static function language()
		{
			if (defined('ACP') && ACP && !empty($_SESSION['admin_language'])) {
				return strtolower((string) $_SESSION['admin_language']);
			}

			return defined('PUBLIC_LANGUAGE') ? strtolower((string) PUBLIC_LANGUAGE) : 'ru';
		}

		public static function set()
		{
			$locale = self::language();
			if ($locale === 'ru') {
				@setlocale(LC_ALL, 'ru_RU.UTF-8', 'rus_RUS.UTF-8', 'russian');
				@setlocale(LC_NUMERIC, 'C');
				return;
			}

			@setlocale(LC_ALL, $locale . '_' . strtoupper($locale), $locale, '');
		}

		public static function translateDate($value)
		{
			if (self::language() !== 'ru') {
				return (string) $value;
			}

			return strtr((string) $value, array(
				'January' => 'Января', 'February' => 'Февраля', 'March' => 'Марта',
				'April' => 'Апреля', 'May' => 'Мая', 'June' => 'Июня',
				'July' => 'Июля', 'August' => 'Августа', 'September' => 'Сентября',
				'October' => 'Октября', 'November' => 'Ноября', 'December' => 'Декабря',
				'Monday' => 'Понедельник', 'Tuesday' => 'Вторник', 'Wednesday' => 'Среда',
				'Thursday' => 'Четверг', 'Friday' => 'Пятница', 'Saturday' => 'Суббота',
				'Sunday' => 'Воскресенье',
				'Jan' => 'Янв', 'Feb' => 'Фев', 'Mar' => 'Мрт', 'Apr' => 'Апр',
				'Jun' => 'Июн', 'Jul' => 'Июл', 'Aug' => 'Авг', 'Sep' => 'Сен',
				'Oct' => 'Окт', 'Nov' => 'Нбр', 'Dec' => 'Дек',
				'Mon' => 'Пн', 'Tue' => 'Вт', 'Wed' => 'Ср', 'Thu' => 'Чт',
				'Fri' => 'Пт', 'Sat' => 'Сб', 'Sun' => 'Вс',
			));
		}

		public static function prettyDate($value)
		{
			$value = (string) $value;
			if (!mb_check_encoding($value, 'UTF-8')) {
				$value = (string) iconv('Windows-1251', 'UTF-8', $value);
			}

			if (self::language() !== 'ru') {
				return $value;
			}

			return strtr($value, array(
				'Январь' => 'января', 'Февраль' => 'февраля', 'Март' => 'марта',
				'Апрель' => 'апреля', 'Май' => 'мая', 'Июнь' => 'июня',
				'Июль' => 'июля', 'Август' => 'августа', 'Сентябрь' => 'сентября',
				'Октябрь' => 'октября', 'Ноябрь' => 'ноября', 'Декабрь' => 'декабря',
				'воскресенье' => 'Воскресенье', 'понедельник' => 'Понедельник',
				'вторник' => 'Вторник', 'среда' => 'Среда', 'четверг' => 'Четверг',
				'пятница' => 'Пятница', 'суббота' => 'Суббота',
			));
		}

		public static function humanDate($timestamp)
		{
			$difference = max(0, time() - (int) $timestamp);
			$titles = array(
				array('секунда', 'секунды', 'секунд'),
				array('минута', 'минуты', 'минут'),
				array('час', 'часа', 'часов'),
				array('день', 'дня', 'дней'),
				array('неделя', 'недели', 'недель'),
				array('месяц', 'месяца', 'месяцев'),
				array('год', 'года', 'лет'),
				array('десятилетие', 'десятилетия', 'десятилетий'),
			);
			$lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600);
			$index = count($lengths) - 1;
			while ($index >= 0 && ($number = $difference / $lengths[$index]) <= 1) {
				$index--;
			}

			if ($index < 0) {
				$index = 0;
				$number = $difference;
			}

			$number = (int) floor($number);
			return sprintf('%d %s назад', $number, self::datePhrase($number, $titles[$index]));
		}

		public static function datePhrase($number, array $titles)
		{
			$cases = array(2, 0, 1, 1, 1, 2);
			$index = ($number % 100 > 4 && $number % 100 < 20)
				? 2
				: $cases[min($number % 10, 5)];
			return $titles[$index];
		}
	}
