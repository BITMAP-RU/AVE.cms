<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Navigation/Revisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Navigation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use App\Content\Revisions\JsonRevisionStore;

	class Revisions
	{
		protected static $store;

		public static function table() { return ContentTables::table('navigation_revisions'); }
		public static function labels() { return array('create' => array('label' => 'Создание', 'badge' => 'badge-green'), 'baseline' => array('label' => 'До изменения', 'badge' => 'badge-gray'), 'update' => array('label' => 'Сохранение', 'badge' => 'badge-blue'), 'restore' => array('label' => 'Восстановление', 'badge' => 'badge-cyan'), 'restore_backup' => array('label' => 'Перед восстановлением', 'badge' => 'badge-gray')); }

		public static function listing($navigationId)
		{
			$out = array();
			foreach (self::store()->listing($navigationId, 80) as $row) { $out[] = self::format($row, false); }
			return $out;
		}

		public static function one($id)
		{
			$row = self::store()->one($id);
			return $row ? self::format($row, true) : null;
		}

		public static function capture($navigationId, $action, $authorId = 0, $comment = '', array $snapshot = null, $sourceRevisionId = 0)
		{
			$snapshot = $snapshot === null ? Model::snapshot($navigationId) : $snapshot;
			if (!$snapshot) { return 0; }
			return self::store()->capture((int) $navigationId, $action, $snapshot, (int) $authorId, $comment, array('source_revision_id' => (int) $sourceRevisionId), true);
		}

		public static function restore($revisionId, $authorId = 0)
		{
			$revision = self::one($revisionId);
			if (!$revision || empty($revision['snapshot'])) { throw new \RuntimeException('Ревизия не найдена'); }
			$id = (int) $revision['navigation_id'];
			self::capture($id, 'restore_backup', $authorId, 'Снимок перед восстановлением', null, $revisionId);
			Model::applySnapshot($id, $revision['snapshot']);
			self::capture($id, 'restore', $authorId, 'Восстановлено из ревизии #' . (int) $revisionId, null, $revisionId);
			return $id;
		}

		public static function delete($revisionId) { return self::store()->delete($revisionId); }
		public static function deleteFor($navigationId) { return self::store()->deleteFor($navigationId); }

		protected static function format(array $row, $withSnapshot)
		{
			$row = self::store()->formatRow($row, $withSnapshot);
			if ($withSnapshot && is_array($row['snapshot'])) { $row['comparison'] = JsonRevisionStore::compareSnapshots(Model::snapshot($row['navigation_id']), $row['snapshot']); }
			return $row;
		}

		protected static function store()
		{
			if (!self::$store) { self::$store = new JsonRevisionStore(self::table(), 'navigation_id', self::labels()); }
			return self::$store;
		}
	}
