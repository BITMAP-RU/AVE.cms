<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/GlobalSearch/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'global_search',
		'name' => 'Глобальный поиск',
		'version' => '0.2.1',
		'routes' => array(
			array('GET', '/search', array(\App\Adminx\GlobalSearch\Controller::class, 'index')),
		),
	);
