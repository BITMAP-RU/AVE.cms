<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/IpBlocker.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;
	use App\Helpers\Response;
	use DB;

	class IpBlocker
	{
		public static function table()
		{
			return SystemTables::table('ip_blocks');
		}

		public static function enforce($maxAttempts = 120, $decaySeconds = 60)
		{
			$ip = Request::ip();
			$block = self::findActive($ip, true);
			if ($block) {
				Response::setStatus(403);
				header('Content-Type: text/plain; charset=UTF-8');
				echo 'Доступ с этого IP-адреса заблокирован.';
				exit;
			}

			if (self::bypassesAutomaticLimit()) {
				return;
			}

			$key = 'public-request:' . $ip;
			if (!RateLimiter::attempt($key, (int) $maxAttempts, (int) $decaySeconds)) {
				$retry = max(1, RateLimiter::availableIn($key));
				Response::setStatus(429);
				header('Retry-After: ' . $retry);
				header('Content-Type: text/plain; charset=UTF-8');
				echo 'Слишком много запросов. Повторите попытку позже.';
				exit;
			}
		}

		protected static function bypassesAutomaticLimit()
		{
			try {
				return Auth::systemUserCan('admin_panel');
			} catch (\Throwable $e) {
				return false;
			}
		}

		public static function all($query = '')
		{
			$query = trim((string) $query);
			$sql = 'SELECT * FROM `' . self::table() . '`';
			if ($query !== '') {
				$sql .= ' WHERE ip LIKE %ss OR reason LIKE %ss';
				return DB::query($sql . ' ORDER BY created_at DESC, id DESC', $query, $query)->getAll() ?: array();
			}

			return DB::query($sql . ' ORDER BY created_at DESC, id DESC')->getAll() ?: array();
		}

		public static function block($ip, $reason, $expiresAt = null, $actorId = 0)
		{
			$ip = self::validateIp($ip);
			$expiresAt = $expiresAt === null ? null : (int) $expiresAt;
			if ($expiresAt !== null && $expiresAt <= time()) {
				throw new \InvalidArgumentException('Срок блокировки должен быть в будущем');
			}

			if ($expiresAt === null) {
				DB::query(
					'INSERT INTO `' . self::table() . '` (ip, reason, expires_at, actor_id, created_at)'
					. ' VALUES (%s, %s, NULL, %i, %i) ON DUPLICATE KEY UPDATE reason=VALUES(reason),'
					. ' expires_at=NULL, actor_id=VALUES(actor_id), created_at=VALUES(created_at)',
					$ip, trim((string) $reason), (int) $actorId, time()
				);
			} else {
				DB::query(
					'INSERT INTO `' . self::table() . '` (ip, reason, expires_at, actor_id, created_at)'
					. ' VALUES (%s, %s, %i, %i, %i) ON DUPLICATE KEY UPDATE reason=VALUES(reason),'
					. ' expires_at=VALUES(expires_at), actor_id=VALUES(actor_id), created_at=VALUES(created_at)',
					$ip, trim((string) $reason), $expiresAt, (int) $actorId, time()
				);
			}

			return self::findActive($ip);
		}

		public static function unblock($id)
		{
			return (bool) DB::query('DELETE FROM `' . self::table() . '` WHERE id = %i', (int) $id);
		}

		public static function findActive($ip, $failSilently = false)
		{
			$ip = self::validateIp($ip);
			try {
				$row = DB::query(
					'SELECT * FROM `' . self::table() . '` WHERE ip = %s AND (expires_at IS NULL OR expires_at > %i) LIMIT 1',
					$ip, time()
				)->getAssoc();
			} catch (\Throwable $e) {
				if ($failSilently) { return null; }
				throw $e;
			}

			return $row ? (array) $row : null;
		}

		public static function pruneExpired($limit = 250)
		{
			$limit = max(1, min(1000, (int) $limit));
			DB::query('DELETE FROM `' . self::table() . '` WHERE expires_at IS NOT NULL AND expires_at <= %i LIMIT ' . $limit, time());
			return (int) DB::affectedRows();
		}

		protected static function validateIp($ip)
		{
			$ip = trim((string) $ip);
			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				throw new \InvalidArgumentException('Некорректный IP-адрес');
			}

			return $ip;
		}

	}
