<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentApi/Feature.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\DocumentApi;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Router;

	class Feature
	{
		protected static $booted = false;

		public static function boot(array $config = array())
		{
			if (self::$booted) { return; }
			self::$booted = true;
			Router::get('/api/v1/documents/by-alias', array(Controller::class, 'byAlias'));
			Router::get('/api/v1/documents/{id}', array(Controller::class, 'show'));
			Router::post('/api/v1/documents', array(Controller::class, 'store'));
			Router::put('/api/v1/documents/{id}', array(Controller::class, 'update'));
			Router::patch('/api/v1/documents/{id}', array(Controller::class, 'update'));
		}
	}
