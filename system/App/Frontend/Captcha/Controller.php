<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Captcha/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Captcha;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class Controller
	{
		public function image(array $params = array())
		{
			require_once BASEPATH . '/system/vendor/Captcha/kcaptcha.php';
			unset($_SESSION['captcha_keystring']);

			ob_start();
			$captcha = new \KCAPTCHA();
			$image = ob_get_clean();
			$_SESSION['captcha_keystring'] = $captcha->getKeyString();

			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
			header('Pragma: no-cache', true);
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);

			return $image;
		}
	}
