<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Security/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'security',
		'name' => 'Безопасность',
		'version' => '0.2.0',
		'permissions' => array('key' => 'security', 'items' => array(
			array(
				'code' => 'view_ip_blocks',
				'group_code' => 'navigation',
				'name' => 'Блокировки запросов: просмотр',
				'description' => 'Просмотр заблокированных IP-адресов и User-Agent.',
				'sort_order' => 10,
			),
			array(
				'code' => 'manage_ip_blocks',
				'group_code' => 'admin',
				'name' => 'Блокировки запросов: управление',
				'description' => 'Блокировка и разблокировка IP-адресов и User-Agent.',
				'sort_order' => 20,
			),
		), 'icon' => 'ti ti-shield-lock', 'priority' => 42),
		'navigation' => array(
			array(
				'code' => 'security_ip_blocks',
				'label' => 'Блокировки запросов',
				'url' => '/security/ip-blocks',
				'icon' => 'ti ti-shield-lock',
				'permission' => 'view_ip_blocks',
				'group' => 'Система',
				'sort_order' => 37,
				'parent' => 'settings',
				'match' => array(
					'/security/ip-blocks',
				),
			),
		),
		'migrations' => array(
			array('id' => '001_create_ip_blocks', 'file' => 'migrations/001_create_ip_blocks.sql'),
			array('id' => '002_create_user_agent_blocks', 'file' => 'migrations/002_create_user_agent_blocks.php'),
		),
		'routes' => array(
			array('GET', '/security/ip-blocks', array(\App\Adminx\Security\Controller::class, 'index'), array('permission' => 'view_ip_blocks')),
			array('POST', '/security/ip-blocks', array(\App\Adminx\Security\Controller::class, 'block'), array('permission' => 'manage_ip_blocks')),
			array('POST', '/security/ip-blocks/{id}/delete', array(\App\Adminx\Security\Controller::class, 'unblock'), array('permission' => 'manage_ip_blocks')),
			array('POST', '/security/user-agent-blocks', array(\App\Adminx\Security\Controller::class, 'blockUserAgent'), array('permission' => 'manage_ip_blocks')),
			array('POST', '/security/user-agent-blocks/{id}/delete', array(\App\Adminx\Security\Controller::class, 'unblockUserAgent'), array('permission' => 'manage_ip_blocks')),
		),
		'view_globals' => array('module_code' => 'security'),
	);
