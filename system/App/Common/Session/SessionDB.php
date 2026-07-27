<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Session/SessionDB.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Session;

	defined("BASEPATH") || die ('Direct access to this location is not allowed.');

	use App\Content\PublicShellTables;
	use DB;

	class SessionDB
	{
		private static $sessLifetime;
		private static $instance = null;

		private function __construct()
		{
			register_shutdown_function('session_write_close');

			$configured = (int) ini_get('session.gc_maxlifetime');
			$defined    = defined('SESSION_LIFETIME') && is_numeric(SESSION_LIFETIME) ? (int) SESSION_LIFETIME : 0;

			self::$sessLifetime = $defined > 0 ? $defined : max($configured, 1440);
		}

		public static function init()
		{
			if (is_null(self::$instance)) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public static function open($path, $name)
		{
			return true;
		}

		public static function close()
		{
			return true;
		}

		public static function read($sessionId)
		{
			$sql = "SELECT value, Ip FROM " . PublicShellTables::table('sessions') . "
                WHERE sesskey = %s AND expiry > %i";

			$row = DB::Query($sql, $sessionId, time())->getAssoc();

			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
			return $row && (string) $row['Ip'] === $ip ? (string) $row['value'] : '';
		}

		public static function write($sessionId, $data)
		{
			// Атомарный upsert — без двух отдельных запросов
			$expiry = time() + self::$sessLifetime;
			$sql = "INSERT INTO " . PublicShellTables::table('sessions') . " (sesskey, expiry, value, Ip, expire_datum)
                VALUES (%s, %i, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    value  = VALUES(value),
                    expiry = VALUES(expiry),
                    Ip = VALUES(Ip),
                    expire_datum = VALUES(expire_datum)";

			DB::Query($sql, $sessionId, $expiry, $data, isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '', date('d.m.Y, H:i:s', $expiry));

			return true;
		}

		public static function destroy($sessionId)
		{
			return DB::Delete(PublicShellTables::table('sessions'), 'sesskey = %s', $sessionId);
		}

		/**
		 * Удалить истёкшие сессии.
		 * expire хранит абсолютный timestamp, поэтому сравниваем с time(), а не с time()-maxlifetime.
		 */
		public static function gc($maxlifetime)
		{
			return (bool) DB::Delete(PublicShellTables::table('sessions'), 'expiry < %i', time());
		}

		public function __destruct()
		{
			//
		}
	}
