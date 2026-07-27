<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Secure.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	if (! defined("BASEPATH"))
	{
		die ('Direct access to this location is not allowed.');
	}




	class Secure
	{
		/**
		 * Конструктор класса Secure
		 *
		 * @return void
		 */
		protected function __construct()
		{
			//--
		}


		/**
		 * Декодирует HTML-сущности и убирает слеши из строки
		 *
		 * Метод используется для очистки строк от HTML-сущностей и лишних слэшей,
		 * что помогает предотвратить возможные уязвимости при обработке данных.
		 *
		 * @param string $string Входная строка для очистки
		 * @return string Очищенная строка
		 *
		 * @example
		 * <code>
		 * Secure::cleanOut('<script>alert("XSS")</script>');
		 * // Результат: '<script>alert("XSS")</script>'
		 * </code>
		 */
		public static function cleanOut ($string)
		{
			$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

			return stripslashes($string);
		}


		/**
		 * Очищает строку от потенциально опасных символов
		 *
		 * Метод применяет фильтрацию строки для удаления HTML-тегов,
		 * специальных символов и других потенциально опасных элементов.
		 * Может использоваться для очистки пользовательского ввода перед сохранением в базу данных.
		 *
		 * @param string $string Входная строка для очистки
		 * @param bool|int $trim Флаг обрезки строки или максимальная длина строки
		 * @return string Очищенная строка
		 *
		 * @example
		 * <code>
		 * Secure::sanitize('<script>alert("XSS")</script>', true);
		 * // Результат: 'alertXSS'
		 * </code>
		 */
		public static function sanitize ($string, $trim = false)
		{
			$string = filter_var($string, FILTER_SANITIZE_STRING);
			$string = trim($string);
			$string = stripslashes($string);
			$string = strip_tags($string);
			$string = str_replace(
				[
					'‘',
					'’',
					'“',
					'”'
				],
				[
					"'",
					"'",
					'"',
					'"'
				],
				$string
			);

			if ($trim)
			{
				$string = substr($string, 0, $trim);
			}

			if (is_numeric($string))
			{
				$string = filter_var($string, FILTER_VALIDATE_INT);
			}

			return $string;
		}


		/**
		 * Очищает строку и обрезает её при необходимости
		 *
		 * Метод объединяет функции cleanOut и sanitize с возможностью обрезки строки
		 * до заданной длины с добавлением символа окончания.
		 * Используется для подготовки текстовых данных к отображению с ограничением длины.
		 *
		 * @param string $string Входная строка для очистки
		 * @param bool|int $trim Максимальная длина строки или флаг обрезки
		 * @param string $end_char Символ, добавляемый в конце обрезанной строки
		 * @return string Очищенная и при необходимости обрезанная строка
		 *
		 * @example
		 * <code>
		 * Secure::cleanSanitize('Very long text that needs to be truncated', 10, '...');
		 * // Результат: 'Very long...'
		 * </code>
		 */
		public static function cleanSanitize ($string, $trim = false, $end_char = '&#8230;')
		{
			$string = self::cleanOut($string);
			$string = filter_var($string, FILTER_SANITIZE_STRING);
			$string = trim($string);
			$string = stripslashes($string);
			$string = strip_tags($string);
			$string = str_replace(
				[
					'‘',
					'’',
					'“',
					'”'],
				[
					"'",
					"'",
					'"',
					'"'
				],
			$string);

			if ($trim)
			{
				if (strlen($string) < $trim)
				{
					return $string;
				}

				$string = preg_replace("/\s+/", ' ', str_replace(array(
					"\r\n",
					"\r",
					"\n"), ' ', $string));

				if (strlen($string) <= $trim)
				{
					return $string;
				}

				$out = '';

				foreach (explode(' ', trim($string)) as $val)
				{
					$out .= $val . ' ';

					if (strlen($out) >= $trim)
					{
						$out = trim($out);

						return (strlen($out) == strlen($string))
							? $out
							: $out . $end_char;
					}
				}
			}

			return $string;
		}


		/**
		 * Защищает массив от XSS-атак
		 *
		 * Метод применяет защиту от XSS для всех элементов массива,
		 * рекурсивно обрабатывая вложенные массивы. Используется для
		 * безопасной обработки пользовательских данных из GET/POST запросов.
		 *
		 * @param array $array Массив параметров для защиты
		 * @param bool $stripslashes Флаг удаления слэшей из строк
		 * @return array Защищённый массив параметров
		 *
		 * @example
		 * <code>
		 * $data = ['name' => '<script>alert("XSS")</script>', 'email' => 'test@example.com'];
		 * Secure::arrayXss($data);
		 * // Результат: ['name' => '<script>alert("XSS")</script>', 'email' => 'test@example.com']
		 * </code>
		 */
		public static function arrayXss ($array, $stripslashes = false)
		{
			$filter = array('<', '>');

			foreach ($array as $key => $xss)
			{
				if (is_array($xss))
				{
					$array[$key] = self::arrayXss($xss, $stripslashes);
				}
				else
				{
					if ($stripslashes)
						$xss = stripslashes($xss);

					$xss = htmlspecialchars_decode($xss);

					$xss = str_replace('"', '&quot;', $xss);

					$array[$key] = str_replace($filter, array('&lt;', '&gt;'), trim($xss));
				}
			}

			return $array;
		}


		/**
		 * Восстанавливает строку после защиты от XSS
		 *
		 * Метод восстанавливает оригинальные символы, которые были заменены
		 * при защите от XSS-атак. Используется для отображения данных,
		 * которые были ранее защищены методом arrayXss().
		 *
		 * @param string $string Входная строка для восстановления
		 * @return string Восстановленная строка
		 *
		 * @example
		 * <code>
		 * $safe_string = Secure::arrayXss(['name' => '<script>alert("XSS")</script>']);
		 * Secure::decodeXss($safe_string['name']);
		 * // Результат: '<script>alert("XSS")</script>'
		 * </code>
		 */
		public static function decodeXss ($string)
		{
			return str_replace(['&lt;', '&gt;', '&quot;'], ['<', '>', '"'], trim($string));
		}


		/**
		 * Очищает и нормализует номер телефона
		 *
		 * Метод удаляет все символы, кроме цифр, из номера телефона,
		 * а также обрабатывает международные и российские форматы номеров.
		 * Возвращает последние 10 цифр номера телефона.
		 *
		 * @param string $phone Номер телефона для очистки
		 * @return string Очищенный номер телефона (последние 10 цифр)
		 *
		 * @example
		 * <code>
		 * Secure::sanitizePhoneNumber('+7 (999) 123-45-67');
		 * // Результат: '9991234567'
		 * </code>
		 */
		public static function sanitizePhoneNumber($phone)
		{
			$phone = preg_replace('/^\+\s*[0-9]{1,4}\s+/', '', $phone);
			$phone = preg_replace('/^8\s{1,}\(/', '', $phone);
			$phone = preg_replace('/[^0-9]/', '', $phone);

			return substr($phone, -10);
		}


		protected function __clone ()
		{
			//
		}


		protected function __wakeup ()
		{
			//
		}
	}
