<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/PathController.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class PathController extends Controller
	{
		public function showPath(array $params = array())
		{
			$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
			$alias = FeedPresets::aliasForPath($path);
			if (!$alias) { http_response_code(404); return ''; }
			return parent::show(array('alias'=>$alias));
		}
	}
