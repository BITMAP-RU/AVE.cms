<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Session/SessionNative.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Session;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	/**
	 * Адаптер нативного PHP-обработчика сессий (SessionHandler).
	 * Используется когда SESSION_SAVE_HANDLER = 'Native'.
	 */
	class SessionNative extends \SessionHandler
	{
		private static $instance = null;

		public static function init()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self;
			}

			return self::$instance;
		}
	}
