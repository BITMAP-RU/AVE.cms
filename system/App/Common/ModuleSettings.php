<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ModuleSettings.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Настройки модулей поверх общего key-value хранилища App\Common\Settings.
	 *
	 * Не заводит отдельную таблицу: ключи просто пространственно именуются как
	 * "<module>.<key>", что переиспользует типизацию, кеш и upsert базового Settings.
	 */
	class ModuleSettings
	{
		/** Полный ключ настройки: "<module>.<key>" (или "<key>" без модуля). */
		public static function key($key, $module = null)
		{
			$module = trim((string) $module);
			$key = trim((string) $key);
			return $module !== '' ? $module . '.' . $key : $key;
		}

		/** Значение настройки модуля или $default, если не задано. */
		public static function get($key, $default = null, $module = null)
		{
			$value = Settings::get(self::key($key, $module));
			return $value === null ? $default : $value;
		}

		/** Записать настройку модуля (тип определяется автоматически или задаётся явно). */
		public static function set($key, $value, $module = null, $type = null)
		{
			$fullKey = self::key($key, $module);
			if (SettingsRegistry::has($fullKey)) {
				return SettingsRegistry::save($fullKey, $value);
			}

			Settings::set($fullKey, $value, $type);
			return $value;
		}

		/** Значение с учётом default из SettingsRegistry. */
		public static function value($key, $default = null, $module = null)
		{
			return SettingsRegistry::value(self::key($key, $module), $default);
		}

		/** Сохранить настройку через SettingsRegistry validation/audit. */
		public static function save($key, $value, $module = null, $actorId = null)
		{
			return SettingsRegistry::save(self::key($key, $module), $value, $actorId);
		}

		/** Удалить все настройки модуля при физическом удалении пакета. */
		public static function purge($module)
		{
			$module = strtolower(trim((string) $module));
			if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $module)) {
				throw new \InvalidArgumentException('Некорректный код модуля');
			}

			Settings::deletePrefix($module . '.');
		}
	}
