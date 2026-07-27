<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Str.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Хелпер для работы со строками (UTF-8).
	 * Все методы работают с многобайтовыми строками через mbstring.
	 */
	class Str
	{
		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  Базовые операции
		// ------------------------------------------------------------------ //

		public static function length($str)
		{
			return mb_strlen((string)$str, 'UTF-8');
		}

		public static function upper($str)
		{
			return mb_strtoupper((string)$str, 'UTF-8');
		}

		public static function lower($str)
		{
			return mb_strtolower((string)$str, 'UTF-8');
		}

		/** Первый символ в верхний регистр (UTF-8) */
		public static function ucfirst($str)
		{
			$str = (string)$str;
			if ($str === '') return '';
			return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($str, 1, null, 'UTF-8');
		}

		/** Каждое слово с большой буквы */
		public static function title($str)
		{
			return mb_convert_case((string)$str, MB_CASE_TITLE, 'UTF-8');
		}

		public static function trim($str, $characters = null)
		{
			return $characters !== null ? trim((string)$str, $characters) : trim((string)$str);
		}

		/** Вернуть trimmed string или NULL, если строка пустая */
		public static function nullIfEmpty($str)
		{
			$str = trim((string)$str);
			return $str === '' ? null : $str;
		}

		public static function ltrim($str, $characters = null)
		{
			return $characters !== null ? ltrim((string)$str, $characters) : ltrim((string)$str);
		}

		public static function rtrim($str, $characters = null)
		{
			return $characters !== null ? rtrim((string)$str, $characters) : rtrim((string)$str);
		}

		public static function substr($str, $start, $length = null)
		{
			return $length !== null
				? mb_substr((string)$str, $start, $length, 'UTF-8')
				: mb_substr((string)$str, $start, null, 'UTF-8');
		}

		public static function pos($haystack, $needle, $offset = 0)
		{
			return mb_strpos((string)$haystack, (string)$needle, $offset, 'UTF-8');
		}

		public static function replace($search, $replace, $subject)
		{
			return str_replace($search, $replace, $subject);
		}

		public static function pad($str, $length, $pad = ' ', $type = STR_PAD_RIGHT)
		{
			return str_pad((string)$str, $length, $pad, $type);
		}

		public static function repeat($str, $times)
		{
			return str_repeat((string)$str, max(0, (int)$times));
		}

		// ------------------------------------------------------------------ //
		//  Поиск и проверки
		// ------------------------------------------------------------------ //

		/** Содержит ли строка подстроку */
		public static function contains($haystack, $needle)
		{
			return mb_strpos((string)$haystack, (string)$needle, 0, 'UTF-8') !== false;
		}

		/** Начинается ли строка с prefix */
		public static function startsWith($str, $prefix)
		{
			$prefix = (string)$prefix;
			return $prefix !== '' && mb_substr((string)$str, 0, mb_strlen($prefix, 'UTF-8'), 'UTF-8') === $prefix;
		}

		/** Заканчивается ли строка на suffix */
		public static function endsWith($str, $suffix)
		{
			$suffix = (string)$suffix;
			if ($suffix === '') return true;
			$str = (string)$str;
			return mb_substr($str, -mb_strlen($suffix, 'UTF-8'), null, 'UTF-8') === $suffix;
		}

		/** Соответствует ли строка паттерну (wildcards: * и ?) */
		public static function is($pattern, $value)
		{
			$pattern = preg_quote((string)$pattern, '#');
			$pattern = str_replace(['\*', '\?'], ['.*', '.'], $pattern);
			return (bool)preg_match('#^' . $pattern . '$#u', (string)$value);
		}

		// ------------------------------------------------------------------ //
		//  Трансформации
		// ------------------------------------------------------------------ //

		/**
		 * Ограничить строку по количеству символов.
		 * Добавляет суффикс если строка обрезана.
		 */
		public static function limit($str, $limit = 100, $end = '...')
		{
			$str = (string)$str;
			if (mb_strlen($str, 'UTF-8') <= $limit) {
				return $str;
			}

			return mb_substr($str, 0, $limit, 'UTF-8') . $end;
		}

		/**
		 * Ограничить строку по количеству слов.
		 */
		public static function words($str, $words = 10, $end = '...')
		{
			$str  = (string)$str;
			$wArr = preg_split('/\s+/u', trim($str), -1, PREG_SPLIT_NO_EMPTY);

			if (count($wArr) <= $words) {
				return $str;
			}

			return implode(' ', array_slice($wArr, 0, $words)) . $end;
		}

		/** @deprecated Используй limit() */
		public static function truncate($str, $length, $suffix = '...')
		{
			return self::limit($str, $length, $suffix);
		}

		/** Ограничить строку с сохранением целых слов. */
		public static function truncateSmart($string, $length = 80, $suffix = '...', $breakWords = false, $middle = false)
		{
			$string = (string) $string;
			$length = max(0, (int) $length);
			if ($length === 0) { return ''; }
			if (mb_strlen($string, 'UTF-8') <= $length) { return $string; }

			$contentLength = $length - min($length, mb_strlen($suffix, 'UTF-8'));
			if ($middle) {
				$left = (int) floor($contentLength / 2);
				$right = $contentLength - $left;
				return mb_substr($string, 0, $left, 'UTF-8')
					. $suffix
					. mb_substr($string, -$right, null, 'UTF-8');
			}

			$value = mb_substr($string, 0, $contentLength + 1, 'UTF-8');
			if (!$breakWords) {
				$value = preg_replace('/\s+?(\S+)?$/u', '', $value);
			}

			return mb_substr($value, 0, $contentLength, 'UTF-8') . $suffix;
		}

		/** Ограничить текст на первом полном слове после заданной длины. */
		public static function truncateText($string, $length = 100, $suffix = '&#8230;')
		{
			$string = (string) $string;
			$length = max(0, (int) $length);
			if (mb_strlen($string, 'UTF-8') <= $length) { return $string; }

			$normalized = preg_replace('/\s+/u', ' ', str_replace(array("\r\n", "\r", "\n"), ' ', $string));
			$out = '';
			foreach (preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) as $word) {
				$out .= ($out === '' ? '' : ' ') . $word;
				if (mb_strlen($out, 'UTF-8') >= $length) {
					return mb_strlen($out, 'UTF-8') === mb_strlen($normalized, 'UTF-8') ? $out : $out . $suffix;
				}
			}

			return $normalized;
		}

		/**
		 * Маскировать часть строки.
		 *
		 * @param  string $str
		 * @param  int    $start   Позиция начала маски (отрицательные — от конца)
		 * @param  int    $length  Длина маски (0 = до конца)
		 * @param  string $char    Символ маски
		 */
		public static function mask($str, $start, $length = 0, $char = '*')
		{
			$str      = (string)$str;
			$total    = mb_strlen($str, 'UTF-8');
			$start    = $start < 0 ? max(0, $total + $start) : min($start, $total);
			$length   = $length > 0 ? min($length, $total - $start) : $total - $start;

			return mb_substr($str, 0, $start, 'UTF-8')
				. str_repeat($char, $length)
				. mb_substr($str, $start + $length, null, 'UTF-8');
		}

		/**
		 * Маскировать email: jo***@example.com
		 */
		public static function maskEmail($email)
		{
			$parts = explode('@', (string)$email, 2);
			if (count($parts) !== 2) return $email;

			$name = $parts[0];
			$len  = mb_strlen($name, 'UTF-8');

			if ($len <= 2) {
				$masked = str_repeat('*', $len);
			} else {
				$visible = max(1, (int)floor($len / 3));
				$masked  = mb_substr($name, 0, $visible, 'UTF-8') . str_repeat('*', $len - $visible);
			}

			return $masked . '@' . $parts[1];
		}

		// ------------------------------------------------------------------ //
		//  Именование
		// ------------------------------------------------------------------ //

		/**
		 * snake_case → camelCase
		 */
		public static function camel($str)
		{
			return lcfirst(static::studly($str));
		}

		/**
		 * snake_case → StudlyCase (PascalCase)
		 */
		public static function studly($str)
		{
			$str = (string)$str;
			return str_replace([' ', '_', '-'], '', mb_convert_case(
				str_replace(['-', '_'], ' ', $str),
				MB_CASE_TITLE,
				'UTF-8'
			));
		}

		/**
		 * camelCase / StudlyCase → snake_case
		 */
		public static function snake($str, $delimiter = '_')
		{
			$str = (string)$str;
			if (!ctype_lower($str)) {
				$str = preg_replace('/\s+/u', '', $str);
				$str = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $str);
				$str = mb_strtolower($str, 'UTF-8');
			}

			return $str;
		}

		/**
		 * Текст → URL-slug (кириллица транслитерируется).
		 * «Моя Строка» → «moya-stroka»
		 */
		public static function slug($str, $separator = '-')
		{
			$str = Locales::transliterateSlug((string) $str);

			// Привести к нижнему регистру и оставить только допустимые символы
			$str = mb_strtolower($str, 'UTF-8');
			$str = preg_replace('/[^a-z0-9\s\-_]/u', '', $str);
			$str = preg_replace('/[\s\-_]+/', $separator, $str);
			return trim($str, $separator);
		}

		/**
		 * Машинный alias для admin-сущностей: латиница, цифры, _, . и дефисы.
		 * Не транслитерирует смысловые URL, а только чистит технические ключи.
		 */
		public static function machineAlias($str, $separator = '-')
		{
			$str = mb_strtolower(trim((string)$str), 'UTF-8');
			$str = preg_replace('/[^a-z0-9_.-]+/', (string)$separator, $str);
			$str = preg_replace('/' . preg_quote((string)$separator, '/') . '+/', (string)$separator, $str);
			return trim($str, (string)$separator);
		}

		// ------------------------------------------------------------------ //
		//  Генерация
		// ------------------------------------------------------------------ //

		/**
		 * Сгенерировать криптографически стойкую случайную строку.
		 *
		 * @param  int    $length  Длина строки
		 * @param  string $chars   Алфавит (по умолчанию a-z0-9)
		 */
		public static function random($length = 16, $chars = 'abcdefghijklmnopqrstuvwxyz0123456789')
		{
			$result   = '';
			$max      = strlen($chars) - 1;
			$bytesLen = (int)ceil($length * 2);

			$bytes = random_bytes($bytesLen);

			for ($i = 0; $i < $bytesLen && strlen($result) < $length; $i++) {
				$index    = ord($bytes[$i]) % ($max + 1);
				$result  .= $chars[$index];
			}

			return substr($result, 0, $length);
		}

		/**
		 * Сгенерировать UUID v4.
		 */
		public static function uuid()
		{
			$bytes = random_bytes(16);
			$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
			$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
		}

		// ------------------------------------------------------------------ //
		//  Прочее
		// ------------------------------------------------------------------ //

		/** Безопасное сравнение строк (защита от timing-атак) */
		public static function equals($a, $b)
		{
			return hash_equals((string)$a, (string)$b);
		}

		/** SHA-256 hash строки */
		public static function sha256($str)
		{
			return hash('sha256', (string)$str);
		}

		/** Удалить теги и декодировать HTML-сущности */
		public static function stripTags($str, $allowedTags = '')
		{
			return html_entity_decode(strip_tags((string)$str, $allowedTags), ENT_QUOTES, 'UTF-8');
		}

		/** Экранировать для вывода в HTML */
		public static function escape($str)
		{
			return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
