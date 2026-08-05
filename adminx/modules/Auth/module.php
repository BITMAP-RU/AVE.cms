<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Auth/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Модуль доступа: экран логина на теме AdminKit + logout.
	 *
	 * Роут /login публичный (доступен без авторизации); /logout требует входа и
	 * работает через POST + CSRF (ajax-контракт success/error).
	 */
	return [
		'code'    => 'auth',
		'name'    => 'Доступ',
		'version' => '0.2.1',

		'public_routes' => [
			'/login',
			'/locale',
		],

		'routes' => array(
			array('GET', '/login', array(\App\Adminx\Auth\Controller::class, 'form')),
			array('POST', '/login', array(\App\Adminx\Auth\Controller::class, 'login')),
			array('POST', '/locale', array(\App\Adminx\Auth\Controller::class, 'language')),
			array('GET', '/account/password', array(\App\Adminx\Auth\Controller::class, 'passwordForm'), array('permission' => 'admin_panel')),
			array('POST', '/account/password', array(\App\Adminx\Auth\Controller::class, 'changePassword'), array('permission' => 'admin_panel')),
			array('POST', '/reauth', array(\App\Adminx\Auth\Controller::class, 'reauth'), array('permission' => 'admin_panel')),
			array('POST', '/logout', array(\App\Adminx\Auth\Controller::class, 'logout')),
		),
	];
