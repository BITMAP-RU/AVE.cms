<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Debug/PublicDebugCollector.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Debug;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Request-scoped diagnostic events without changing normal error output. */
	class PublicDebugCollector
	{
		protected static $enabled = false;
		protected static $previousErrorHandler;
		protected static $errors = array();

		public static function boot()
		{
			if (self::$enabled) { return; }
			self::$enabled = true;
			self::$previousErrorHandler = set_error_handler(array(__CLASS__, 'handleError'), E_ALL);
		}

		public static function handleError($type, $message, $file, $line)
		{
			if (error_reporting() & $type) {
				self::$errors[] = array(
					'type' => self::severity($type),
					'code' => (int) $type,
					'message' => (string) $message,
					'file' => self::relative($file),
					'line' => (int) $line,
				);
			}

			if (is_callable(self::$previousErrorHandler)) {
				return call_user_func(self::$previousErrorHandler, $type, $message, $file, $line);
			}

			return false;
		}

		public static function errors()
		{
			return self::$errors;
		}

		public static function reset()
		{
			self::$errors = array();
		}

		protected static function severity($type)
		{
			$map = array(
				E_ERROR => 'Error', E_WARNING => 'Warning', E_NOTICE => 'Notice',
				E_USER_ERROR => 'User Error', E_USER_WARNING => 'User Warning', E_USER_NOTICE => 'User Notice',
				E_STRICT => 'Strict', E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'User Deprecated',
				E_RECOVERABLE_ERROR => 'Recoverable Error',
			);
			return isset($map[$type]) ? $map[$type] : 'PHP ' . (int) $type;
		}

		protected static function relative($file)
		{
			return str_replace(rtrim(BASEPATH, '/\\') . DIRECTORY_SEPARATOR, '', (string) $file);
		}
	}
