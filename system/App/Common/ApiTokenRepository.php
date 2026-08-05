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
			return self::issueRestricted($userId, $name, $scopes, self::allowedScopes(), $expiresAt);
		}

		/** Issue a token only when every requested scope belongs to the caller's boundary. */
		public static function issueRestricted($userId, $name, array $scopes, array $allowedScopes, $expiresAt = '')
		{
			$userId = (int) $userId;
			$name = trim((string) $name);
			if ($userId <= 0 || $name === '') { throw new \InvalidArgumentException('Укажите название API-токена'); }
			$requested = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $scopes)))));
			$allowedScopes = self::normalizeScopes($allowedScopes);
			if (array_diff($requested, $allowedScopes)) {
				throw new \InvalidArgumentException('Запрошенное разрешение недоступно в этом разделе');
			}

			$scopes = self::normalizeScopes($requested);
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

		/** Revoke only a token that belongs to one of the caller's scope families. */
		public static function revokeRestricted($id, array $scopes)
		{
			$token = self::find((int) $id);
			if (!$token) { return 0; }
			foreach (self::normalizeScopes($scopes) as $scope) {
				if (self::hasScope($token, $scope)) { return self::revoke((int) $id); }
			}

			return 0;
		}

		public static function find($id)
		{
			$row = DB::query('SELECT * FROM %b WHERE id=%i LIMIT 1', self::table(), (int) $id)->getAssoc();
			if (!$row) { return null; }
			$row['id'] = (int) $row['id'];
			$row['scopes_list'] = self::normalizeScopes(explode(',', (string) $row['scopes']));
			return $row;
		}

		public static function deleteByScope($scope)
		{
			$scope = trim((string) $scope);
			if (!in_array($scope, self::allowedScopes(), true)) { return 0; }
			return DB::Delete(self::table(), 'FIND_IN_SET(%s,scopes)>0', $scope);
		}

		public static function hasScope(array $token, $scope)
		{
			$scopes = isset($token['scopes_list']) && is_array($token['scopes_list'])
				? $token['scopes_list']
				: self::normalizeScopes(explode(',', isset($token['scopes']) ? (string) $token['scopes'] : ''));

			return self::scopeGranted((string) $scope, $scopes);
		}

		public static function authenticate($requiredScope)
		{
			return self::authenticateScopes(
				array((string) $requiredScope),
				array(self::permissionForScope((string) $requiredScope))
			);
		}

		public static function authenticateScopes(array $requiredScopes, array $requiredPermissions = array())
		{
			$result = self::authenticateScopesResult($requiredScopes, $requiredPermissions);
			return isset($result['token']) ? $result['token'] : null;
		}

		public static function authenticateScopesResult(array $requiredScopes, array $requiredPermissions = array())
		{
			$raw = self::bearerToken();
			if ($raw === '' || strpos($raw, 'ave_') !== 0 || strlen($raw) < 40) {
				return array('token' => null, 'error' => 'authentication_required');
			}

			$row = DB::query('SELECT t.*,u.name,u.email,u.role,u.is_active FROM ' . self::table() . ' t'
				. ' JOIN ' . SystemTables::table('users') . ' u ON u.id=t.user_id'
				. ' WHERE t.token_hash=%s AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at>=%s) LIMIT 1',
				hash('sha256', $raw), date('Y-m-d H:i:s'))->getAssoc();
			if (!$row || (int) $row['is_active'] !== 1) {
				return array('token' => null, 'error' => 'authentication_required');
			}

			$scopes = self::normalizeScopes(explode(',', (string) $row['scopes']));
			foreach (array_unique(array_map('strval', $requiredScopes)) as $requiredScope) {
				if (!self::scopeGranted($requiredScope, $scopes)) {
					return array('token' => null, 'error' => 'scope_denied');
				}
			}

			$requiredPermissions = array_values(array_filter(array_unique(array_map('strval', $requiredPermissions))));
			$permissions = Permission::forRole((string) $row['role']);
			if ((string) $row['role'] !== 'admin' && !in_array('all_permissions', $permissions, true)) {
				foreach ($requiredPermissions as $permission) {
					if (!in_array($permission, $permissions, true)) {
						return array('token' => null, 'error' => 'permission_denied');
					}
				}
			}

			if (empty($row['last_used_at']) || strtotime((string) $row['last_used_at']) < time() - 300) {
				DB::Update(self::table(), array('last_used_at' => date('Y-m-d H:i:s')), 'id=%i', (int) $row['id']);
			}

			$row['scopes_list'] = $scopes;
			return array('token' => $row, 'error' => '');
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

		protected static function allowedScopes()
		{
			return array(
				'documents:read',
				'documents:write',
				'documents:*',
				'mcp.bridge',
				'content.read',
				'structure.read',
			);
		}

		protected static function normalizeScopes(array $scopes)
		{
			return array_values(array_unique(array_intersect(
				self::allowedScopes(),
				array_map('trim', array_map('strval', $scopes))
			)));
		}

		protected static function scopeGranted($requiredScope, array $scopes)
		{
			if (in_array($requiredScope, $scopes, true)) { return true; }
			return strpos($requiredScope, 'documents:') === 0 && in_array('documents:*', $scopes, true);
		}

		protected static function permissionForScope($scope)
		{
			return (string) $scope === 'documents:write' ? 'manage_documents' : 'view_documents';
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
