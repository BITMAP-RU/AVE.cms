<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/Crypt.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	class Crypt
	{
		public static $_salt = "wU!9kd4**&2vp||";

		/**
		 * Шифрует строку
		 *
		 * Добавляет к строке "соль" и кодирует результат в Base64.
		 * Этот метод обеспечивает простое обфусцирование, не является криптографически стойким.
		 *
		 * @param string $string Исходная строка для шифрования.
		 * @return string Зашифрованная строка в формате Base64.
		 *
		 * @example
		 * <code>
		 * $encrypted = Crypt::_crypt('my_secret_password');
		 * </code>
		 */
		public static function _crypt($string)
		{
			$data = $string . self::$_salt;
			return base64_encode($data);
		}

		/**
		 * Дешифрует строку
		 *
		 * Декодирует строку из Base64 и удаляет "соль", возвращая исходное значение.
		 *
		 * @param string $string Зашифрованная строка в формате Base64.
		 * @return string Исходная, дешифрованная строка.
		 *
		 * @example
		 * <code>
		 * $decrypted = Crypt::_decrypt($encrypted_string);
		 * </code>
		 */
		public static function _decrypt($string)
		{
			$data = base64_decode($string);
			return str_replace(self::$_salt, '', $data);
		}

		/**
		 * Конвертирует 64-битное целое число в 32-битное
		 *
		 * Выполняет побитовую операцию XOR для преобразования числа, выходящего за пределы 32-битного диапазона.
		 * Используется для обеспечения совместимости хеш-сумм на 32-битных и 64-битных системах.
		 *
		 * @param int $int 64-битное целое число.
		 * @return int 32-битное целое число.
		 *
		 * @example
		 * <code>
		 * $crc = crc32('some string'); // Может вернуть 64-битное число на 64-битной системе
		 * $compatible_crc = Crypt::convert64b32($crc);
		 * </code>
		 */
		public static function convert64b32($int)
		{
			if ($int > 2147483647 || $int < -2147483648) {
				$int ^= 18446744069414584320;
			}

			return $int;
		}

		/**
		 * Вычисляет CRC32 хеш строки
		 *
		 * Вычисляет полиномиальную контрольную сумму CRC32 для строки и конвертирует результат
		 * для совместимости между 32/64-битными системами.
		 *
		 * @param string $value Строка для вычисления хеша.
		 * @return int 32-битное знаковое целое число, представляющее CRC32 хеш.
		 *
		 * @example
		 * <code>
		 * $hash = Crypt::crc32('my string data');
		 * </code>
		 */
		public static function crc32($value)
		{
			return self::convert64b32(crc32($value));
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
