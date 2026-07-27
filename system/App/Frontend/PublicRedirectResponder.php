<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicRedirectResponder.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Emits validated redirects for canonical and historical document URLs. */
	class PublicRedirectResponder
	{
		public function permanent($location)
		{
			$this->redirect($location, 301);
		}

		public function historical($location, $status)
		{
			$status = (int) $status;
			if (!in_array($status, array(301, 302, 307, 308), true)) {
				$status = 301;
			}

			$this->redirect($location, $status);
		}

		protected function redirect($location, $status)
		{
			header('Location:' . (string) $location, true, (int) $status);
			exit;
		}
	}
