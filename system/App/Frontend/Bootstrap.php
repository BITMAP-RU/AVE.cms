<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Bootstrap.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Twig;

	/** Registers system-owned public routes, tags and gateways. */
	class Bootstrap
	{
		protected static $booted = false;

		public static function boot(array $config = array())
		{
			if (self::$booted) {
				return;
			}

			self::$booted = true;

			Auth\Feature::boot($config);
			Captcha\Feature::boot($config);
			DocumentApi\Feature::boot($config);
			Twig::addPath(__DIR__ . DS . 'view' . DS . 'content', 'content_public');
			\App\Common\ModuleTagRegistry::register(
				'content-list',
				'#\[mod_content_list:(\d+)(?::(\d+))?(?::([01]))?(?::([a-zA-Z][a-zA-Z0-9_-]{0,63}):([^\]\r\n:]{1,255}))?\]#',
				array(ContentCollectionTags::class, 'render'),
				20
			);
			\App\Common\ModuleTagRegistry::register(
				'theme-setting',
				'#\[tag:theme-setting:([a-z][a-z0-9_-]{0,63})\]#',
				array(ThemeSettingsTags::class, 'render'),
				5,
				array('cacheable' => true)
			);
			\App\Helpers\Hooks::add(
				'frontend.response.rendering',
				array(\App\Content\Presentation\PresentationRenderer::class, 'injectLifecycleAssets'),
				900
			);
			Feeds\Feature::boot($config);
			FieldEndpoint::boot();
			Media\Feature::boot($config);
			Sitemap\Feature::boot($config);
		}
	}
