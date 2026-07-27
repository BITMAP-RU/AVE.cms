<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/Roles.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;

	/**
	 * Общий справочник ролей: расшифровка code → человекочитаемое имя.
	 *
	 * Источник — таблица {prefix}_roles (RBAC). Используется везде, где роль
	 * показывается пользователю (список/фильтры/формы), чтобы не светить голый код
	 * (`admin` → «Администратор»).
	 */
	class Roles
	{
		/** @var array|null кеш карты на запрос */
		protected static $map = null;

		/** Фолбэк-имена, если таблица ролей ещё не создана. */
		protected static function fallback()
		{
			return array(
				'admin'     => 'Администратор',
				'guest'     => 'Гостевая',
				'moderator' => 'Модератор',
				'user'      => 'Пользователи',
			);
		}

		/** Карта code => name (из БД, иначе фолбэк). */
		public static function map()
		{
			if (self::$map !== null) {
				return self::$map;
			}

			$map = array();
			try {
				$exists = DB::query('SHOW TABLES LIKE %s', SystemTables::table('roles'))->getValue();
				if ($exists) {
					$rows = DB::query('SELECT code, name FROM ' . SystemTables::table('roles') . ' ORDER BY is_system DESC, name ASC')->getAll();
					foreach ($rows ?: array() as $row) {
						$row = (array) $row;
						$code = trim((string) $row['code']);
						if ($code !== '') {
							$map[$code] = (string) $row['name'] !== '' ? (string) $row['name'] : $code;
						}
					}
				}
			} catch (\Throwable $e) {
				$map = array();
			}

			self::$map = $map ?: self::fallback();
			return self::$map;
		}

		/** Имя роли по коду (или сам код, если не найдено). */
		public static function name($code)
		{
			$code = (string) $code;
			$map = self::map();
			return isset($map[$code]) ? $map[$code] : $code;
		}

		/** Валидные коды ролей. */
		public static function codes()
		{
			return array_keys(self::map());
		}
	}
