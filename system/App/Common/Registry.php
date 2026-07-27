<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Registry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || exit('Direct access to this location is not allowed.');

	class Registry
	{
		private static $_storage = [];
		protected static $_instance = null;

		protected function __construct()
		{
			//
		}

		public static function init()
		{
			if (self::$_instance === null) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		// ------------------------------------------------------------------ //
		//  Запись
		// ------------------------------------------------------------------ //

		public static function set($key, $value)
		{
			return self::$_storage[$key] = $value;
		}

		/** Добавить значение в конец строки */
		public static function addAfter($key, $value)
		{
			return self::$_storage[$key] .= $value;
		}

		/** Добавить значение в начало строки */
		public static function addBefore($key, $value)
		{
			return self::$_storage[$key] = $value . (isset(self::$_storage[$key]) ? self::$_storage[$key] : '');
		}

		/**
		 * Добавить элемент в массив по ключу (array_push).
		 * Если ключ не существует или не является массивом — инициализирует как [].
		 */
		public static function push($key, $value)
		{
			if (!isset(self::$_storage[$key]) || !is_array(self::$_storage[$key])) {
				self::$_storage[$key] = [];
			}

			self::$_storage[$key][] = $value;
			return self::$_storage[$key];
		}

		/** Увеличить числовое значение */
		public static function increment($key, $by = 1)
		{
			if (!isset(self::$_storage[$key])) {
				self::$_storage[$key] = 0;
			}

			return self::$_storage[$key] += (int)$by;
		}

		/** Уменьшить числовое значение */
		public static function decrement($key, $by = 1)
		{
			if (!isset(self::$_storage[$key])) {
				self::$_storage[$key] = 0;
			}

			return self::$_storage[$key] -= (int)$by;
		}

		// ------------------------------------------------------------------ //
		//  Чтение
		// ------------------------------------------------------------------ //

		public static function get($key, $arrkey = null, $default = null)
		{
			if (empty($arrkey)) {
				return self::$_storage[$key] ?? $default;
			}

			return self::$_storage[$key][$arrkey] ?? $default;
		}

		/** Получить значение как int */
		public static function getInt($key, $default = 0)
		{
			return isset(self::$_storage[$key]) ? (int)self::$_storage[$key] : (int)$default;
		}

		/** Получить значение как string */
		public static function getStr($key, $default = '')
		{
			return isset(self::$_storage[$key]) ? (string)self::$_storage[$key] : (string)$default;
		}

		/** Получить значение как bool */
		public static function getBool($key, $default = false)
		{
			return isset(self::$_storage[$key]) ? (bool)self::$_storage[$key] : (bool)$default;
		}

		/** Вернуть весь реестр */
		public static function getAll()
		{
			return self::$_storage;
		}

		/** @deprecated Используй getAll() */
		public static function output()
		{
			return self::getAll();
		}

		// ------------------------------------------------------------------ //
		//  Проверка и удаление
		// ------------------------------------------------------------------ //

		public static function exists($key, $arrkey = null)
		{
			return empty($arrkey)
				? isset(self::$_storage[$key])
				: isset(self::$_storage[$key][$arrkey]);
		}

		public static function remove($key, $arrkey = null)
		{
			if (empty($arrkey)) {
				unset(self::$_storage[$key]);
			} else {
				unset(self::$_storage[$key][$arrkey]);
			}

			return true;
		}

		public static function clean()
		{
			self::$_storage = [];
			return true;
		}

		// ------------------------------------------------------------------ //
		//  Сериализация
		// ------------------------------------------------------------------ //

		public function __sleep()
		{
			self::$_storage = serialize(self::$_storage);
			return [];
		}

		public function __wakeup()
		{
			self::$_storage = unserialize((string)self::$_storage, ['allowed_classes' => false]);
		}

		protected function __clone()
		{
			//
		}
	}
