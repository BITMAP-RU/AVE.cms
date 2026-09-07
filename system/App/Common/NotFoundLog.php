<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/NotFoundLog.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;
	use DB;

	/**
	 * Журнал 404: какие несуществующие URL запрашивают (и откуда). Пишется из
	 * публичного рантайма при отдаче 404, читается разделом админки.
	 */
	class NotFoundLog
	{
		protected static $requestIpColumn;

		public static function table()
		{
			return SystemTables::table('not_found_log');
		}

		/** Записать текущий 404-запрос (данные берём из $_SERVER). Не бросает. */
		public static function record()
		{
			try {
				$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
				$path = (string) parse_url($uri, PHP_URL_PATH);
				$path = rawurldecode($path);
				if ($path === '' || self::isNoise($path)) {
					return false;
				}

				$query = (string) parse_url($uri, PHP_URL_QUERY);
				$referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
				$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
				$ip = Request::ip();
				$now = time();

				if (self::hasRequestIpColumn()) {
					DB::query(
						'INSERT INTO `' . self::table() . '`'
							. ' (path_hash, path, query_string, referer, user_agent, request_ip, hits, first_seen_at, last_seen_at)'
							. ' VALUES (%s, %s, %s, %s, %s, %s, 1, %i, %i)'
							. ' ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = VALUES(last_seen_at),'
							. ' referer = VALUES(referer), query_string = VALUES(query_string),'
							. ' user_agent = VALUES(user_agent), request_ip = VALUES(request_ip)',
						hash('sha256', $path), mb_substr($path, 0, 512), mb_substr($query, 0, 512),
						mb_substr($referer, 0, 512), mb_substr($ua, 0, 255), mb_substr($ip, 0, 45), $now, $now
					);
				} else {
					DB::query(
						'INSERT INTO `' . self::table() . '`'
							. ' (path_hash, path, query_string, referer, user_agent, hits, first_seen_at, last_seen_at)'
							. ' VALUES (%s, %s, %s, %s, %s, 1, %i, %i)'
							. ' ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = VALUES(last_seen_at),'
							. ' referer = VALUES(referer), query_string = VALUES(query_string), user_agent = VALUES(user_agent)',
						hash('sha256', $path), mb_substr($path, 0, 512), mb_substr($query, 0, 512),
						mb_substr($referer, 0, 512), mb_substr($ua, 0, 255), $now, $now
					);
				}

				return true;
			} catch (\Throwable $e) {
				error_log('NotFound log write failed: ' . $e->getMessage());
				return false;
			}
		}

		protected static function hasRequestIpColumn()
		{
			if (self::$requestIpColumn !== null) { return self::$requestIpColumn; }
			try {
				self::$requestIpColumn = DatabaseSchema::columnExists(self::table(), 'request_ip');
			} catch (\Throwable $e) {
				self::$requestIpColumn = false;
			}

			return self::$requestIpColumn;
		}

		protected static function isNoise($path)
		{
			$noise = array('/favicon.ico', '/robots.txt', '/sitemap.xml', '/ads.txt');
			if (in_array($path, $noise, true)) {
				return true;
			}

			if (strpos($path, '/.well-known/') === 0 || strpos($path, '/apple-touch') === 0) {
				return true;
			}

			return false;
		}

		public static function rows($limit = 300, $query = '', $onlyUnresolved = false)
		{
			$limit = max(1, min(1000, (int) $limit));
			$query = trim((string) $query);
			$where = array();
			$args = array();
			if ($query !== '') {
				$withIp = self::hasRequestIpColumn();
				$where[] = $withIp
					? '(path LIKE %ss OR referer LIKE %ss OR user_agent LIKE %ss OR request_ip LIKE %ss)'
					: '(path LIKE %ss OR referer LIKE %ss OR user_agent LIKE %ss)';
				$args = array_fill(0, $withIp ? 4 : 3, $query);
			}

			if ($onlyUnresolved) {
				$where[] = 'resolved = 0';
			}

			$sql = 'SELECT * FROM `' . self::table() . '`';
			if ($where) {
				$sql .= ' WHERE ' . implode(' AND ', $where);
			}

			$sql .= ' ORDER BY resolved ASC, last_seen_at DESC, id DESC LIMIT ' . $limit;
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			return $rows ?: array();
		}

		public static function exportChunk($query = '', $onlyUnresolved = false, $beforeId = 0, $limit = 500)
		{
			$query = trim((string) $query);
			$limit = max(1, min(1000, (int) $limit));
			$where = array();
			$args = array();
			if ($query !== '') {
				$withIp = self::hasRequestIpColumn();
				$where[] = $withIp
					? '(path LIKE %ss OR referer LIKE %ss OR user_agent LIKE %ss OR request_ip LIKE %ss)'
					: '(path LIKE %ss OR referer LIKE %ss OR user_agent LIKE %ss)';
				$args = array_fill(0, $withIp ? 4 : 3, $query);
			}

			if ($onlyUnresolved) { $where[] = 'resolved = 0'; }
			if ((int) $beforeId > 0) {
				$where[] = 'id < %i';
				$args[] = (int) $beforeId;
			}

			$sql = 'SELECT * FROM `' . self::table() . '`';
			if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
			$sql .= ' ORDER BY id DESC LIMIT ' . $limit;
			return call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
		}

		public static function stats()
		{
			$row = DB::query(
				'SELECT COUNT(*) AS urls, COALESCE(SUM(hits), 0) AS hits,'
					. ' SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) AS unresolved'
					. ' FROM `' . self::table() . '`'
			)->getAssoc();
			return array(
				'urls' => isset($row['urls']) ? (int) $row['urls'] : 0,
				'hits' => isset($row['hits']) ? (int) $row['hits'] : 0,
				'unresolved' => isset($row['unresolved']) ? (int) $row['unresolved'] : 0,
			);
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM `' . self::table() . '` WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ?: null;
		}

		public static function resolve($id, $resolved = true)
		{
			DB::query('UPDATE `' . self::table() . '` SET resolved = %i WHERE id = %i', $resolved ? 1 : 0, (int) $id);
			return true;
		}

		public static function delete($id)
		{
			return (bool) DB::Delete(self::table(), 'id = %i', (int) $id);
		}

		public static function clearResolved()
		{
			DB::query('DELETE FROM `' . self::table() . '` WHERE resolved = 1');
			return (int) DB::affectedRows();
		}

		public static function clearAll()
		{
			DB::query('DELETE FROM `' . self::table() . '`');
			return (int) DB::affectedRows();
		}
	}
