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
		protected static $userAgentTableAvailable;
		protected static $activeUserAgentRules;

		public static function table()
		{
			return SystemTables::table('ip_blocks');
		}

		public static function userAgentTable()
		{
			return SystemTables::table('user_agent_blocks');
		}

		public static function enforce($maxAttempts = 120, $decaySeconds = 60)
		{
			$ip = Request::ip();
			$block = self::findActive($ip, true);
			$agentBlock = self::findActiveUserAgent(Request::userAgent(), true);
			if ($block || $agentBlock) {
				Response::setStatus(403);
				header('Content-Type: text/plain; charset=UTF-8');
				echo 'Доступ заблокирован.';
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

		public static function allUserAgents($query = '')
		{
			if (!self::hasUserAgentTable()) { return array(); }

			$query = trim((string) $query);
			$sql = 'SELECT * FROM `' . self::userAgentTable() . '`';
			if ($query !== '') {
				$sql .= ' WHERE pattern LIKE %ss OR reason LIKE %ss';
				return DB::query($sql . ' ORDER BY created_at DESC, id DESC', $query, $query)->getAll() ?: array();
			}

			return DB::query($sql . ' ORDER BY created_at DESC, id DESC')->getAll() ?: array();
		}

		public static function blockUserAgent($pattern, $reason, $expiresAt = null, $actorId = 0, $matchType = 'contains')
		{
			if (!self::hasUserAgentTable()) {
				throw new \RuntimeException('Примените миграции в разделе «База данных → Миграции»');
			}

			$pattern = self::validateUserAgentPattern($pattern, $matchType);
			$matchType = $matchType === 'exact' ? 'exact' : 'contains';
			$expiresAt = $expiresAt === null ? null : (int) $expiresAt;
			if ($expiresAt !== null && $expiresAt <= time()) {
				throw new \InvalidArgumentException('Срок блокировки должен быть в будущем');
			}

			$hash = hash('sha256', $matchType . '|' . mb_strtolower($pattern, 'UTF-8'));
			if ($expiresAt === null) {
				DB::query(
					'INSERT INTO `' . self::userAgentTable() . '` (pattern_hash, pattern, match_type, reason, expires_at, actor_id, created_at)'
						. ' VALUES (%s, %s, %s, %s, NULL, %i, %i) ON DUPLICATE KEY UPDATE reason=VALUES(reason),'
						. ' expires_at=NULL, actor_id=VALUES(actor_id), created_at=VALUES(created_at)',
					$hash, $pattern, $matchType, trim((string) $reason), (int) $actorId, time()
				);
			} else {
				DB::query(
					'INSERT INTO `' . self::userAgentTable() . '` (pattern_hash, pattern, match_type, reason, expires_at, actor_id, created_at)'
						. ' VALUES (%s, %s, %s, %s, %i, %i, %i) ON DUPLICATE KEY UPDATE reason=VALUES(reason),'
						. ' expires_at=VALUES(expires_at), actor_id=VALUES(actor_id), created_at=VALUES(created_at)',
					$hash, $pattern, $matchType, trim((string) $reason), $expiresAt, (int) $actorId, time()
				);
			}

			self::forgetActiveUserAgentRules();
			return self::findUserAgentRule($hash);
		}

		public static function unblockUserAgent($id)
		{
			if (!self::hasUserAgentTable()) { return false; }

			$result = (bool) DB::query('DELETE FROM `' . self::userAgentTable() . '` WHERE id = %i', (int) $id);
			self::forgetActiveUserAgentRules();
			return $result;
		}

		public static function findActiveUserAgent($userAgent, $failSilently = false)
		{
			$userAgent = trim((string) $userAgent);
			if ($userAgent === '') { return null; }

			foreach (self::activeUserAgentRules() as $row) {
				$pattern = isset($row['pattern']) ? trim((string) $row['pattern']) : '';
				if ($pattern === '') { continue; }
				$exact = isset($row['match_type']) && $row['match_type'] === 'exact';
				if (($exact && strcasecmp($userAgent, $pattern) === 0)
					|| (!$exact && mb_stripos($userAgent, $pattern, 0, 'UTF-8') !== false)) {
					return (array) $row;
				}
			}

			return null;
		}

		public static function suggestUserAgentPattern($userAgent)
		{
			$userAgent = trim((string) $userAgent);
			if ($userAgent === '') { return array('pattern' => '', 'match_type' => 'exact'); }

			if (preg_match('/\b([a-z][a-z0-9._-]*(?:bot|crawler|spider|slurp|preview|fetcher|agent|monitor)[a-z0-9._-]*(?:\/[a-z0-9._-]+)?)/i', $userAgent, $match)) {
				return array('pattern' => $match[1], 'match_type' => 'contains');
			}

			return array('pattern' => mb_substr($userAgent, 0, 500), 'match_type' => 'exact');
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
			$count = (int) DB::affectedRows();
			if (self::hasUserAgentTable()) {
				DB::query('DELETE FROM `' . self::userAgentTable() . '` WHERE expires_at IS NOT NULL AND expires_at <= %i LIMIT ' . $limit, time());
				$userAgentCount = (int) DB::affectedRows();
				$count += $userAgentCount;
				if ($userAgentCount > 0) { self::forgetActiveUserAgentRules(); }
			}

			return $count;
		}

		protected static function findUserAgentRule($hash)
		{
			if (!self::hasUserAgentTable()) { return null; }

			$row = DB::query('SELECT * FROM `' . self::userAgentTable() . '` WHERE pattern_hash = %s LIMIT 1', $hash)->getAssoc();
			return $row ? (array) $row : null;
		}

		protected static function hasUserAgentTable()
		{
			if (self::$userAgentTableAvailable !== null) {
				return self::$userAgentTableAvailable;
			}

			try {
				self::$userAgentTableAvailable = DatabaseSchema::tableExists(self::userAgentTable());
			} catch (\Throwable $e) {
				self::$userAgentTableAvailable = false;
			}

			return self::$userAgentTableAvailable;
		}

		protected static function activeUserAgentRules()
		{
			if (self::$activeUserAgentRules !== null) {
				return self::$activeUserAgentRules;
			}

			$cacheKey = self::activeUserAgentRulesCacheKey();
			$miss = new \stdClass();
			$rows = Cache::get($cacheKey, $miss);
			if ($rows !== $miss && is_array($rows)) {
				self::$activeUserAgentRules = $rows;
				return $rows;
			}

			if (!self::hasUserAgentTable()) {
				self::$activeUserAgentRules = array();
				Cache::set($cacheKey, array(), 30);
				return array();
			}

			self::$activeUserAgentRules = DB::query(
				'SELECT * FROM `' . self::userAgentTable() . '`'
					. ' WHERE expires_at IS NULL OR expires_at > %i ORDER BY id ASC',
				time()
			)->getAll() ?: array();
			Cache::set($cacheKey, self::$activeUserAgentRules, 60);
			return self::$activeUserAgentRules;
		}

		protected static function forgetActiveUserAgentRules()
		{
			self::$activeUserAgentRules = null;
			Cache::forget(self::activeUserAgentRulesCacheKey());
		}

		protected static function activeUserAgentRulesCacheKey()
		{
			return 'ip-blocker:user-agent-rules:' . self::userAgentTable();
		}

		protected static function validateUserAgentPattern($pattern, $matchType)
		{
			$pattern = trim((string) $pattern);
			if ($pattern === '') {
				throw new \InvalidArgumentException('Укажите User-Agent или его характерный фрагмент');
			}

			if (mb_strlen($pattern, 'UTF-8') > 500) {
				throw new \InvalidArgumentException('User-Agent не должен быть длиннее 500 символов');
			}

			if ($matchType !== 'exact' && mb_strlen($pattern, 'UTF-8') < 5) {
				throw new \InvalidArgumentException('Фрагмент User-Agent должен содержать не меньше 5 символов');
			}

			return $pattern;
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
