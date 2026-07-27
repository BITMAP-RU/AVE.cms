<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/Schema.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;

	class Schema
	{
		public static function table($name) { return ContentTables::table('feed_' . $name); }
	}
