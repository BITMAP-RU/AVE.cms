<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicEnvironment.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Errors;
	use App\Common\PublicBootstrap;
	use App\Common\PublicConfiguration;
	use App\Common\Registry;
	use App\Common\RuntimeDirectoryGuard;
	use App\Common\Session;
	use App\Helpers\Hooks;
	use App\Helpers\Locales;

	/** Initializes the framework services and compatibility boundary for public HTTP. */
	class PublicEnvironment
	{
		protected static $booted = false;

		public static function boot()
		{
			if (self::$booted) {
				return;
			}

			self::configureErrors();
			Registry::init();
			Hooks::init();
			PublicBootstrap::initializeEnvironment();
			PublicConfiguration::load();
			self::loadCustomFunctions();
			self::prepareRuntimeDirectories();

			Session::init();
			self::defineRuntimeConstants();
			Locales::set();

			self::$booted = true;
		}

		protected static function loadCustomFunctions()
		{
			$file = BASEPATH . '/system/App/Functions/custom.php';
			if (is_file($file)) {
				require_once $file;
			}
		}

		protected static function configureErrors()
		{
			if (!PHP_DEBUGGING) {
				error_reporting(E_ERROR);
				ini_set('display_errors', '0');
			} else {
				error_reporting(E_ALL);
				ini_set('display_errors', '0');
			}

			if (PHP_DEBUGGING_FILE && !defined('ACP')) {
				new Errors();
			}
		}

		protected static function prepareRuntimeDirectories()
		{
			foreach (array(ATTACH_DIR, 'cache', 'backup', 'logs', 'session', 'update') as $dir) {
				RuntimeDirectoryGuard::protect(BASEPATH . '/tmp/' . $dir);
			}

			foreach (array('module', 'sql', 'tpl') as $dir) {
				RuntimeDirectoryGuard::protect(BASEPATH . '/tmp/cache/' . $dir);
			}

			RuntimeDirectoryGuard::protect(BASEPATH . '/templates/' . DEFAULT_THEME_FOLDER . '/include/');
		}

		protected static function defineRuntimeConstants()
		{
			defined('DATE_FORMAT') || define('DATE_FORMAT', PublicSettings::get('date_format'));
			defined('TIME_FORMAT') || define('TIME_FORMAT', PublicSettings::get('time_format'));
			defined('PAGE_NOT_FOUND_ID') || define('PAGE_NOT_FOUND_ID', (int) PublicSettings::get('page_not_found_id'));
			if (isset($_REQUEST['onlycontent']) && (int) $_REQUEST['onlycontent'] === 1) {
				defined('ONLYCONTENT') || define('ONLYCONTENT', 1);
			}
		}
	}
