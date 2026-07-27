<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicBootstrapEntry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	date_default_timezone_set('Europe/Moscow');
	require_once BASEPATH . '/system/preload.php';

	\App\Common\RuntimeConstants::load();

	\App\Frontend\PublicEnvironment::boot();
