<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Registration/CheckoutRegistrationGateInterface.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Registration;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	interface CheckoutRegistrationGateInterface
	{
		public function checkoutOptions();

		public function sendCheckoutAccess(array $user, $token, array $context = array());
	}
