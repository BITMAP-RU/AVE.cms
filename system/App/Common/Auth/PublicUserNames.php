<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Auth/PublicUserNames.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\PublicUserTables;
	use App\Helpers\Str;
	use DB;

	/** Formats names from the public user schema without procedural helpers. */
	class PublicUserNames
	{
		protected static $names = array();

		public static function format($login = '', $firstName = '', $lastName = '', $short = true)
		{
			$login = (string) $login;
			$firstName = (string) $firstName;
			$lastName = (string) $lastName;

			if ($firstName !== '' && $lastName !== '') {
				if ($short) {
					$firstName = Str::substr($firstName, 0, 1) . '.';
				}

				return Str::ucfirst(Str::lower($firstName)) . ' ' . Str::ucfirst(Str::lower($lastName));
			}

			if ($firstName !== '') {
				return Str::ucfirst(Str::lower($firstName));
			}

			if ($lastName !== '') {
				return Str::ucfirst(Str::lower($lastName));
			}

			return $login !== '' ? $login : 'Anonymous';
		}

		public static function byId($userId, $short = true)
		{
			$key = (int) $userId . ':' . ($short ? '1' : '0');
			if (!array_key_exists($key, self::$names)) {
				$row = DB::query(
					'SELECT user_name, firstname, lastname FROM %b WHERE Id = %i LIMIT 1',
					PublicUserTables::table('users'),
					(int) $userId
				)->getAssoc();
				self::$names[$key] = $row
					? self::format($row['user_name'], $row['firstname'], $row['lastname'], $short)
					: self::format();
			}

			return self::$names[$key];
		}
	}
