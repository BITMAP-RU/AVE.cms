<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentMediaDraft.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Session;
	use App\Helpers\Dir;

	/** Session-bound staging area used before a document save has a stable ID. */
	class DocumentMediaDraft
	{
		const ROOT = '/uploads/.drafts';
		const SESSION_KEY = '_document_media_drafts';
		const TTL = 172800;

		public static function issue($actorId, $documentId = 0)
		{
			self::cleanupExpired();
			$token = bin2hex(random_bytes(20));
			$drafts = self::sessionDrafts();
			$drafts[$token] = array(
				'actor_id' => (int) $actorId,
				'document_id' => max(0, (int) $documentId),
				'created_at' => time(),
				'touched_at' => time(),
			);
			Session::set(self::SESSION_KEY, $drafts);
			return $token;
		}

		public static function uploadDirectory($token, $fieldId, $actorId, $documentId = 0)
		{
			self::assertOwned($token, $actorId, $documentId);
			$fieldId = (int) $fieldId;
			if ($fieldId <= 0) {
				throw new \RuntimeException('Не указано поле для загрузки');
			}

			self::touch($token);
			return self::directory($token, $fieldId);
		}

		public static function directory($token, $fieldId = 0)
		{
			$token = self::normalizeToken($token);
			$path = self::ROOT . '/' . $token;
			return (int) $fieldId > 0 ? $path . '/' . (int) $fieldId : $path;
		}

		public static function assertOwned($token, $actorId, $documentId = 0)
		{
			$token = self::normalizeToken($token);
			$drafts = self::sessionDrafts();
			if (!isset($drafts[$token]) || !is_array($drafts[$token])) {
				throw new \RuntimeException('Сессия загрузки файлов устарела. Обновите редактор документа.');
			}

			$draft = $drafts[$token];
			if ((int) $draft['actor_id'] !== (int) $actorId) {
				throw new \RuntimeException('Сессия загрузки принадлежит другому пользователю');
			}

			$boundDocument = isset($draft['document_id']) ? (int) $draft['document_id'] : 0;
			if ($boundDocument > 0 && $boundDocument !== (int) $documentId) {
				throw new \RuntimeException('Сессия загрузки принадлежит другому документу');
			}

			if ((int) (isset($draft['touched_at']) ? $draft['touched_at'] : $draft['created_at']) < time() - self::TTL) {
				self::forget($token, true);
				throw new \RuntimeException('Сессия загрузки файлов устарела. Обновите редактор документа.');
			}

			return true;
		}

		public static function ownsPath($token, $fieldId, $path)
		{
			$path = self::urlPath($path);
			$prefix = self::directory($token, $fieldId) . '/';
			return strpos($path, $prefix) === 0 && strpos(substr($path, strlen($prefix)), '/') === false;
		}

		public static function isDraftPath($path)
		{
			$path = self::urlPath($path);
			return strpos($path, self::ROOT . '/') === 0;
		}

		public static function finish($token)
		{
			if (!self::validToken($token)) {
				return;
			}

			self::forget((string) $token, true);
		}

		public static function cleanupExpired()
		{
			$cutoff = time() - self::TTL;
			$drafts = self::sessionDrafts();
			$sessionChanged = false;
			foreach ($drafts as $token => $draft) {
				$touchedAt = is_array($draft) && isset($draft['touched_at'])
					? (int) $draft['touched_at']
					: (is_array($draft) && isset($draft['created_at']) ? (int) $draft['created_at'] : 0);
				if (!self::validToken($token) || $touchedAt < $cutoff) {
					unset($drafts[$token]);
					$sessionChanged = true;
				}
			}

			if ($sessionChanged) {
				Session::set(self::SESSION_KEY, $drafts);
			}

			$root = BASEPATH . self::ROOT;
			if (!is_dir($root)) {
				return;
			}

			foreach (scandir($root) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..' || !self::validToken($entry)) {
					continue;
				}

				$dir = $root . '/' . $entry;
				$modified = @filemtime($dir);
				if (is_dir($dir) && $modified !== false && $modified < $cutoff) {
					Dir::delete($dir);
				}
			}
		}

		protected static function touch($token)
		{
			$drafts = self::sessionDrafts();
			if (isset($drafts[$token])) {
				$drafts[$token]['touched_at'] = time();
				Session::set(self::SESSION_KEY, $drafts);
			}

			$dir = BASEPATH . self::directory($token);
			if (Dir::create($dir)) {
				@touch($dir);
			}
		}

		protected static function forget($token, $removeFiles)
		{
			$drafts = self::sessionDrafts();
			unset($drafts[$token]);
			Session::set(self::SESSION_KEY, $drafts);
			$dir = BASEPATH . self::directory($token);
			if ($removeFiles && is_dir($dir)) {
				Dir::delete($dir);
			}
		}

		protected static function sessionDrafts()
		{
			$drafts = Session::get(self::SESSION_KEY);
			return is_array($drafts) ? $drafts : array();
		}

		protected static function normalizeToken($token)
		{
			$token = (string) $token;
			if (!self::validToken($token)) {
				throw new \RuntimeException('Некорректная сессия загрузки файлов');
			}

			return $token;
		}

		protected static function validToken($token)
		{
			return is_string($token) && (bool) preg_match('/^[a-f0-9]{40}$/', $token);
		}

		protected static function urlPath($path)
		{
			$path = html_entity_decode(trim((string) $path), ENT_QUOTES, 'UTF-8');
			$parsed = parse_url($path, PHP_URL_PATH);
			$path = is_string($parsed) ? rawurldecode($parsed) : $path;
			$path = preg_replace('#/+#', '/', str_replace('\\', '/', $path));
			return '/' . ltrim($path, '/');
		}
	}
