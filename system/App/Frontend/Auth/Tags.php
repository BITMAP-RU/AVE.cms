<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Tags.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Tags
	{
		protected static $assetsAdded = false;

		public static function render(array $matches, array $context = array())
		{
			$includeAssets = !self::$assetsAdded;
			self::$assetsAdded = true;
			return Renderer::panel($includeAssets);
		}
	}
