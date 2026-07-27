<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicRequestApi.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Native convenience API for field types that embed request items. */
	class PublicRequestApi
	{
		public static function teaser($documentId, $parameters = '')
		{
			$item = (new RequestItemRenderer())->render((int) $documentId, '', (string) $parameters);
			$item = str_replace('[tag:path]', ABS_PATH, $item);
			$theme = defined('THEME_FOLDER') ? THEME_FOLDER : DEFAULT_THEME_FOLDER;
			return str_replace('[tag:mediapath]', ABS_PATH . 'templates/' . $theme . '/', $item);
		}
	}
