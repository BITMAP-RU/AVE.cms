<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'templates',
		'name' => 'Шаблоны',
		'version' => '0.1.0',

		'permissions' => array(
			'key' => 'templates',
			'items' => array(
				array(
					'code' => 'view_templates',
					'group_code' => 'navigation',
					'name' => 'Шаблоны: просмотр',
					'description' => 'Просмотр шаблонов документов.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_templates',
					'group_code' => 'content',
					'name' => 'Шаблоны: управление',
					'description' => 'Создание, изменение, копирование, удаление и восстановление шаблонов.',
					'sort_order' => 20,
				),
			),
			'icon' => 'ti ti-template',
			'priority' => 34,
		),

		'navigation' => array(
			array(
				'code' => 'templates',
				'label' => 'Шаблоны',
				'url' => '/templates',
				'icon' => 'ti ti-template',
				'permission' => 'view_templates',
				'group' => 'Контент',
				'sort_order' => 25,
				'match' => array(
					'/templates',
				),
			),
		),

		'migrations' => array(
			array('id' => '001_create_templates_tables', 'file' => 'migrations/001_create_templates_tables.sql'),
			array('id' => '002_create_template_revisions', 'file' => 'migrations/002_create_template_revisions.sql'),
		),

		'routes' => array(
			array('GET', '/templates', array(\App\Adminx\Templates\Controller::class, 'index')),
			array('POST', '/templates/lint', array(\App\Adminx\Templates\Controller::class, 'lint')),
			array('GET', '/templates/{id}/revisions', array(\App\Adminx\Templates\Controller::class, 'revisions')),
			array('POST', '/templates/{id}/revisions/delete', array(\App\Adminx\Templates\Controller::class, 'deleteRevisions')),
			array('GET', '/templates/revisions/{revision}', array(\App\Adminx\Templates\Controller::class, 'revision')),
			array('POST', '/templates/revisions/{revision}/restore', array(\App\Adminx\Templates\Controller::class, 'restoreRevision'), array('permission' => 'manage_templates', 'sensitive' => 'stored_php.write', 'reauth' => true)),
			array('POST', '/templates/revisions/{revision}/delete', array(\App\Adminx\Templates\Controller::class, 'deleteRevision')),
			array('GET', '/templates/{id}', array(\App\Adminx\Templates\Controller::class, 'show')),
			array('POST', '/templates', array(\App\Adminx\Templates\Controller::class, 'store'), array('permission' => 'manage_templates', 'sensitive' => 'stored_php.write', 'reauth' => true)),
			array('POST', '/templates/{id}', array(\App\Adminx\Templates\Controller::class, 'update'), array('permission' => 'manage_templates', 'sensitive' => 'stored_php.write', 'reauth' => true)),
			array('POST', '/templates/{id}/copy', array(\App\Adminx\Templates\Controller::class, 'copy'), array('permission' => 'manage_templates', 'sensitive' => 'stored_php.write', 'reauth' => true)),
			array('POST', '/templates/{id}/clear-cache', array(\App\Adminx\Templates\Controller::class, 'clearCache')),
			array('POST', '/templates/{id}/delete', array(\App\Adminx\Templates\Controller::class, 'destroy')),
		),

		'view_globals' => array(
			'module_code' => 'templates',
		),
	);
