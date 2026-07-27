<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'customers', 'name' => 'Пользователи сайта', 'version' => '0.3.0',
		'permissions' => array('key' => 'customers', 'items' => array(
			array(
				'code' => 'view_customers',
				'group_code' => 'navigation',
				'name' => 'Пользователи сайта: просмотр',
				'sort_order' => 10,
			),
			array(
				'code' => 'manage_customers',
				'group_code' => 'admin',
				'name' => 'Пользователи сайта: управление',
				'sort_order' => 20,
			),
		), 'icon' => 'ti ti-user-heart', 'priority' => 31),
		'navigation' => array(
			array(
				'code' => 'customers',
				'label' => 'Пользователи сайта',
				'url' => '/system/customers',
				'icon' => 'ti ti-user-heart',
				'permission' => 'view_customers',
				'group' => 'Система',
				'sort_order' => 32,
				'match' => array(
					'/system/customers',
				),
			),
		),
		'routes' => array(
			array('GET', '/system/customers', array(\App\Adminx\Customers\Controller::class, 'index')),
			array('GET', '/system/customers/users/{id}', array(\App\Adminx\Customers\Controller::class, 'customer')),
			array('POST', '/system/customers/users/{id}', array(\App\Adminx\Customers\Controller::class, 'updateCustomer')),
			array('POST', '/system/customers/{id}/toggle', array(\App\Adminx\Customers\Controller::class, 'toggle')),
			array('POST', '/system/customers/fields/reorder', array(\App\Adminx\Customers\Controller::class, 'reorderFields')),
			array('POST', '/system/customers/fields/{id}', array(\App\Adminx\Customers\Controller::class, 'saveField')),
			array('POST', '/system/customers/fields/{id}/delete', array(\App\Adminx\Customers\Controller::class, 'deleteField')),
			array('POST', '/system/customers/fields/{id}/toggle', array(\App\Adminx\Customers\Controller::class, 'toggleField')),
			array('POST', '/system/customers/auth', array(\App\Adminx\Customers\Controller::class, 'saveAuthSettings')),
			array('POST', '/system/customers/pages', array(\App\Adminx\Customers\Controller::class, 'saveAuthPages')),
			array('GET', '/system/customers/forms/{key}', array(\App\Adminx\Customers\Controller::class, 'authForm')),
			array('POST', '/system/customers/forms/{key}', array(\App\Adminx\Customers\Controller::class, 'saveAuthForm')),
			array('POST', '/system/customers/forms/{key}/reset', array(\App\Adminx\Customers\Controller::class, 'resetAuthForm')),
		),
		'migrations' => array(
			array('id' => '001_normalize_public_groups', 'file' => 'migrations/001_normalize_public_groups.sql'),
			array('id' => '002_public_auth_schema', 'file' => 'migrations/002_public_auth_schema.sql'),
			array('id' => '003_normalize_last_visit', 'file' => 'migrations/003_normalize_last_visit.sql'),
			array('id' => '004_materialize_public_auth_schema', 'file' => 'migrations/004_materialize_public_auth_schema.sql'),
			array('id' => '005_checkout_registration', 'file' => 'migrations/005_checkout_registration.sql'),
			array('id' => '006_phone_identity', 'file' => 'migrations/006_phone_identity.php'),
		),
		'view_globals' => array('module_code' => 'customers'),
	);
