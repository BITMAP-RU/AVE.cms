<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Registration/RegistrationGateInterface.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Registration;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	interface RegistrationGateInterface
	{
		public function code();
		public function normalize($identifier);
		public function valid($identifier);
		public function send(array $user, $token);
	}
