<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldValueCodec.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	/**
	 * Переходный кодек значений документа.
	 *
	 * Скалярные значения не оборачиваются: это сохраняет числовые индексы и
	 * быстрый поиск. Структурные значения хранятся как JSON; чтение старого PHP
	 * serialize остаётся доступным до завершения web-миграции.
	 */
	class FieldValueCodec
	{
		/**
		 * Построить безопасное значение для DECIMAL(18,4) в числовом индексе поля.
		 * Для составного текста берётся первое число: размеры не склеиваются в одно
		 * заведомо ложное и потенциально слишком большое значение.
		 */
		public static function numericIndexValue($value)
		{
			$raw = str_replace(array(',', "\xC2\xA0", "\xE2\x80\xAF"), array('.', ' ', ' '), trim((string) $value));
			if (!preg_match('/[-+]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))/', $raw, $match)) {
				return '0';
			}

			$number = $match[0];
			$negative = substr($number, 0, 1) === '-';
			$number = ltrim($number, '+-');
			$parts = explode('.', $number, 2);
			$integer = ltrim($parts[0], '0');
			$integer = $integer === '' ? '0' : $integer;
			if (strlen($integer) > 14) {
				return '0';
			}

			$fraction = isset($parts[1]) ? substr($parts[1], 0, 4) : '';
			if ($integer === '0' && trim($fraction, '0') === '') {
				return '0';
			}

			return ($negative ? '-' : '') . $integer . ($fraction === '' ? '' : '.' . $fraction);
		}

		public static function normalizeForStorage($value)
		{
			if (is_array($value)) {
				return self::encode($value);
			}

			$raw = (string) $value;
			$decoded = self::decodeStructured($raw, null);
			return is_array($decoded) ? self::encode($decoded) : $raw;
		}

		public static function decodeStructured($value, $default = array())
		{
			if (is_array($value)) {
				return $value;
			}

			$raw = trim((string) $value);
			if ($raw === '') {
				return $default;
			}

			$first = substr($raw, 0, 1);
			if ($first === '[' || $first === '{') {
				try {
					$json = Json::decode($raw);
				} catch (\RuntimeException $e) {
					$json = null;
				}

				if (is_array($json)) {
					return $json;
				}
			}

			if (preg_match('/^(a|s|i|d|b|N):/', $raw)) {
				$legacy = @unserialize($raw, array('allowed_classes' => false));
				if (is_array($legacy)) {
					return $legacy;
				}
			}

			return $default;
		}

		public static function templateParts($value)
		{
			$structured = self::decodeStructured($value, null);
			if (!is_array($structured)) {
				return explode('|', (string) $value);
			}

			$parts = array();
			array_walk_recursive($structured, function ($item) use (&$parts) {
				if (is_scalar($item) || $item === null) {
					$parts[] = (string) $item;
				}
			});
			return $parts;
		}

		protected static function encode(array $value)
		{
			return empty($value) ? '' : Json::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}
