<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/bootstrap.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	use App\Common\Loader\Load;
	use App\Common\Errors;
	use App\Common\Registry;
	use App\Common\Exceptions;


	class App
	{
		protected static $instance;


		protected function __construct()
		{
			//-- Auto-loader
			require_once BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Loader' . DS . 'Load.php';

			//-- Start Auto-loader
			App\Common\Loader\Load::init();

			//-- Load classes with aliases for start engine
			App\Common\Loader\Load::addClasses([
				//-- DB legacy global classes (не в namespace, нарушают PSR-4)
				'DB'           => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB.php',
				'DB_Eval'      => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Eval.php',
				'DB_Exception' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Exception.php',
				'DB_Result'    => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Result.php',
				'DB_Where'     => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Where.php',
			]);

			//-- Load classes - Common (includes Db sub-namespaces)
			Load::regNamespace('App\Common', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS);

			//-- Load classes - Helpers
			Load::regNamespace('App\Helpers', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Helpers' . DS);

			//-- Load classes - Content (контент-ядро: подсистема полей и т.д.)
			Load::regNamespace('App\Content', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Content' . DS);

			//-- Native public services are shared by the control panel where their contracts match.
			Load::regNamespace('App\Frontend', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Frontend' . DS);

			$config = App\Common\DatabaseConfiguration::all();
			DB::getInstance($config);
			App\Common\RuntimeConstants::load();

			//-- Errors
			self::phpErrors();

			//- Headers
			$headers = [
				'Content-Type: text/html; charset=UTF-8'
			];

			//-- Переключение для нормальной работы с русскими буквами в некоторых функциях
			function_exists('mb_language') && mb_language('uni');
			function_exists('mb_regex_encoding') && mb_regex_encoding('UTF-8');
			function_exists('mb_internal_encoding') && mb_internal_encoding('UTF-8');

			//-- Magic Quotes (PHP 7.3 — функция есть, но всегда false; проверяем возвращаемое значение)
			if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
				array_walk_recursive($_GET,     'self::stripSlashesGPC');
				array_walk_recursive($_POST,    'self::stripSlashesGPC');
				array_walk_recursive($_COOKIE,  'self::stripSlashesGPC');
				array_walk_recursive($_REQUEST, 'self::stripSlashesGPC');
			}

			//-- Set ABS_PATH
			self::absPath();

			// The user extension is the only global function file loaded by the
			// framework. Public and infrastructure APIs are namespaced.
			Load::loadFile(BASEPATH . DS . 'system' . DS . 'App' . DS . 'Functions' . DS . 'custom.php');

			if (defined('PHP_DEBUGGING') && PHP_DEBUGGING) {
				Load::debugLoad('App\Common\Errors');
			}

			new Errors();

			//-- Logs
			App\Common\Logs::init();

			//-- Set HOST
			self::setHost();

			//-- SQL Debug (for development)
			App\Common\Db\Core\QueryDebug::init(defined('SQL_DEBUGGING') && SQL_DEBUGGING);

			//-- Session start
			App\Common\Session::init();

			//-- Settings
			App\Common\Settings::init();

			//-- Define
			self::setDefine();

			//-- Set http headers
			App\Common\ResponseSecurityHeaders::apply();
			App\Helpers\Request::setHeaders($headers);

			//-- Set deafult language
			self::defaultLanguage();

			//-- Set language locale
			App\Helpers\Locales::set();

			//-- Twig Autoload
			App\Common\Loader\Load::twigLoad();
			App\Common\Twig::init();

			//-- Hook trigger after initialize system
			App\Helpers\Hooks::action('system_initialize');
			App\Common\Lifecycle::event('system.initialize', 'system', 'initialized', null, array(), null, array(), 'bootstrap');

			unset($config);
		}


		/*
		|--------------------------------------------------------------------------------------
		| Define HOST
		|--------------------------------------------------------------------------------------
		| Устанавливаем константу, которая будет возвращать полный путь к домену
		|
		*/
		public static function setHost()
		{
			App\Common\SiteOrigin::initializeHost();
		}


		/*
		|--------------------------------------------------------------------------------------
		| Define ABS_PATH
		|--------------------------------------------------------------------------------------
		| Абсолютный путь
		|
		*/
		protected static function absPath()
		{
			$abs_path = dirname((strpos($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']) === false && (@PHP_SAPI == 'cgi'))
				? $_SERVER['PHP_SELF']
				: $_SERVER['SCRIPT_NAME']);

			if (defined('ACP')) {
				$abs_path = dirname($abs_path);
			}

			define('ABS_PATH', rtrim(str_replace('\\', '/', $abs_path), '/') . '/');
		}


		/*
		|--------------------------------------------------------------------------------------
		| Define ABS_ADMIN
		|--------------------------------------------------------------------------------------
		| Абсолютный путь для папки admin
		|
		*/
		public static function absAdminPath()
		{
			$abs_path = dirname((strpos($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME']) === false && (@PHP_SAPI === 'cgi'))
				? $_SERVER['PHP_SELF']
				: $_SERVER['SCRIPT_NAME']);

			define('ABS_ADMIN', rtrim(str_replace('\\', '/', $abs_path), '/') . '/');
		}


		/*
		|--------------------------------------------------------------------------------------
		| SSL Check
		|--------------------------------------------------------------------------------------
		| Проверяем есть ли протокол HTTPS
		|
		*/
		public static function isSSL()
		{
			if (isset($_SERVER['HTTPS'])) {
				if ('on' === strtolower($_SERVER['HTTPS'])) {
					return true;
				}

				if ('1' === $_SERVER['HTTPS']) {
					return true;
				}
			} elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
				return true;
			}

			return false;
		}


		/*
		|--------------------------------------------------------------------------------------
		| defaultLanguage
		|--------------------------------------------------------------------------------------
		|
		|
		*/
		public static function defaultLanguage()
		{
			App\Common\Session::set('accept_langs', array(PUBLIC_LANGUAGE => ''));
			App\Common\Session::set('current_language', PUBLIC_LANGUAGE);
		}


		/*
		|--------------------------------------------------------------------------------------
		| Отображение ошибок PHP
		|--------------------------------------------------------------------------------------
		| PHP_DEBUGGING true/false - Включить отображение ошибок
		| SELF_ERROR true/false - Выводить ошибки через файл
		|
		*/
		protected static function phpErrors()
		{
			if (PHP_DEBUGGING) {
				error_reporting(E_ALL);
				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
			} else {
				error_reporting(E_ERROR);
				ini_set('display_errors', 0);
			}
		}


		/*
		|--------------------------------------------------------------------------------------
		| Instance
		|--------------------------------------------------------------------------------------
		| Инициализация инстанса
		|
		*/
		public static function init()
		{
			if (!isset(self::$instance)) {
				self::$instance = new self;
			}

			return self::$instance;
		}


		public static function resetInstance()
		{
			if (self::$instance) {
				self::$instance = null;
			}
		}


		/*
		|--------------------------------------------------------------------------------------
		| Константы по умолчанию
		|--------------------------------------------------------------------------------------
		|
		| @param void
		|
		*/
		public static function setDefine()
		{
			define('DATE_FORMAT', Registry::get('settings', 'date_format'));
			define('TIME_FORMAT', Registry::get('settings', 'time_format'));
			define('PAGE_NOT_FOUND_ID', Registry::get('settings', 'page_not_found_id'));
		}


		private static function stripSlashesGPC(&$value)
		{
			$value = stripslashes($value);
		}


		protected function __clone()
		{
			//--
		}


		protected function __wakeup()
		{
			//--
		}
	}
