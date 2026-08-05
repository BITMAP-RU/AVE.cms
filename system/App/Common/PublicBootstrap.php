<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicBootstrap.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;

	class PublicBootstrap
	{
		public static function initializeEnvironment()
		{
			self::captureInput();
			self::defineHost();
			self::configurePhp();
		}

		protected static function captureInput()
		{
			Request::captureInput((array) $_GET, (array) $_POST);
			$_REQUEST = array_merge($_POST, $_GET);
		}

		protected static function defineHost()
		{
			SiteOrigin::initializeHost();

			$phpSelf = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '/index.php';
			$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index.php';
			$absPath = dirname(strpos($phpSelf, $scriptName) === false && PHP_SAPI === 'cgi' ? $phpSelf : $scriptName);
			if (defined('ACP')) {
				$absPath = dirname($absPath);
			}

			defined('ABS_PATH') || define('ABS_PATH', rtrim(str_replace('\\', '/', $absPath), '/') . '/');
		}

		public static function isSsl()
		{
			return Request::isHttps();
		}

		protected static function configurePhp()
		{
			ini_set('arg_separator.output', '&amp;');
			ini_set('session.cache_limiter', 'none');
			ini_set('session.cookie_lifetime', (string) (60 * 60 * 24 * 14));
			ini_set('session.gc_maxlifetime', (string) (60 * 24));
			ini_set('session.use_cookies', '1');
			ini_set('session.use_only_cookies', '1');
			ini_set('session.use_trans_sid', '0');
			ini_set('url_rewriter.tags', '');
			if (defined('SESSION_SAVE_HANDLER') && SESSION_SAVE_HANDLER === 'memcached') {
				ini_set('session.lazy_write', '0');
				ini_set('session.save_handler', 'memcached');
				ini_set('session.save_path', MEMCACHED_SERVER . ':' . MEMCACHED_PORT . '?persistent=1&weight=1&timeout=1&retry_interval=15');
			}

			mb_internal_encoding('UTF-8');
		}
	}
