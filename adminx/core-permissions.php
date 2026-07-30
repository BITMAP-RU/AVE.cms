<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/core-permissions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Базовые права ядра админки с человекочитаемой расшифровкой.
	 * Общий источник для точки входа (adminx/index.php) и инициализации RBAC
	 * (adminx/tools/setup-rbac.php) — чтобы в _permissions попадали название и
	 * описание, а не голый код.
	 */
	return array(
		array(
			'code'        => 'admin_panel',
			'group_code'  => 'core',
			'name'        => 'Доступ в панель управления',
			'description' => 'Разрешает вход в панель управления.',
			'sort_order'  => 1,
		),
		array(
			'code'        => 'all_permissions',
			'group_code'  => 'core',
			'name'        => 'Полный доступ (суперправо)',
			'description' => 'Даёт доступ ко всем разделам и действиям без исключений.',
			'sort_order'  => 2,
		),
		array(
			'code'        => 'view_public_debug',
			'group_code'  => 'core',
			'name'        => 'Публичная панель отладки',
			'description' => 'Показывает на публичном сайте запросы, ошибки, сессию и служебные данные страницы.',
			'sort_order'  => 3,
		),
		array(
			'code'        => 'view_development_site',
			'group_code'  => 'core',
			'name'        => 'Просмотр сайта в режиме разработки',
			'description' => 'Разрешает видеть публичный сайт, когда он временно закрыт для посетителей и поисковых систем.',
			'sort_order'  => 4,
		),
	);
