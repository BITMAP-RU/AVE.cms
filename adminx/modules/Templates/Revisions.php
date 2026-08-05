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
	use App\Content\ContentTables;
	use App\Content\Revisions\JsonRevisionStore;

	class Revisions
	{
		protected static $store;

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
			$out = array();
			foreach (self::store()->listing($templateId, $limit) as $row) {
				$out[] = self::format($row, false);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = self::store()->one($id);
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
			$textHash = sha1((string) $snapshot['template_text']);
			return self::store()->capture(
				$templateId,
				$action,
				$snapshot,
				$authorId,
				$comment,
				array(
					'text_hash' => $textHash,
					'source_revision_id' => (int) $sourceRevisionId,
				),
				in_array($action, array('update', 'import', 'restore'), true)
			);
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
			return self::store()->delete($revisionId);
		}

		public static function deleteForTemplate($templateId)
		{
			return self::store()->deleteFor($templateId);
		}

		protected static function format(array $row, $withSnapshot)
		{
			$row = self::store()->formatRow($row, $withSnapshot);
			$snapshot = $row['snapshot'];
			$text = $withSnapshot && is_array($snapshot) && isset($snapshot['template_text'])
				? Model::decodeText((string) $snapshot['template_text'])
				: '';
			$current = $withSnapshot ? Model::raw((int) $row['template_id']) : array();
			$comparison = $withSnapshot && is_array($snapshot)
				? JsonRevisionStore::compareSnapshots($current ? Model::snapshot($current) : array(), $snapshot)
				: array();

			return array(
				'id' => (int) $row['id'],
				'template_id' => (int) $row['template_id'],
				'action' => $row['action'],
				'action_label' => $row['action_label'],
				'badge' => $row['badge'],
				'comment' => (string) $row['comment'],
				'author_id' => (int) $row['author_id'],
				'author_name' => (string) $row['author_name'],
				'created_at' => $row['created_at'],
				'created_label' => $row['created_label'],
				'source_revision_id' => (int) $row['source_revision_id'],
				'snapshot_hash' => (string) $row['snapshot_hash'],
				'text_hash' => (string) $row['text_hash'],
				'text_size' => strlen($text),
				'text_size_label' => $withSnapshot ? JsonRevisionStore::formatBytes(strlen($text)) : '',
				'snapshot' => $snapshot,
				'code' => $text,
				'comparison' => $comparison,
			);
		}

		protected static function store()
		{
			if (!self::$store) {
				self::$store = new JsonRevisionStore(self::table(), 'template_id', self::labels());
			}

			return self::$store;
		}
	}
