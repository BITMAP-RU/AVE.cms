<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/ApiTokenRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Request;
	use DB;

	/** Hashed personal access tokens for server-to-server JSON API calls. */
	class ApiTokenRepository
	{
		public static function table() { return SystemTables::table('api_tokens'); }

		public static function issue($userId, $name, array $scopes, $expiresAt = '')
		{
			$userId = (int) $userId;
			$name = trim((string) $name);
			if ($userId <= 0 || $name === '') { throw new \InvalidArgumentException('Укажите название API-токена'); }
			$scopes = self::normalizeScopes($scopes);
			if (empty($scopes)) { throw new \InvalidArgumentException('Выберите хотя бы одно разрешение API'); }
			$raw = 'ave_' . bin2hex(random_bytes(32));
			$expires = self::dateValue($expiresAt);
			DB::Insert(self::table(), array(
				'user_id' => $userId, 'name' => mb_substr($name, 0, 190, 'UTF-8'),
				'token_hash' => hash('sha256', $raw), 'token_prefix' => substr($raw, 0, 12),
				'scopes' => implode(',', $scopes), 'last_used_at' => null, 'expires_at' => $expires,
				'revoked_at' => null, 'created_at' => date('Y-m-d H:i:s'),
			));
			return array('id' => (int) DB::insertId(), 'token' => $raw, 'scopes' => $scopes, 'expires_at' => $expires);
		}

		public static function all($userId = 0)
		{
			$sql = 'SELECT t.*,u.name user_name,u.email user_email FROM ' . self::table() . ' t LEFT JOIN ' . SystemTables::table('users') . ' u ON u.id=t.user_id';
			$rows = (int) $userId > 0
				? DB::query($sql . ' WHERE t.user_id=%i ORDER BY t.id DESC', (int) $userId)->getAll()
				: DB::query($sql . ' ORDER BY t.id DESC')->getAll();
			foreach ($rows ?: array() as &$row) {
				$row['id'] = (int) $row['id'];
				$row['scopes_list'] = self::normalizeScopes(explode(',', (string) $row['scopes']));
				$row['state'] = !empty($row['revoked_at']) ? 'revoked' : (!empty($row['expires_at']) && strtotime($row['expires_at']) < time() ? 'expired' : 'active');
			}

			unset($row);
			return $rows ?: array();
		}

		public static function revoke($id)
		{
			return DB::Update(self::table(), array('revoked_at' => date('Y-m-d H:i:s')), 'id=%i AND revoked_at IS NULL', (int) $id);
		}

		public static function authenticate($requiredScope)
		{
			$raw = self::bearerToken();
			if ($raw === '' || strpos($raw, 'ave_') !== 0 || strlen($raw) < 40) { return null; }
			$row = DB::query('SELECT t.*,u.name,u.email,u.role,u.is_active FROM ' . self::table() . ' t'
				. ' JOIN ' . SystemTables::table('users') . ' u ON u.id=t.user_id'
				. ' WHERE t.token_hash=%s AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at>=%s) LIMIT 1',
				hash('sha256', $raw), date('Y-m-d H:i:s'))->getAssoc();
			if (!$row || (int) $row['is_active'] !== 1) { return null; }
			$scopes = self::normalizeScopes(explode(',', (string) $row['scopes']));
			if (!in_array((string) $requiredScope, $scopes, true) && !in_array('documents:*', $scopes, true)) { return null; }
			$permission = $requiredScope === 'documents:write' ? 'manage_documents' : 'view_documents';
			$permissions = Permission::forRole((string) $row['role']);
			if ((string) $row['role'] !== 'admin' && !in_array('all_permissions', $permissions, true) && !in_array($permission, $permissions, true)) { return null; }
			if (empty($row['last_used_at']) || strtotime((string) $row['last_used_at']) < time() - 300) {
				DB::Update(self::table(), array('last_used_at' => date('Y-m-d H:i:s')), 'id=%i', (int) $row['id']);
			}

			$row['scopes_list'] = $scopes;
			return $row;
		}

		protected static function bearerToken()
		{
			$header = isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
			if ($header === '' && function_exists('getallheaders')) {
				$headers = getallheaders();
				$header = isset($headers['Authorization']) ? (string) $headers['Authorization'] : '';
			}

			return preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) ? trim($matches[1]) : '';
		}

		protected static function normalizeScopes(array $scopes)
		{
			$allowed = array('documents:read', 'documents:write', 'documents:*');
			return array_values(array_unique(array_intersect($allowed, array_map('trim', array_map('strval', $scopes)))));
		}

		protected static function dateValue($value)
		{
			$value = trim((string) $value);
			if ($value === '') { return null; }
			$time = strtotime($value);
			if (!$time || $time <= time()) { throw new \InvalidArgumentException('Срок действия токена должен быть в будущем'); }
			return date('Y-m-d H:i:s', $time);
		}
	}
