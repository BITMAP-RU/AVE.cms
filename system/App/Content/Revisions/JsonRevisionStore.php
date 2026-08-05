<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Revisions/JsonRevisionStore.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Revisions;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Helpers\Json;
	use DB;

	/**
	 * Общее хранилище JSON-ревизий для сущностей с числовым владельцем.
	 *
	 * Класс не знает, как получить или применить снимок. Эти бизнес-правила
	 * остаются в репозитории конкретной сущности.
	 */
	class JsonRevisionStore
	{
		protected $table;
		protected $ownerColumn;
		protected $labels;

		public function __construct($table, $ownerColumn, array $labels = array())
		{
			$this->table = self::identifier($table);
			$this->ownerColumn = self::identifier($ownerColumn);
			$this->labels = $labels;
		}

		public function listing($ownerId, $limit = 50)
		{
			return DB::query(
				'SELECT * FROM ' . $this->table . ' WHERE ' . $this->ownerColumn
					. '=%i ORDER BY created_at DESC,id DESC LIMIT %i',
				(int) $ownerId,
				max(1, min(200, (int) $limit))
			)->getAll() ?: array();
		}

		public function one($id)
		{
			return DB::query(
				'SELECT * FROM ' . $this->table . ' WHERE id=%i LIMIT 1',
				(int) $id
			)->getAssoc() ?: null;
		}

		public function capture($ownerId, $action, array $snapshot, $authorId = 0, $comment = '', array $extra = array(), $deduplicate = true)
		{
			$ownerId = (int) $ownerId;
			if ($ownerId <= 0 || !$snapshot) {
				return 0;
			}

			$json = Json::encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (!is_string($json)) {
				throw new \RuntimeException('Не удалось создать JSON-снимок');
			}

			$hash = sha1($json);
			if ($deduplicate) {
				$last = DB::query(
					'SELECT snapshot_hash FROM ' . $this->table . ' WHERE ' . $this->ownerColumn
						. '=%i ORDER BY created_at DESC,id DESC LIMIT 1',
					$ownerId
				)->getValue();
				if ($last && hash_equals((string) $last, $hash)) {
					return 0;
				}
			}

			$data = array(
				$this->ownerColumn => $ownerId,
				'action' => (string) $action,
				'snapshot_hash' => $hash,
				'snapshot_json' => $json,
				'comment' => trim((string) $comment),
				'author_id' => (int) $authorId,
				'author_name' => self::authorName((int) $authorId),
				'created_at' => time(),
			);
			foreach ($extra as $column => $value) {
				$column = self::identifier($column);
				if (array_key_exists($column, $data)) {
					throw new \InvalidArgumentException('Нельзя переопределить системное поле ревизии');
				}

				$data[$column] = $value;
			}

			DB::Insert($this->table, $data);
			return (int) DB::insertId();
		}

		public function delete($id)
		{
			$row = $this->one($id);
			if (!$row) {
				return false;
			}

			DB::Delete($this->table, 'id=%i', (int) $id);
			return isset($row[$this->ownerColumn]) ? (int) $row[$this->ownerColumn] : 0;
		}

		public function deleteFor($ownerId)
		{
			$ownerId = (int) $ownerId;
			if ($ownerId <= 0) {
				return 0;
			}

			$count = (int) DB::query(
				'SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . $this->ownerColumn . '=%i',
				$ownerId
			)->getValue();
			DB::Delete($this->table, $this->ownerColumn . '=%i', $ownerId);
			return $count;
		}

		public function formatRow(array $row, $withSnapshot = false)
		{
			$action = isset($row['action']) ? (string) $row['action'] : 'update';
			$meta = isset($this->labels[$action]) ? $this->labels[$action] : array();
			$label = isset($meta['label']) ? $meta['label'] : (isset($meta[0]) ? $meta[0] : $action);
			$badge = isset($meta['badge']) ? $meta['badge'] : (isset($meta[1]) ? $meta[1] : 'badge-gray');
			$created = isset($row['created_at']) ? (int) $row['created_at'] : 0;
			$row['id'] = isset($row['id']) ? (int) $row['id'] : 0;
			$row[$this->ownerColumn] = isset($row[$this->ownerColumn]) ? (int) $row[$this->ownerColumn] : 0;
			$row['action'] = $action;
			$row['action_label'] = (string) $label;
			$row['badge'] = (string) $badge;
			$row['author_id'] = isset($row['author_id']) ? (int) $row['author_id'] : 0;
			$row['author_name'] = isset($row['author_name']) ? (string) $row['author_name'] : '';
			$row['created_at'] = $created;
			$row['created_label'] = $created > 0 ? date('d.m.Y H:i:s', $created) : '-';
			$row['snapshot'] = $withSnapshot
				? Json::toArray(isset($row['snapshot_json']) ? (string) $row['snapshot_json'] : '')
				: null;
			return $row;
		}

		public static function formatBytes($bytes)
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

		/** Flat comparison used by revision previews before a restore. */
		public static function compareSnapshots(array $current, array $target)
		{
			$keys = array_values(array_unique(array_merge(array_keys($current), array_keys($target))));
			sort($keys, SORT_STRING);
			$items = array();
			$summary = array('changed' => 0, 'unchanged' => 0, 'added' => 0, 'removed' => 0, 'total_changed' => 0);
			foreach ($keys as $key) {
				$hasCurrent = array_key_exists($key, $current);
				$hasTarget = array_key_exists($key, $target);
				if (!$hasCurrent) { $state = 'added'; }
				elseif (!$hasTarget) { $state = 'removed'; }
				else { $state = self::sameValue($current[$key], $target[$key]) ? 'unchanged' : 'changed'; }
				$summary[$state]++;
				if ($state !== 'unchanged') { $summary['total_changed']++; }
				$items[$key] = array(
					'key' => (string) $key,
					'state' => $state,
					'changed' => $state !== 'unchanged',
					'before' => $hasCurrent ? $current[$key] : null,
					'after' => $hasTarget ? $target[$key] : null,
				);
			}

			return array(
				'items' => $items,
				'summary' => $summary,
				'has_changes' => $summary['total_changed'] > 0,
			);
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) {
				return '';
			}

			$row = DB::query(
				'SELECT name,login,email FROM ' . SystemTables::table('users') . ' WHERE id=%i LIMIT 1',
				(int) $id
			)->getAssoc();
			if (!$row) {
				return '#' . (int) $id;
			}

			if (!empty($row['name'])) {
				return (string) $row['name'];
			}

			if (!empty($row['login'])) {
				return '@' . (string) $row['login'];
			}

			return !empty($row['email']) ? (string) $row['email'] : '#' . (int) $id;
		}

		protected static function sameValue($left, $right)
		{
			if (is_array($left) || is_array($right)) {
				return Json::encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
					=== Json::encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			return $left === $right || (is_scalar($left) && is_scalar($right) && (string) $left === (string) $right);
		}

		protected static function identifier($value)
		{
			$value = (string) $value;
			if ($value === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
				throw new \InvalidArgumentException('Некорректный идентификатор хранилища ревизий');
			}

			return $value;
		}
	}
