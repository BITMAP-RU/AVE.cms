<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Directories/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'directories',
		'name' => 'Справочники',
		'version' => '0.2.0',
		'requires' => array('rubrics'),

		'routes' => array(
			array('GET', '/directories', array(\App\Adminx\Directories\Controller::class, 'index'), array('permission' => 'view_rubrics')),
			array('POST', '/directories', array(\App\Adminx\Directories\Controller::class, 'store'), array('permission' => 'manage_rubrics')),
			array('POST', '/directories/{id}', array(\App\Adminx\Directories\Controller::class, 'update'), array('permission' => 'manage_rubrics')),
			array('POST', '/directories/{id}/delete', array(\App\Adminx\Directories\Controller::class, 'delete'), array('permission' => 'manage_rubrics')),
			array('POST', '/directories/{id}/items', array(\App\Adminx\Directories\Controller::class, 'storeItem'), array('permission' => 'manage_rubrics')),
			array('POST', '/directories/{id}/items/{item}', array(\App\Adminx\Directories\Controller::class, 'updateItem'), array('permission' => 'manage_rubrics')),
			array('POST', '/directories/{id}/items/{item}/delete', array(\App\Adminx\Directories\Controller::class, 'deleteItem'), array('permission' => 'manage_rubrics')),
		),

		'migrations' => array(
			array('id' => '001_create_directories', 'file' => 'migrations/001_create_directories.sql'),
		),

		'view_globals' => array(
			'module_code' => 'directories',
		),
	);
