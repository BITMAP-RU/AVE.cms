<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Events/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'events',
		'name' => 'Системные события',
		'version' => '0.1.1',

		'permissions' => array(
			'key' => 'events',
			'items' => array(
				array(
					'code' => 'view_events',
					'group_code' => 'navigation',
					'name' => 'События: просмотр',
					'description' => 'Доступ к журналу системных событий и логам.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_events',
					'group_code' => 'admin',
					'name' => 'События: управление',
					'description' => 'Экспорт и очистка журналов событий.',
					'sort_order' => 20,
				),
			),
			'icon' => 'ti ti-history',
			'priority' => 40,
		),

		'navigation' => array(
			array(
				'code' => 'events',
				'label' => 'События',
				'url' => '/events',
				'icon' => 'ti ti-history',
				'permission' => 'view_events',
				'group' => 'Система',
				'sort_order' => 35,
				'match' => array(
					'/events',
				),
			),
		),

		'routes' => array(
			array('GET', '/events', array(\App\Adminx\Events\Controller::class, 'index')),
			array('GET', '/events/export/{source}', array(\App\Adminx\Events\Controller::class, 'export')),
			array('POST', '/events/{source}/clear', array(\App\Adminx\Events\Controller::class, 'clear')),
		),
		'migrations' => array(
			array('id' => '001_materialize_event_logs', 'file' => 'migrations/001_materialize_event_logs.sql'),
		),

		'view_globals' => array(
			'module_code' => 'events',
		),
	);
