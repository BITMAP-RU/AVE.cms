<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Раздел «Пользователи» — CRUD над системной таблицей пользователей.
	 * Первый системный раздел после пилота (Фаза 1 плана миграции: идентичность).
	 */
	return [
		'code'    => 'users',
		'name'    => 'Пользователи',
		'version' => '0.2.0',

		'permissions' => [
			'key'      => 'users',
			'items'    => array(
				array(
					'code' => 'view_users',
					'group_code' => 'navigation',
					'name' => 'Просмотр пользователей',
					'description' => 'Доступ к разделу и списку пользователей.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_users',
					'group_code' => 'admin',
					'name' => 'Управление пользователями',
					'description' => 'Создание, изменение, удаление, вкл/выкл учётных записей.',
					'sort_order' => 20,
				),
			),
			'icon'     => 'ti ti-users',
			'priority' => 20,
		],

		'navigation' => array(
			array(
				'code' => 'users',
				'label' => 'Пользователи',
				'url' => '/users',
				'icon' => 'ti ti-users',
				'permission' => 'view_users',
				'group' => 'Система',
				'sort_order' => 31,
				'match' => array(
					'/users',
				),
			),
		),

		'routes' => array(
			array('GET', '/users', array(\App\Adminx\Users\Controller::class, 'index')),
			array('POST', '/users/bulk', array(\App\Adminx\Users\Controller::class, 'bulk')),
			array('GET', '/users/{id}', array(\App\Adminx\Users\Controller::class, 'show')),
			array('POST', '/users', array(\App\Adminx\Users\Controller::class, 'store')),
			array('POST', '/users/{id}', array(\App\Adminx\Users\Controller::class, 'update')),
			array('POST', '/users/{id}/toggle', array(\App\Adminx\Users\Controller::class, 'toggle')),
			array('POST', '/users/{id}/delete', array(\App\Adminx\Users\Controller::class, 'destroy')),
		),

		'migrations' => [
			['id' => '001_normalize_public_session_activity', 'file' => 'migrations/001_normalize_public_session_activity.sql'],
			['id' => '002_harden_public_remember_tokens', 'file' => 'migrations/002_harden_public_remember_tokens.php'],
		],

		'view_globals' => [
			'module_code' => 'users',
		],
	];
