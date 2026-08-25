<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Themes/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'themes',
		'name' => 'Темы',
		'version' => '0.2.1',

		'permissions' => array(
			'key' => 'themes',
			'items' => array(
				array(
					'code' => 'view_themes',
					'group_code' => 'navigation',
					'name' => 'Темы: просмотр',
					'description' => 'Просмотр тем, файлов и реестра подключаемых ассетов.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_themes',
					'group_code' => 'content',
					'name' => 'Темы: управление',
					'description' => 'Создание, импорт, редактирование, активация и удаление тем.',
					'sort_order' => 20,
				),
			),
			'icon' => 'ti ti-palette',
			'priority' => 33,
		),

		'navigation' => array(
			array(
				'code' => 'themes',
				'label' => 'Темы',
				'url' => '/themes',
				'icon' => 'ti ti-palette',
				'permission' => 'view_themes',
				'group' => 'Контент',
				'sort_order' => 28,
				'match' => array('/themes'),
			),
		),

		'migrations' => array(
			array('id' => '001_create_theme_asset_revisions', 'file' => 'migrations/001_create_theme_asset_revisions.sql'),
			array('id' => '002_place_theme_navigation', 'file' => 'migrations/002_place_theme_navigation.php'),
		),

		'assets' => array(
			'styles' => array(
				array('url' => ADMINX_BASE . '/modules/Themes/assets/themes.css', 'priority' => 50),
			),
			'scripts' => array(
				array('url' => ADMINX_BASE . '/modules/Themes/assets/themes.js', 'priority' => 50),
			),
		),

		'routes' => array(
			array('GET', '/themes', array(\App\Adminx\Themes\Controller::class, 'index')),
			array('GET', '/themes/file', array(\App\Adminx\Themes\Controller::class, 'file')),
			array('POST', '/themes/file', array(\App\Adminx\Themes\Controller::class, 'saveFile'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/files/create', array(\App\Adminx\Themes\Controller::class, 'createFile'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/folders/create', array(\App\Adminx\Themes\Controller::class, 'createFolder'), array('permission' => 'manage_themes')),
			array('POST', '/themes/upload', array(\App\Adminx\Themes\Controller::class, 'upload'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/path/delete', array(\App\Adminx\Themes\Controller::class, 'deletePath'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/manifest', array(\App\Adminx\Themes\Controller::class, 'saveManifest'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/settings', array(\App\Adminx\Themes\Controller::class, 'saveSettings'), array('permission' => 'manage_themes')),
			array('POST', '/themes/create', array(\App\Adminx\Themes\Controller::class, 'createTheme'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/activate', array(\App\Adminx\Themes\Controller::class, 'activate'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('GET', '/themes/export', array(\App\Adminx\Themes\Controller::class, 'export')),
			array('POST', '/themes/import', array(\App\Adminx\Themes\Controller::class, 'import'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/delete', array(\App\Adminx\Themes\Controller::class, 'deleteTheme'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('GET', '/themes/revisions', array(\App\Adminx\Themes\Controller::class, 'revisions')),
			array('GET', '/themes/revisions/{id}', array(\App\Adminx\Themes\Controller::class, 'revision')),
			array('POST', '/themes/revisions/{id}/restore', array(\App\Adminx\Themes\Controller::class, 'restoreRevision'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/themes/revisions/{id}/delete', array(\App\Adminx\Themes\Controller::class, 'deleteRevision'), array('permission' => 'manage_themes')),
			array('POST', '/themes/revisions/clear', array(\App\Adminx\Themes\Controller::class, 'clearRevisions'), array('permission' => 'manage_themes')),
		),

		'view_globals' => array('module_code' => 'themes'),
	);
