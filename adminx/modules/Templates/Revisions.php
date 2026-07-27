<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/Revisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Helpers\Json;

	class Revisions
	{
		public static function table()
		{
			return ContentTables::table('template_revisions');
		}

		public static function labels()
		{
			return array(
				'create' => array('label' => 'Создание', 'badge' => 'badge-green'),
				'update' => array('label' => 'Сохранение', 'badge' => 'badge-blue'),
				'copy' => array('label' => 'Копия', 'badge' => 'badge-violet'),
				'import' => array('label' => 'Импорт', 'badge' => 'badge-amber'),
				'restore' => array('label' => 'Восстановление', 'badge' => 'badge-cyan'),
				'restore_backup' => array('label' => 'Перед восстановлением', 'badge' => 'badge-gray'),
				'delete' => array('label' => 'Удаление', 'badge' => 'badge-red'),
			);
		}

		public static function listForTemplate($templateId, $limit = 50)
		{
			$rows = DB::query(
				'SELECT * FROM ' . self::table() . ' WHERE template_id = %i ORDER BY created_at DESC, id DESC LIMIT %i',
				(int) $templateId,
				max(1, min(200, (int) $limit))
			)->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::format($row, false);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::format($row, true) : null;
		}

		public static function capture($templateId, $action, $authorId = 0, $comment = '', array $snapshot = null, $sourceRevisionId = 0)
		{
			$templateId = (int) $templateId;
			if ($snapshot === null) {
				$snapshot = Model::raw($templateId);
			}

			if (!$snapshot) {
				return 0;
			}

			$snapshot = Model::snapshot($snapshot);
			$json = Json::encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($json === false) {
				$json = '{}';
			}

			$snapshotHash = sha1($json);
			$textHash = sha1((string) $snapshot['template_text']);

			if (in_array($action, array('update', 'import', 'restore'), true)) {
				$last = DB::query(
					'SELECT snapshot_hash FROM ' . self::table() . ' WHERE template_id = %i ORDER BY created_at DESC, id DESC LIMIT 1',
					$templateId
				)->getValue();
				if ($last && (string) $last === $snapshotHash) {
					return 0;
				}
			}

			DB::Insert(self::table(), array(
				'template_id' => $templateId,
				'action' => (string) $action,
				'snapshot_hash' => $snapshotHash,
				'text_hash' => $textHash,
				'snapshot_json' => $json,
				'comment' => trim((string) $comment),
				'author_id' => (int) $authorId,
				'author_name' => self::authorName((int) $authorId),
				'source_revision_id' => (int) $sourceRevisionId,
				'created_at' => time(),
			));

			return (int) DB::insertId();
		}

		public static function captureCurrent($action, $authorId = 0, $comment = '')
		{
			$rows = DB::query('SELECT * FROM ' . Model::table() . ' ORDER BY Id ASC')->getAll();
			$count = 0;
			foreach ($rows as $row) {
				if (self::capture((int) $row['Id'], $action, (int) $authorId, $comment, $row) > 0) {
					$count++;
				}
			}

			return $count;
		}

		public static function restore($revisionId, $authorId = 0)
		{
			$revision = self::one($revisionId);
			if (!$revision || empty($revision['snapshot'])) {
				throw new \RuntimeException('Ревизия не найдена');
			}

			$templateId = (int) $revision['template_id'];
			$current = Model::raw($templateId);
			if (!$current) {
				throw new \RuntimeException('Шаблон не найден');
			}

			self::capture($templateId, 'restore_backup', (int) $authorId, 'Снимок перед восстановлением', $current, (int) $revisionId);
			Model::applySnapshot($templateId, $revision['snapshot'], (int) $authorId);
			self::capture($templateId, 'restore', (int) $authorId, 'Восстановлено из ревизии #' . (int) $revisionId, Model::raw($templateId), (int) $revisionId);

			return $templateId;
		}

		public static function delete($revisionId)
		{
			$revision = self::one($revisionId);
			if (!$revision) {
				return false;
			}

			DB::Delete(self::table(), 'id = %i', (int) $revisionId);
			return (int) $revision['template_id'];
		}

		public static function deleteForTemplate($templateId)
		{
			$templateId = (int) $templateId;
			if ($templateId <= 0) {
				return 0;
			}

			$count = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE template_id = %i', $templateId)->getValue();
			DB::Delete(self::table(), 'template_id = %i', $templateId);
			return $count;
		}

		protected static function format(array $row, $withSnapshot)
		{
			$labels = self::labels();
			$action = isset($row['action']) ? (string) $row['action'] : 'update';
			$meta = isset($labels[$action]) ? $labels[$action] : array('label' => $action, 'badge' => 'badge-gray');
			$snapshot = null;
			if ($withSnapshot) {
				$snapshot = Json::toArray((string) $row['snapshot_json']);
			}

			$created = isset($row['created_at']) ? (int) $row['created_at'] : 0;
			$text = $withSnapshot && is_array($snapshot) && isset($snapshot['template_text'])
				? Model::decodeText((string) $snapshot['template_text'])
				: '';

			return array(
				'id' => (int) $row['id'],
				'template_id' => (int) $row['template_id'],
				'action' => $action,
				'action_label' => $meta['label'],
				'badge' => $meta['badge'],
				'comment' => (string) $row['comment'],
				'author_id' => (int) $row['author_id'],
				'author_name' => (string) $row['author_name'],
				'created_at' => $created,
				'created_label' => $created > 0 ? date('d.m.Y H:i:s', $created) : '-',
				'source_revision_id' => (int) $row['source_revision_id'],
				'snapshot_hash' => (string) $row['snapshot_hash'],
				'text_hash' => (string) $row['text_hash'],
				'text_size' => strlen($text),
				'text_size_label' => $withSnapshot ? self::formatBytes(strlen($text)) : '',
				'snapshot' => $snapshot,
				'code' => $text,
			);
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) {
				return '';
			}

			$row = DB::query('SELECT name, login, email FROM ' . SystemTables::table('users') . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) {
				return '#' . (int) $id;
			}

			if (!empty($row['name'])) {
				return (string) $row['name'];
			}

			if (!empty($row['login'])) {
				return '@' . (string) $row['login'];
			}

			if (!empty($row['email'])) {
				return (string) $row['email'];
			}

			return '#' . (int) $id;
		}

		protected static function formatBytes($bytes)
		{
			$bytes = (int) $bytes;
			if ($bytes < 1024) {
				return $bytes . ' Б';
			}

			if ($bytes < 1048576) {
				return round($bytes / 1024, 1) . ' KB';
			}

			return round($bytes / 1048576, 1) . ' MB';
		}
	}
