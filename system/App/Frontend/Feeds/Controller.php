<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Controller
	{
		public function show(array $params = array())
		{
			$alias = isset($params['alias']) ? preg_replace('/\.yml$/i', '', (string) $params['alias']) : '';
			$feed = (new Repository())->findByAlias($alias);
			if (!$feed || ($feed['access_token'] !== '' && (!isset($_GET['token']) || !hash_equals($feed['access_token'], (string) $_GET['token'])))) { http_response_code(404); return ''; }
			try { $xml = (new Service())->output($feed); } catch (\Throwable $e) { error_log('Feed ' . $alias . ': ' . $e->getMessage()); http_response_code(500); return ''; }
			header_remove('Pragma'); header_remove('Expires'); header('Content-Type: application/xml; charset=UTF-8', true); header('Cache-Control: public, max-age=300', true);
			return $xml;
		}
	}
