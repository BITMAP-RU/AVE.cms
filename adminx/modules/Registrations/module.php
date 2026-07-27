<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Registrations/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'registrations',
		'name' => 'Регистрационные удостоверения',
		'version' => '1.0.0',
		'description' => 'Реестр РУ Росздравнадзора на медизделия: класс риска, срок действия, связь с товаром.',
		'author' => 'AVE.cms',
		'lifecycle' => array(
			'managed' => true,
			'uninstall' => array('migrations/uninstall.sql'),
		),
		'package' => array('removable' => true),
		'permissions' => array(
			'key' => 'registrations',
			'items' => array(
				array(
					'code' => 'view_registrations',
					'group_code' => 'navigation',
					'name' => 'Рег. удостоверения: просмотр',
					'description' => 'Просмотр реестра регистрационных удостоверений.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_registrations',
					'group_code' => 'content',
					'name' => 'Рег. удостоверения: управление',
					'description' => 'Создание, изменение и удаление регистрационных удостоверений.',
					'sort_order' => 20,
				),
			),
			'icon' => 'ti ti-file-certificate',
			'priority' => 41,
		),
		'routes' => array(
			array('GET', '/registrations', array(\App\Adminx\Registrations\Controller::class, 'index')),
			array('GET', '/registrations/create', array(\App\Adminx\Registrations\Controller::class, 'create')),
			array('POST', '/registrations', array(\App\Adminx\Registrations\Controller::class, 'store')),
			array('GET', '/registrations/{id}/edit', array(\App\Adminx\Registrations\Controller::class, 'edit')),
			array('POST', '/registrations/{id}/delete', array(\App\Adminx\Registrations\Controller::class, 'delete')),
			array('POST', '/registrations/{id}', array(\App\Adminx\Registrations\Controller::class, 'update')),
		),
		'migrations' => array(
			array('id' => '001_create_registration_certificates', 'file' => 'migrations/001_create_registration_certificates.sql'),
			array('id' => '002_register_registration_permissions', 'file' => 'migrations/002_register_registration_permissions.sql'),
		),
		'assets' => array(
			'styles' => array(
				array('url' => ADMINX_BASE . '/modules/Registrations/assets/registrations.css', 'priority' => 41),
			),
			'scripts' => array(
				array('url' => ADMINX_BASE . '/modules/Registrations/assets/registrations.js', 'priority' => 41),
			),
		),
		'admin_extension' => array(
			'url' => '/registrations',
			'icon' => 'ti ti-file-certificate',
			'feature' => 'Реестр регистрационных удостоверений Росздравнадзора',
			'menu' => array(
				array(
					'code' => 'registrations',
					'label' => 'Рег. удостоверения',
					'url' => '/registrations',
					'icon' => 'ti ti-file-certificate',
					'permission' => 'view_registrations',
					'group' => 'Каталог',
					'sort_order' => 40,
					'match' => array(
						'/registrations',
					),
				),
			),
		),
	);
