<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Date.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use DateTime;
	use App\Common\Exceptions;

	/**
	 * Класс Date предоставляет методы для работы с датами и временем.
	 */
	class Date
	{
		protected static $_timeFormat = 'Y-m-d H:i:s';
		protected static $_dateFormat = 'Y-m-d';

		/**
		 * Текущая дата/время в SQL-формате.
		 *
		 * @return string
		 */
		public static function nowSql ()
		{
			return date(self::$_timeFormat);
		}

		/**
		 * Текущий Unix timestamp.
		 *
		 * @return int
		 */
		public static function nowTimestamp ()
		{
			return time();
		}

		/**
		 * Преобразовать значение HTML datetime-local в SQL DATETIME.
		 *
		 * @param string $value Значение вида YYYY-MM-DDTHH:MM или SQL DATETIME.
		 * @return string|null
		 */
		public static function htmlDateTimeToSql ($value)
		{
			$value = trim((string) $value);
			if ($value === '')
			{
				return null;
			}

			$value = str_replace('T', ' ', $value);
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value))
			{
				$value .= ':00';
			}

			return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)
				? $value
				: null;
		}

		/**
		 * Отформатировать timestamp.
		 *
		 * @param int    $timestamp
		 * @param string $format
		 * @return string
		 */
		public static function formatTimestamp ($timestamp, $format = 'Y-m-d H:i:s')
		{
			return date($format, (int) $timestamp);
		}


		/**
		 * Преобразует строку даты в метку времени (timestamp).
		 *
		 * @param string $sDate Дата в формате Y-m-d или любой формат, распознаваемый strtotime.
		 * @return int Метка времени Unix.
		 *
		 * @example
		 * // Возвращает timestamp для 2025-10-17
		 * $ts = Date::datetime2timestamp('2025-10-17');
		 */
		public static function datetime2timestamp ($sDate)
		{
			if (defined('DATE_FORMAT'))
			{
				$DateTime = DateTime::createFromFormat(self::$_dateFormat, $sDate);

				return $DateTime->getTimestamp();
			}

			return strtotime($sDate);
		}


		/**
		 * Преобразует строку даты в метку времени.
		 *
		 * @param string $date Дата в любом формате, распознаваемом strtotime.
		 * @return int Метка времени Unix.
		 *
		 * @example
		 * $ts = Date::dateToTimestamp('10/17/2025');
		 */
		public static function dateToTimestamp ($date)
		{
			return strtotime($date);
		}


		/**
		 * Преобразует строку SQL datetime в метку времени.
		 *
		 * @param string $DateTime Строка вида 'YYYY-MM-DD HH:MM:SS'.
		 * @return int Метка времени Unix.
		 *
		 * @example
		 * $ts = Date::sqlTimeToTimestamp('2025-10-17 12:34:56');
		 */
		public static function sqlTimeToTimestamp ($DateTime)
		{
			[$date, $time] = explode(' ', $DateTime);
			[$year, $month, $day] = explode('-', $date);
			[$hour, $minute, $second] = explode(':', $time);

			return mktime($hour, $minute, $second, $month, $day, $year);
		}


		/**
		 * Преобразует строку SQL date в метку времени (00:00:00).
		 *
		 * @param string $Date Строка вида 'YYYY-MM-DD'.
		 * @return int Метка времени Unix.
		 *
		 * @example
		 * $ts = Date::sqlDateToTimestamp('2025-10-17');
		 */
		public static function sqlDateToTimestamp ($Date)
		{
			[$year, $month, $day] = explode('-', $Date);

			return mktime(0, 0, 0, $month, $day, $year);
		}


		/**
		 * Преобразует метку времени в строку формата Y-m-d H:i:s.
		 *
		 * @param int $timestamp Метка времени Unix.
		 * @return string Форматированная дата и время.
		 *
		 * @example
		 * $str = Date::timestampToTime(time());
		 */


		/**
		 * Преобразует метку времени в строку формата Y-m-d.
		 *
		 * @param int $timestamp Метка времени Unix.
		 * @return string Форматированная дата.
		 *
		 * @example
		 * $str = Date::timestampToDate(time());
		 */
		public static function timestampToDate ($timestamp)
		{
			return date(self::$_dateFormat, $timestamp);
		}


		/**
		 * Возвращает строку вида «X секунд/минут/... назад» для разницы между текущим временем и переданной меткой.
		 *
		 * @param int $date Метка времени Unix, от которой считаем время.
		 * @return string Человекочитаемое представление времени.
		 * @deprecated Используйте Locales::humanDate().
		 *
		 * @example
		 * echo Date::humanDate(time() - 3600); // «1 час назад»
		 */
		public static function humanDate ($date)
		{
			return Locales::humanDate($date);
		}

		/**
		 * @example
		 * $str = Date::timestampToTime(time());
		 */
		public static function timestampToTime ($timestamp)
		{
			return date(self::$_timeFormat, $timestamp);
		}


		/**
		 * Возвращает правильную форму слова в зависимости от числа.
		 *
		 * @param int   $number Число.
		 * @param array $titles Массив с 3 вариантами формы слова.
		 * @return string Правильная форма слова.
		 * @deprecated Используйте Locales::datePhrase().
		 */
		public static function datePhrase ($number, $titles)
		{
			return Locales::datePhrase($number, $titles);
		}


		/**
		 * Создаёт метку времени из строки.
		 *
		 * @param string|null $string Строка даты/времени в различных форматах или null.
		 * @return int Метка времени Unix.
		 *
		 * @example
		 * Date::_make_timestamp('20251017123456'); // 1730000000 (пример)
		 */
		public static function _make_timestamp ($string)
		{
			if (empty($string))
			{
				$time = time();
			}
			elseif (preg_match('/^\\d{14}$/', $string))
			{
				$time = mktime(substr($string, 8, 2),substr($string, 10, 2),substr($string, 12, 2),  substr($string, 4, 2),substr($string, 6, 2),substr($string, 0, 4));
			}
			elseif (is_numeric($string))
			{
				$time = (int)$string;
			}
			else
			{
				$time = strtotime($string);

				if ($time == -1 || $time === false)
				{
					$time = time();
				}
			}

			return $time;
		}
	}
