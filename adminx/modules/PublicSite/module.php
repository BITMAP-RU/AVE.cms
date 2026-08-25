<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	return array(
		'code' => 'public_site',
		'name' => 'Публичный сайт',
		'version' => '0.5.2',
		'permissions' => array(
			'key' => 'public_site',
			'items' => array(
				array(
					'code' => 'view_public_site',
					'group_code' => 'navigation',
					'name' => 'Публичный сайт: просмотр структуры',
					'description' => 'Просмотр связей рубрик, документов, запросов, каталогов и шаблонов.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_public_presentations',
					'group_code' => 'content',
					'name' => 'Публичный сайт: управление представлениями',
					'description' => 'Создание, публикация, назначение и удаление представлений.',
					'sort_order' => 20,
				),
			),
			'icon' => 'ti ti-world',
			'priority' => 18,
		),
		'navigation' => array(
			array(
				'code' => 'public_site',
				'label' => 'Публичный сайт',
				'url' => '/public-site',
				'icon' => 'ti ti-world',
				'permission' => 'view_public_site',
				'group' => 'Контент',
				'sort_order' => 24,
				'match' => array('/public-site'),
			),
		),
		'migrations' => array(
			array('id' => '001_create_presentations', 'file' => 'migrations/001_create_presentations.sql'),
		),
		'routes' => array(
			array('GET', '/public-site', array(\App\Adminx\PublicSite\Controller::class, 'index')),
			array('GET', '/public-site/template-map', array(\App\Adminx\PublicSite\Controller::class, 'templateMap')),
			array('GET', '/public-site/map', array(\App\Adminx\PublicSite\Controller::class, 'siteMap')),
			array('GET', '/public-site/placements', array(\App\Adminx\PublicSite\Controller::class, 'placements')),
			array('GET', '/public-site/diagnostics', array(\App\Adminx\PublicSite\Controller::class, 'diagnostics')),
			array('GET', '/public-site/templates', array(\App\Adminx\PublicSite\Controller::class, 'publicTemplates')),
			array('POST', '/public-site/templates/lint', array(\App\Adminx\PublicSite\Controller::class, 'lintPublicTemplate'), array('permission' => 'manage_public_presentations')),
			array('POST', '/public-site/templates/{code}', array(\App\Adminx\PublicSite\Controller::class, 'savePublicTemplate'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/public-site/templates/{code}/delete', array(\App\Adminx\PublicSite\Controller::class, 'deletePublicTemplate'), array('permission' => 'manage_themes', 'sensitive' => 'theme_assets.write', 'reauth' => true)),
			array('POST', '/public-site/placements/rebuild', array(\App\Adminx\PublicSite\Controller::class, 'rebuildPlacements')),
			array('GET', '/public-site/presentations', array(\App\Adminx\PublicSite\PresentationsController::class, 'index')),
			array('POST', '/public-site/presentations/lint', array(\App\Adminx\PublicSite\PresentationsController::class, 'lint')),
			array('POST', '/public-site/presentations/preview', array(\App\Adminx\PublicSite\PresentationsController::class, 'preview')),
			array('GET', '/public-site/presentations/{id}', array(\App\Adminx\PublicSite\PresentationsController::class, 'show')),
			array('POST', '/public-site/presentations', array(\App\Adminx\PublicSite\PresentationsController::class, 'store')),
			array('POST', '/public-site/presentations/{id}', array(\App\Adminx\PublicSite\PresentationsController::class, 'update')),
			array('POST', '/public-site/presentations/{id}/publish', array(\App\Adminx\PublicSite\PresentationsController::class, 'publish')),
			array('POST', '/public-site/presentations/{id}/diagnose', array(\App\Adminx\PublicSite\PresentationsController::class, 'diagnose')),
			array('POST', '/public-site/presentations/{id}/copy', array(\App\Adminx\PublicSite\PresentationsController::class, 'copy')),
			array('POST', '/public-site/presentations/{id}/delete', array(\App\Adminx\PublicSite\PresentationsController::class, 'delete')),
			array('GET', '/public-site/presentations/{id}/revisions', array(\App\Adminx\PublicSite\PresentationsController::class, 'revisions')),
			array('POST', '/public-site/presentations/{id}/revisions/delete', array(\App\Adminx\PublicSite\PresentationsController::class, 'deleteRevisions')),
			array('GET', '/public-site/presentation-revisions/{revision}', array(\App\Adminx\PublicSite\PresentationsController::class, 'revision')),
			array('POST', '/public-site/presentation-revisions/{revision}/restore', array(\App\Adminx\PublicSite\PresentationsController::class, 'restoreRevision')),
			array('POST', '/public-site/presentation-revisions/{revision}/delete', array(\App\Adminx\PublicSite\PresentationsController::class, 'deleteRevision')),
			array('POST', '/public-site/presentations/{id}/assignments', array(\App\Adminx\PublicSite\PresentationsController::class, 'saveAssignment')),
			array('POST', '/public-site/presentation-assignments/{assignment}/delete', array(\App\Adminx\PublicSite\PresentationsController::class, 'deleteAssignment')),
		),
		'view_globals' => array('module_code' => 'public_site'),
	);
