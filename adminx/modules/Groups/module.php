<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Groups/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Раздел «Роли и права» (RBAC): роли + назначение прав, объявленных модулями.
	 * Закрывает Фазу 1 плана миграции (идентичность и доступ).
	 */
	return [
		'code'    => 'groups',
		'name'    => 'Роли и права',
		'version' => '0.3.0',

		'permissions' => [
			'key'      => 'groups',
			'items'    => array(
				array(
					'code' => 'view_roles',
					'group_code' => 'navigation',
					'name' => 'Просмотр ролей',
					'description' => 'Доступ к разделу «Роли и права».',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_roles',
					'group_code' => 'admin',
					'name' => 'Управление ролями',
					'description' => 'Создание/изменение ролей и назначение прав.',
					'sort_order' => 20,
				),
			),
			'icon'     => 'ti ti-shield-lock',
			'priority' => 30,
		],

		'navigation' => array(
			array(
				'code' => 'roles',
				'label' => 'Роли и права',
				'url' => '/roles',
				'icon' => 'ti ti-shield-lock',
				'permission' => 'view_roles',
				'group' => 'Система',
				'sort_order' => 30,
				'match' => array(
					'/roles',
				),
			),
		),

		'routes' => array(
			array('GET', '/roles', array(\App\Adminx\Groups\Controller::class, 'index')),
			array('GET', '/roles/simulator', array(\App\Adminx\Groups\Controller::class, 'simulator'), array('permission' => 'view_roles')),
			array('GET', '/roles/{id}', array(\App\Adminx\Groups\Controller::class, 'show')),
			array('POST', '/roles', array(\App\Adminx\Groups\Controller::class, 'store')),
			array('POST', '/roles/{id}', array(\App\Adminx\Groups\Controller::class, 'update')),
			array('POST', '/roles/{id}/copy', array(\App\Adminx\Groups\Controller::class, 'copy'), array('permission' => 'manage_roles')),
			array('POST', '/roles/{id}/delete', array(\App\Adminx\Groups\Controller::class, 'destroy')),
		),

		'migrations' => [
			[
				'id' => '001_register_public_debug_permission',
				'file' => 'migrations/001_register_public_debug_permission.sql',
			],
			[
				'id' => '002_remove_observer_role',
				'file' => 'migrations/002_remove_observer_role.sql',
			],
			[
				'id' => '003_seed_default_roles',
				'file' => 'migrations/003_seed_default_roles.sql',
			],
			[
				'id' => '004_merge_legacy_roles',
				'file' => 'migrations/004_merge_legacy_roles.sql',
			],
			[
				'id' => '005_register_development_site_permission',
				'file' => 'migrations/005_register_development_site_permission.sql',
			],
			[
				'id' => '006_normalize_control_panel_permission',
				'file' => 'migrations/006_normalize_control_panel_permission.sql',
			],
		],

		'view_globals' => [
			'module_code' => 'groups',
		],
	];
