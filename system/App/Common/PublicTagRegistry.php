<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/PublicTagRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Общий фасад безопасных тегов публичного runtime. */
	class PublicTagRegistry
	{
		public static function register($code, $pattern, $handler, $priority = 10, array $options = array())
		{
			ModuleTagRegistry::register($code, $pattern, $handler, $priority, $options);
		}

		public static function parse($content, array $context = array(), $scope = 'all')
		{
			return ModuleTagRegistry::parse($content, $context, $scope);
		}

		public static function all()
		{
			return ModuleTagRegistry::all();
		}
	}
