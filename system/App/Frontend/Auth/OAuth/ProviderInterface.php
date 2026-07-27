<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/OAuth/ProviderInterface.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\OAuth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	interface ProviderInterface
	{
		public function code();
		public function label();
		public function authorizationUrl(array $flow);
		public function profile(array $query, array $flow);
	}
