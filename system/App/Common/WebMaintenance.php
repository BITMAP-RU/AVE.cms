<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/WebMaintenance.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session\SessionFiles;

	/** Runs short, idempotent maintenance batches without requiring production CLI. */
	class WebMaintenance
	{
		const INTERVAL = 900;
		const BATCH_SIZE = 250;

		protected static $ran = false;

		public static function runIfDue()
		{
			if (self::$ran) { return false; }
			self::$ran = true;

			try {
				$key = 'web-maintenance:v1';
				if (Cache::get($key)) { return false; }
				Cache::set($key, time(), self::INTERVAL);
			} catch (\Throwable $e) {
				error_log('Web maintenance scheduling failed: ' . $e->getMessage());
				return false;
			}

			self::task('expired IP blocks', array(IpBlocker::class, 'pruneExpired'));
			self::task('expired referrer rows', array(ReferrerLog::class, 'pruneExpired'));
			self::pruneFileSessions();
			return true;
		}

		protected static function task($label, callable $callback)
		{
			try {
				call_user_func($callback, self::BATCH_SIZE);
			} catch (\Throwable $e) {
				error_log('Web maintenance [' . $label . '] failed: ' . $e->getMessage());
			}
		}

		protected static function pruneFileSessions()
		{
			$handler = defined('SESSION_SAVE_HANDLER') ? strtolower((string) SESSION_SAVE_HANDLER) : '';
			if (!in_array($handler, array('file', 'files'), true)) { return; }

			$configured = (int) ini_get('session.gc_maxlifetime');
			$lifetime = defined('SESSION_LIFETIME') && (int) SESSION_LIFETIME > 0
				? (int) SESSION_LIFETIME
				: max(1440, $configured);
			SessionFiles::clearBounded(BASEPATH . SESSION_DIR, 'sess', $lifetime, 500);
		}
	}
