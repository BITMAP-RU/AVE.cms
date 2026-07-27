<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Registrations/Schema.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Registrations;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\PublicConfiguration;

	/**
	 * Резолвер таблиц модуля «Регистрационные удостоверения».
	 *
	 * Данные принадлежат удаляемому пакету → модульный префикс (тот же домен, что в
	 * миграции: {{module_prefix}}_registration_*). Живёт внутри /adminx.
	 */
	class Schema
	{
		protected static $allowed = array(
			'registration_certificates',
		);

		public static function table($suffix)
		{
			$suffix = (string) $suffix;
			if (!in_array($suffix, self::$allowed, true)) {
				throw new \InvalidArgumentException('Некорректная таблица регистрационных удостоверений');
			}

			return PublicConfiguration::prefix('module') . '_' . $suffix;
		}
	}
