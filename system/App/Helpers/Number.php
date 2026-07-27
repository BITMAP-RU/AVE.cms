<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Number.php
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

	class Number
	{
		protected function __construct()
		{
			//
		}

		/**
		 * Форматирует размер файла в удобочитаемый вид.
		 *
		 * Преобразует размер в байтах в строку с указанием единиц измерения (Б, Кб, Мб, Гб),
		 * округляя до двух знаков после запятой.
		 *
		 * @param int $size Размер в байтах.
		 * @return string Нормированный размер с единицей измерения.
		 *
		 * @example
		 * <code>
		 * echo Number::formatSize(1024); // 1.00 Kb
		 * echo Number::formatSize(2048576); // 1.95 Mb
		 * </code>
		 */
		public static function formatSize($size)
		{
			if ($size >= 1073741824) {
				$size = round($size / 1073741824 * 100) / 100 . ' Gb';
			} elseif ($size >= 1048576) {
				$size = round($size / 1048576 * 100) / 100 . ' Mb';
			} elseif ($size >= 1024) {
				$size = round($size / 1024 * 100) / 100 . ' Kb';
			} else {
				$size .= ' b';
			}

			return $size;
		}

		/**
		 * Форматирует число.
		 *
		 * Применяет форматирование числа, включая количество десятичных знаков,
		 * разделители дробной части и тысяч.
		 *
		 * @param float|int $number Число для форматирования.
		 * @param int $decimal Количество десятичных знаков (по умолчанию 0).
		 * @param string $after Разделитель десятичных знаков (по умолчанию ',').
		 * @param string $thousand Разделитель тысяч (по умолчанию '.').
		 * @return string Отформатированная строка.
		 *
		 * @example
		 * <code>
		 * echo Number::numFormat(12345.678, 2); // 12.345,68
		 * echo Number::numFormat(12345, 0, ',', ' '); // 12 345
		 * </code>
		 */
		public static function numFormat($number, $decimal = 0, $after = ',', $thousand = '.')
		{
			if ($number) {
				return number_format($number, $decimal, $after, $thousand);
			}

			return '';
		}

		/**
		 * Нормализует число для DECIMAL-полей БД.
		 *
		 * В отличие от numFormat(), всегда возвращает значение с точкой и не скрывает ноль.
		 *
		 * @param float|int|string $number Число.
		 * @param int $scale Количество знаков после точки.
		 * @return string Нормализованная DECIMAL-строка.
		 */
		public static function decimal($number, $scale = 2)
		{
			return number_format((float) $number, (int) $scale, '.', '');
		}

		/**
		 * Вычисляет разницу между двумя метками времени microtime().
		 *
		 * @param string $a Начальная метка времени, полученная функцией microtime().
		 * @param string $b Конечная метка времени, полученная функцией microtime().
		 * @return float Разница между метками времени в секундах.
		 *
		 * @example
		 * <code>
		 * $start = microtime();
		 * // ... ваш код ...
		 * $end = microtime();
		 * $diff = Number::microtimeDiff($start, $end);
		 * echo "Время выполнения: {$diff} секунд.";
		 * </code>
		 */
		public static function microtimeDiff($a, $b)
		{
			[$a_dec, $a_sec] = explode(' ', $a);
			[$b_dec, $b_sec] = explode(' ', $b);
			return $b_sec - $a_sec + $b_dec - $a_dec;
		}

		/**
		 * Переводит числовое значение в слова (на английском).
		 *
		 * Конвертирует целое число в его словесное представление на английском языке.
		 * Поддерживает числа до тысячи.
		 *
		 * @param int $number Число для конвертации.
		 * @return string|false Число прописью (на английском) или false при ошибке.
		 */
		public static function numberToWords($number)
		{
			$words = [
				'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
				'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
				'seventeen', 'eighteen', 'nineteen',
				20 => 'twenty', 30 => 'thirty', 40 => 'fourty', 50 => 'fifty',
				60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety',
				100 => 'hundred', 1000 => 'thousand'
			];

			$number_in_words = '';

			if (is_numeric($number)) {
				$number = (int)round($number);

				if ($number < 0) {
					$number = -$number;
					$number_in_words = 'minus ';
				}

				if ($number > 1000) {
					$number_in_words .= self::numberToWords(floor($number / 1000)) . " " . $words[1000];
					$hundreds = $number % 1000;
					$tens = $hundreds % 100;

					if ($hundreds > 100) {
						$number_in_words .= ", " . self::numberToWords($hundreds);
					} elseif ($tens) {
						$number_in_words .= " and " . self::numberToWords($tens);
					}
				} elseif ($number > 100) {
					$number_in_words .= self::numberToWords(floor($number / 100)) . " " . $words[100];
					$tens = $number % 100;
					if ($tens) {
						$number_in_words .= " and " . self::numberToWords($tens);
					}
				} elseif ($number > 20) {
					$number_in_words .= " " . $words[10 * floor($number / 10)];
					$units = $number % 10;
					if ($units) {
						$number_in_words .= self::numberToWords($units);
					}
				} else {
					$number_in_words .= " " . $words[$number];
				}

				return trim($number_in_words);
			}

			return false;
		}

		/**
		 * Генерирует случайное число заданной длины.
		 *
		 * Генерирует целое число, количество знаков в котором соответствует параметру $digits.
		 *
		 * @param int $digits Количество знаков в генерируемом числе (по умолчанию 1).
		 * @return int Случайное число.
		 *
		 * @example
		 * <code>
		 * $random_3_digit = Number::randomNumber(3); // Например, 542
		 * </code>
		 */
		public static function randomNumber($digits = 1)
		{
			$min = 10 ** ($digits - 1);
			$max = (10 ** $digits) - 1;
			return random_int($min, $max);
		}

		/**
		 * Сравнивает два числа с плавающей точкой с заданной точностью.
		 *
		 * Метод позволяет корректно сравнивать числа с плавающей точкой,
		 * учитывая погрешность вычислений, используя эпсилон-окрестность.
		 *
		 * @param float $float1 Первое число.
		 * @param float $float2 Второе число.
		 * @param string $operator Оператор сравнения ('=', 'eq', '<', 'lt', '<=', 'lte', '>', 'gt', '>=', 'gte', '!=', 'ne').
		 * @return bool Результат сравнения.
		 *
		 * @example
		 * <code>
		 * Number::compareNumbers(10.000001, 10.000002, '='); // true
		 * Number::compareNumbers(5.1, 5.2, '<'); // true
		 * </code>
		 */
		public static function compareNumbers($float1, $float2, $operator = '=')
		{
			$epsilon = 0.00001;
			$float1 = (float)$float1;
			$float2 = (float)$float2;

			switch ($operator) {
				case "=":
				case "eq":
					if (abs($float1 - $float2) < $epsilon) {
						return true;
					}

					break;
				case "<":
				case "lt":
					if (abs($float1 - $float2) < $epsilon) {
						return false;
					}

					if ($float1 < $float2) {
						return true;
					}

					break;
				case "<=":
				case "lte":
					if (self::compareNumbers($float1, $float2, '<') || self::compareNumbers($float1, $float2, '=')) {
						return true;
					}

					break;
				case ">":
				case "gt":
					if (abs($float1 - $float2) < $epsilon) {
						return false;
					}

					if ($float1 > $float2) {
						return true;
					}

					break;
				case ">=":
				case "gte":
					if (self::compareNumbers($float1, $float2, '>') || self::compareNumbers($float1, $float2, '=')) {
						return true;
					}

					break;
				case "<>":
				case "!=":
				case "ne":
					if (abs($float1 - $float2) > $epsilon) {
						return true;
					}

					break;
				default:
					die("Неизвестный оператор '" . $operator . "' в функции compareNumbers()");
			}

			return false;
		}

		/**
		 * Форматирует цену в читаемый вид.
		 *
		 * Форматирует числовое значение цены согласно заданным правилам форматирования цен,
		 * определенным в настройках (Settings::get('priceFormat')) или переданным в $type.
		 *
		 * @param float|string $string Цена.
		 * @param string|null $type Тип форматирования цены (переопределяет настройки по умолчанию).
		 * @return string Отформатированная строка с ценой.
		 */
		public static function numberFormat($string, $type = null)
		{
			$result = $string;
			$priceFormat = Settings::get('priceFormat');

			if ($type) {
				$priceFormat = $type;
			}

			switch ($priceFormat) {
				case '1234.56':
					$result = $string;
				break;
				case '1 234,56':
					$result = number_format($string, 2, ',', ' ');
				break;
				case '1,234.56':
					$result = number_format($string, 2, '.', ',');
				break;
				case '1234':
					$result = round($string);
				break;
				case '1 234':
					$result = number_format(round($string), 0, ',', ' ');
				break;
				case '1,234':
					$result = $string; // Это, вероятно, ошибка в оригинальном коде, должен быть number_format
				break;
				default: // '1234.56' без округления
					$result = number_format(round($string), 0, ',', ' '); // Здесь также без копеек
				break;
			}

			$cent = substr($result, -3);
			if ($cent === '.00' || $cent === ',00') {
				$result = substr($result, 0, -3);
			}

			return $result;
		}

		/**
		 * Де-форматирует цену из строки в число.
		 *
		 * Преобразует строковое представление цены, удаляя разделители тысяч и
		 * десятичных знаков, чтобы получить числовое значение.
		 *
		 * @param string $string Строка с форматированной ценой.
		 * @return string Строка с ценой без форматирования.
		 */
		public static function numberDeFormat($string)
		{
			$result = $string;
			$cent = false;
			$thousand = false;

			$existpoint = strrpos($string, '.');
			$existcomma = strrpos($string, ',');

			if ($existpoint && $existcomma) {
				$result = str_replace([' ', ','], ['', '.'], $string);
				$firstpoint = strpos($result, '.');
				$lastpoint = strrpos($result, '.');

				if ($firstpoint != $lastpoint) {
					$str1 = substr($result, 0, $lastpoint);
					$str2 = substr($result, $lastpoint);
					$str1 = str_replace('.', '', $str1);
					$result = $str1 . $str2;
				}

				return $result;
			}

			if (!$existpoint && $existcomma) {
				$str2 = substr($string, $existcomma);
				if (strlen($str2) - 1 == 2) {
					$cent = true;
				} else {
					$thousand = true;
				}
			}

			if ($thousand) {
				$result = str_replace(',', '', $string);
			}

			if ($cent) {
				$result = str_replace(',', '.', $string);
				$firstpoint = strpos($result, '.');
				$lastpoint = strrpos($result, '.');

				if ($firstpoint != $lastpoint) {
					$str1 = substr($result, 0, $lastpoint);
					$str2 = substr($result, $lastpoint);
					$str1 = str_replace('.', '', $str1);
					$result = $str1 . $str2;
				}
			}

			return str_replace(' ', '', $result);
		}

		/**
		 * Форматирует цену с учетом настроек валютного курса.
		 *
		 * Применяет форматирование к цене, опционально округляя до целых и используя
		 * `numberFormat` для получения читаемого вида.
		 *
		 * @param float $price Цена.
		 * @param bool $format Нужно ли форматировать число.
		 * @param bool|null $float Если false, округляет до целых перед форматированием.
		 * @return string Отформатированная строка с ценой.
		 *
		 * @example
		 * <code>
		 * // Цена без форматирования, округленная до целых
		 * echo Number::priceCourse(1234.56, false, false); // 1235
		 * </code>
		 */
		public static function priceCourse($price, $format = true, $float = null)
		{
			if ($float === false) {
				$price = round($price);
			}

			if ($format) {
				$price = self::numberFormat($price);
			}

			return $price;
		}

		/**
		 * Склонение числительных (для русского языка).
		 *
		 * Выбирает правильную форму существительного в зависимости от числа.
		 *
		 * @param int $number Число.
		 * @param array $titles Массив из трех вариантов склонений существительного
		 *                      (например, `['товар', 'товара', 'товаров']`).
		 * @return string Строка с числом и правильной формой существительного.
		 *
		 * @example
		 * <code>
		 * echo 'Найдено ' . Number::declensionNum(5, ['товар', 'товара', 'товаров']); // Найдено 5 товаров
		 * echo 'Найдено ' . Number::declensionNum(1, ['товар', 'товара', 'товаров']); // Найдено 1 товар
		 * </code>
		 */
		public static function declensionNum($number, $titles)
		{
			$cases = [2, 0, 1, 1, 1, 2];
			return $number . ' ' . $titles[($number % 100 > 4 && $number % 100 < 20)
				? 2
				: $cases[min($number % 10, 5)]];
		}

		/**
		 * Форматирует размер в удобочитаемый текстовый вид.
		 *
		 * Преобразует размер в байтах в строку с единицами измерения (Б, Кб, Мб, Гб)
		 * с двумя знаками после запятой.
		 *
		 * @param int $size Размер в байтах.
		 * @return string Форматированная строка размера.
		 *
		 * @example
		 * <code>
		 * echo Number::getTextSize(500); // 500 b
		 * echo Number::getTextSize(1500); // 1.46 Kb
		 * echo Number::getTextSize(1500000); // 1.43 Mb
		 * </code>
		 */
		public static function getTextSize($size)
		{
			if ($size >= 1024) {
				$textSize = 'kb';
				$size /= 1024;

				if ($size >= 1024) {
					$textSize = 'mb';
					$size /= 1024;

					if ($size >= 1024) {
						$textSize = 'gb';
						$size /= 1024;
					}
				}

				$size = sprintf('%.2f', $size);
			} else {
				$textSize = 'b';
			}

			return $size . ' ' . $textSize;
		}

		protected function __clone()
		{
			//
		}

		protected function __wakeup()
		{
			//
		}
	}
