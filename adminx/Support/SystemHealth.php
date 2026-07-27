<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/SystemHealth.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\NotFoundLog;

	/** Lightweight counters shared by the dashboard and topbar notifications. */
	class SystemHealth
	{
		protected static $sqlErrors;
		protected static $notFound;

		public static function sqlErrorCount()
		{
			if (self::$sqlErrors !== null) {
				return self::$sqlErrors;
			}

			$path = BASEPATH . '/tmp/logs/sql.csv';
			self::$sqlErrors = self::countCsvIncrementally($path);
			return self::$sqlErrors;
		}

		protected static function countCsvIncrementally($path)
		{
			if (!is_file($path)) {
				Cache::forget('adminx.system-health.sql-log');
				return 0;
			}

			$stat = @stat($path);
			if (!is_array($stat)) { return 0; }
			$state = Cache::get('adminx.system-health.sql-log', array());
			$sameFile = is_array($state)
				&& isset($state['device'], $state['inode'], $state['size'], $state['modified'], $state['count'])
				&& (int) $state['device'] === (int) $stat['dev']
				&& (int) $state['inode'] === (int) $stat['ino'];
			if ($sameFile
				&& (int) $state['size'] === (int) $stat['size']
				&& (int) $state['modified'] === (int) $stat['mtime']) {
				return (int) $state['count'];
			}

			$count = $sameFile && (int) $state['size'] < (int) $stat['size']
				? (int) $state['count']
				: 0;
			$offset = $sameFile && (int) $state['size'] < (int) $stat['size']
				? (int) $state['size']
				: 0;
			$handle = @fopen($path, 'rb');
			if ($handle === false) { return $count; }
			if ($offset > 0) { fseek($handle, $offset); }
			while (($row = fgetcsv($handle, 0, ',')) !== false) {
				if (isset($row[0]) && $row[0] !== '') { $count++; }
			}

			fclose($handle);
			Cache::set('adminx.system-health.sql-log', array(
				'device' => (int) $stat['dev'],
				'inode' => (int) $stat['ino'],
				'size' => (int) $stat['size'],
				'modified' => (int) $stat['mtime'],
				'count' => $count,
			), 0);
			return $count;
		}

		public static function notFoundStats()
		{
			if (self::$notFound !== null) {
				return self::$notFound;
			}

			try {
				self::$notFound = NotFoundLog::stats();
			} catch (\Throwable $e) {
				self::$notFound = array('urls' => 0, 'hits' => 0, 'unresolved' => 0);
			}

			return self::$notFound;
		}
	}
