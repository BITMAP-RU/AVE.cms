<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Sitemap/Feature.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Sitemap;

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
			Router::get('/sitemap.xml', array(Controller::class, 'sitemap'));
			Router::get('/sitemap-{part}.xml', array(Controller::class, 'page'));
		}
	}
