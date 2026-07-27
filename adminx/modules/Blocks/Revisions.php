<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/Revisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Blocks;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Helpers\Json;

	class Revisions
	{
		public static function table()
		{
			return ContentTables::table('sysblock_revisions');
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

		public static function listForBlock($blockId, $limit = 50)
		{
			$rows = DB::query(
				'SELECT * FROM ' . self::table() . ' WHERE block_id = %i ORDER BY created_at DESC, id DESC LIMIT %i',
				(int) $blockId,
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

		public static function capture($blockId, $action, $authorId = 0, $comment = '', array $snapshot = null, $sourceRevisionId = 0)
		{
			$blockId = (int) $blockId;
			if ($snapshot === null) {
				$snapshot = Model::raw($blockId);
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
			$textHash = sha1((string) $snapshot['sysblock_text']);

			if (in_array($action, array('update', 'import', 'restore'), true)) {
				$last = DB::query(
					'SELECT snapshot_hash FROM ' . self::table() . ' WHERE block_id = %i ORDER BY created_at DESC, id DESC LIMIT 1',
					$blockId
				)->getValue();
				if ($last && (string) $last === $snapshotHash) {
					return 0;
				}
			}

			DB::Insert(self::table(), array(
				'block_id' => $blockId,
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
			$rows = DB::query('SELECT * FROM ' . Model::table() . ' ORDER BY id ASC')->getAll();
			$count = 0;
			foreach ($rows as $row) {
				if (self::capture((int) $row['id'], $action, (int) $authorId, $comment, $row) > 0) {
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

			$blockId = (int) $revision['block_id'];
			$current = Model::raw($blockId);
			if (!$current) {
				throw new \RuntimeException('Системный блок не найден');
			}

			self::capture($blockId, 'restore_backup', (int) $authorId, 'Снимок перед восстановлением', $current, (int) $revisionId);
			Model::applySnapshot($blockId, $revision['snapshot'], (int) $authorId);
			self::capture($blockId, 'restore', (int) $authorId, 'Восстановлено из ревизии #' . (int) $revisionId, Model::raw($blockId), (int) $revisionId);

			return $blockId;
		}

		public static function delete($revisionId)
		{
			$revision = self::one($revisionId);
			if (!$revision) {
				return false;
			}

			DB::Delete(self::table(), 'id = %i', (int) $revisionId);
			return (int) $revision['block_id'];
		}

		public static function deleteForBlock($blockId)
		{
			$blockId = (int) $blockId;
			if ($blockId <= 0) {
				return 0;
			}

			$count = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE block_id = %i', $blockId)->getValue();
			DB::Delete(self::table(), 'block_id = %i', $blockId);
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
			$text = $withSnapshot && is_array($snapshot) && isset($snapshot['sysblock_text']) ? (string) $snapshot['sysblock_text'] : '';

			return array(
				'id' => (int) $row['id'],
				'block_id' => (int) $row['block_id'],
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
