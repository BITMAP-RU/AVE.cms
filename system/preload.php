<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/preload.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');
	defined('DS') || define('DS', DIRECTORY_SEPARATOR);

	require_once BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Loader' . DS . 'Load.php';

	\App\Common\Loader\Load::init();
	\App\Common\Loader\Load::regNamespace('App\Common', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common');
	\App\Common\Loader\Load::regNamespace('App\Helpers', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Helpers');
	\App\Common\Loader\Load::regNamespace('App\Content', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Content');
	\App\Common\Loader\Load::regNamespace('App\Frontend', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Frontend');
	\App\Common\Loader\Load::addClasses(array(
		'DB' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB.php',
		'DB_Eval' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Eval.php',
		'DB_Exception' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Exception.php',
		'DB_Result' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Result.php',
		'DB_Where' => BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Db' . DS . 'DB_Where.php',
	));

	$publicPreloadDb = \App\Common\DatabaseConfiguration::all();
	if ($publicPreloadDb) { DB::getInstance($publicPreloadDb); }
	\App\Common\Registry::init();
