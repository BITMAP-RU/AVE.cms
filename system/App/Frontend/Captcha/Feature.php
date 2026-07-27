<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Captcha/Feature.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Captcha;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Router;

	class Feature
	{
		protected static $booted = false;

		public static function boot(array $config = array())
		{
			if (self::$booted) {
				return;
			}

			self::$booted = true;
			Router::get('/inc/captcha.php', array(Controller::class, 'image'));
		}
	}
