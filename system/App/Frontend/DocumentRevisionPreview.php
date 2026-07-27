<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentRevisionPreview.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Auth;
	use App\Common\Session;
	use App\Content\ContentTables;
	use DB;

	/** Short-lived, session-bound authorization for public revision previews. */
	class DocumentRevisionPreview
	{
		const MAX_TTL = 300;

		public static function issue($documentId, $revisionId, $userId, $ttl = self::MAX_TTL)
		{
			$documentId = (int) $documentId;
			$revisionId = (int) $revisionId;
			$userId = (int) $userId;
			if ($documentId <= 0 || $revisionId <= 0 || $userId <= 0) {
				throw new \InvalidArgumentException('Некорректные данные предпросмотра ревизии');
			}

			$now = time();
			$payload = self::encode(json_encode(array(
				'v' => 1,
				'd' => $documentId,
				'r' => $revisionId,
				'u' => $userId,
				'iat' => $now,
				'exp' => $now + max(1, min(self::MAX_TTL, (int) $ttl)),
			), JSON_UNESCAPED_SLASHES));
			$signature = self::encode(hash_hmac('sha256', $payload, self::key(), true));

			return $payload . '.' . $signature;
		}

		public static function validate($token, $documentId, $revisionId)
		{
			$token = trim((string) $token);
			if ($token === '' || strlen($token) > 1024 || substr_count($token, '.') !== 1) {
				return false;
			}

			list($payload, $signature) = explode('.', $token, 2);
			$binarySignature = self::decode($signature);
			if ($binarySignature === false || strlen($binarySignature) !== 32) {
				return false;
			}

			$expected = hash_hmac('sha256', $payload, self::key(), true);
			if (!hash_equals($expected, $binarySignature)) {
				return false;
			}

			$json = self::decode($payload);
			$data = $json === false ? null : json_decode($json, true);
			if (!is_array($data)) {
				return false;
			}

			$now = time();
			$issuedAt = isset($data['iat']) ? (int) $data['iat'] : 0;
			$expiresAt = isset($data['exp']) ? (int) $data['exp'] : 0;
			$user = Auth::systemUser();
			return isset($data['v']) && (int) $data['v'] === 1
				&& isset($data['d']) && (int) $data['d'] === (int) $documentId
				&& isset($data['r']) && (int) $data['r'] === (int) $revisionId
				&& isset($data['u']) && is_array($user) && (int) $data['u'] === (int) $user['id']
				&& Auth::systemUserCan('manage_documents')
				&& $issuedAt > 0 && $issuedAt <= $now + 30
				&& $expiresAt >= $now && $expiresAt > $issuedAt
				&& $expiresAt <= $issuedAt + self::MAX_TTL
				&& self::canEditDocument((int) $documentId, $user);
		}

		protected static function canEditDocument($documentId, array $systemUser)
		{
			$document = DB::query(
				'SELECT rubric_id,document_author_id FROM %b WHERE Id=%i LIMIT 1',
				ContentTables::table('documents'),
				(int) $documentId
			)->getAssoc();
			$publicUser = Auth::publicUser();
			if (!$document || !is_array($publicUser) || empty($publicUser['group'])) {
				return false;
			}

			$resolver = new RubricPermissionResolver();
			$permissions = $resolver->resolve((int) $document['rubric_id'], (int) $publicUser['group']);
			if ($resolver->allows($permissions, 'editall')) {
				return true;
			}

			$authorId = (int) $document['document_author_id'];
			return in_array('editown', $permissions, true)
				&& ($authorId === (int) $systemUser['id'] || $authorId === (int) $publicUser['id']);
		}

		protected static function key()
		{
			return hash('sha256', 'avecms:document-revision-preview:' . Session::csrfToken(), true);
		}

		protected static function encode($value)
		{
			return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
		}

		protected static function decode($value)
		{
			$value = (string) $value;
			if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
				return false;
			}

			$padding = strlen($value) % 4;
			if ($padding > 0) {
				$value .= str_repeat('=', 4 - $padding);
			}

			return base64_decode(strtr($value, '-_', '+/'), true);
		}
	}
