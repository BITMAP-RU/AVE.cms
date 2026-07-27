<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Registration/RegistrationGateRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Registration;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class RegistrationGateRegistry
	{
		protected static $gates = array();

		public static function register(RegistrationGateInterface $gate)
		{
			self::$gates[$gate->code()] = $gate;
		}

		public static function get($code)
		{
			return isset(self::$gates[$code]) ? self::$gates[$code] : null;
		}
	}
