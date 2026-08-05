<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/NotificationProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Users;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class NotificationProvider
	{
		public static function data()
		{
			$count = Model::failedLoginSpike(10);
			if ($count < 5) { return array(); }

			return array(array(
				'key' => 'failed_logins',
				'icon' => 'ti ti-shield-exclamation',
				'bg' => '#fee2e2',
				'fg' => '#b91c1c',
				'count' => $count,
				'title' => 'Неудачные попытки входа',
				'text' => 'За последние 10 минут. Проверьте пользователей и аудит.',
				'url' => '/users',
			));
		}
	}
