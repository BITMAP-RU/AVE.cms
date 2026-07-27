<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Flash.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Flash-сообщения — одноразовые сообщения между редиректами.
	 *
	 * Хранятся в сессии под ключом _flash. При чтении автоматически удаляются.
	 *
	 * Типы по умолчанию: success, error, warning, info.
	 * Можно использовать любой произвольный тип.
	 */
	class Flash
	{
		/** Ключ хранения в сессии */
		const SESSION_KEY = '_flash';

		// ------------------------------------------------------------------ //
		//  Запись
		// ------------------------------------------------------------------ //

		public static function set($type, $message)
		{
			$flash = self::storage();
			$flash[$type][] = (string)$message;
			Session::set(self::SESSION_KEY, $flash);
		}

		public static function success($message) { self::set('success', $message); }
		public static function error($message)   { self::set('error',   $message); }
		public static function warning($message) { self::set('warning', $message); }
		public static function info($message)    { self::set('info',    $message); }

		// ------------------------------------------------------------------ //
		//  Чтение (с удалением)
		// ------------------------------------------------------------------ //

		/**
		 * Получить все сообщения по типу и удалить их из сессии.
		 *
		 * @param  string $type
		 * @return string[] Массив сообщений (может быть пустым)
		 */
		public static function get($type)
		{
			$flash = self::storage();

			if (!isset($flash[$type])) {
				return [];
			}

			$messages = $flash[$type];
			unset($flash[$type]);
			Session::set(self::SESSION_KEY, $flash);

			return $messages;
		}

		/**
		 * Получить первое сообщение по типу и удалить его.
		 *
		 * @param  string $type
		 * @param  mixed  $default
		 * @return string|mixed
		 */
		public static function getOne($type, $default = null)
		{
			$messages = self::get($type);
			return $messages ? $messages[0] : $default;
		}

		/**
		 * Получить все сообщения всех типов и очистить flash.
		 *
		 * @return array ['type' => ['msg1', 'msg2'], ...]
		 */
		public static function all()
		{
			$flash = self::storage();
			Session::del(self::SESSION_KEY);
			return $flash;
		}

		// ------------------------------------------------------------------ //
		//  Проверка
		// ------------------------------------------------------------------ //

		/** Есть ли сообщения данного типа */
		public static function has($type)
		{
			$flash = self::storage();
			return !empty($flash[$type]);
		}

		/** Есть ли хоть какие-то flash-сообщения */
		public static function any()
		{
			return !empty(self::storage());
		}

		// ------------------------------------------------------------------ //
		//  Keep (сохранить до следующего запроса без удаления)
		// ------------------------------------------------------------------ //

		/**
		 * "Переложить" flash в следующий запрос — прочитать и сразу перезаписать.
		 * Полезно, если нужно передать сообщение через несколько редиректов.
		 */
		public static function keep($type)
		{
			$flash = self::storage();
			if (isset($flash[$type])) {
				Session::set(self::SESSION_KEY, $flash); // просто убеждаемся что не удалено
			}
		}

		// ------------------------------------------------------------------ //
		//  Вспомогательные
		// ------------------------------------------------------------------ //

		protected static function storage()
		{
			$flash = Session::get(self::SESSION_KEY);
			return is_array($flash) ? $flash : [];
		}
	}
