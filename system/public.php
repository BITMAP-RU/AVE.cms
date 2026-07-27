<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/public.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	/**
	 * Native bootstrap публичных модулей.
	 *
	 * Подключается после public frontend bootstrap: не запускает App::init(), не меняет
	 * session handler и не повторяет инициализацию. Поднимает Twig и декларативные
	 * public-модули поверх уже готового framework runtime.
	 */

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicModuleRuntime;
	use App\Common\PublicConfiguration;

	\App\Common\Loader\Load::twigLoad();

	$publicRuntimeConfig = PublicConfiguration::all();
	PublicModuleRuntime::boot($publicRuntimeConfig);

	if (class_exists('App\Content\Fields\PublicFieldRuntime')) {
		\App\Content\Fields\PublicFieldRuntime::configure('native');
	}

	if (class_exists('App\\Common\\PublicSysblockRegistry')) {
		\App\Common\PublicSysblockRegistry::configure(\App\Content\ContentTables::table('sysblocks'));
	}
