<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicNotice.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Renders a public system notice without relying on the template compatibility API. */
	class PublicNotice
	{
		public static function render($message)
		{
			return '<div class="display_notice"><b>Системное сообщение: </b>'
				. htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8')
				. '</div>';
		}
	}
