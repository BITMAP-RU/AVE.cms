<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Blocks;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\FileCacheInvalidator;
	use App\Common\SystemTables;
	use App\Content\ContentTables;

	class Model
	{
		public static function table()
		{
			return ContentTables::table('sysblocks');
		}

		public static function groupsTable()
		{
			return ContentTables::table('sysblocks_groups');
		}

		public static function all($q = '', $groupId = 0, $state = '')
		{
			$sql = 'SELECT b.*, g.title AS group_title, u.name AS author_name, u.login AS author_login, u.email AS author_email'
				. ' FROM ' . self::table() . ' b'
				. ' LEFT JOIN ' . self::groupsTable() . ' g ON g.id = b.sysblock_group_id'
				. ' LEFT JOIN ' . SystemTables::table('users') . ' u ON u.id = b.sysblock_author_id'
				. ' WHERE 1=1';
			$args = array();

			$q = trim((string) $q);
			if ($q !== '') {
				$sql .= ' AND (b.sysblock_name LIKE %ss OR b.sysblock_alias LIKE %ss OR b.sysblock_description LIKE %ss OR b.sysblock_text LIKE %ss)';
				$args[] = $q;
				$args[] = $q;
				$args[] = $q;
				$args[] = $q;
			}

			$groupId = (int) $groupId;
			if ($groupId > 0) {
				$sql .= ' AND b.sysblock_group_id = %i';
				$args[] = $groupId;
			}

			if ($state === 'active') {
				$sql .= ' AND b.sysblock_active = %s';
				$args[] = '1';
			} elseif ($state === 'inactive') {
				$sql .= ' AND b.sysblock_active = %s';
				$args[] = '0';
			} elseif ($state === 'external') {
				$sql .= ' AND b.sysblock_external = %s';
				$args[] = '1';
			} elseif ($state === 'ajax') {
				$sql .= ' AND b.sysblock_ajax = %s';
				$args[] = '1';
			} elseif ($state === 'visual') {
				$sql .= ' AND b.sysblock_visual = %s';
				$args[] = '1';
			}

			$sql .= ' ORDER BY COALESCE(g.position, 9999) ASC, b.id ASC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::row($row);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::row($row) : null;
		}

		public static function raw($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? $row : null;
		}

		public static function revisionFields()
		{
			return array(
				'id',
				'sysblock_group_id',
				'sysblock_name',
				'sysblock_description',
				'sysblock_alias',
				'sysblock_text',
				'sysblock_active',
				'sysblock_eval',
				'sysblock_external',
				'sysblock_ajax',
				'sysblock_visual',
				'sysblock_editor',
				'sysblock_author_id',
				'sysblock_created',
			);
		}

		public static function snapshot(array $row)
		{
			$out = array();
			foreach (self::revisionFields() as $field) {
				$out[$field] = isset($row[$field]) ? $row[$field] : '';
			}

			$out['id'] = (int) $out['id'];
			$out['sysblock_group_id'] = (int) $out['sysblock_group_id'];
			$out['sysblock_author_id'] = (int) $out['sysblock_author_id'];
			$out['sysblock_created'] = (int) $out['sysblock_created'];
			if ($out['sysblock_editor'] === '') {
				$out['sysblock_editor'] = self::legacyEditorMode($out);
			}

			return $out;
		}

		public static function applySnapshot($id, array $snapshot, $authorId)
		{
			$id = (int) $id;
			$current = self::raw($id);
			if (!$current) {
				throw new \RuntimeException('Системный блок не найден');
			}

			$data = self::snapshot($snapshot);
			unset($data['id']);
			$data['sysblock_author_id'] = (int) $authorId > 0 ? (int) $authorId : (int) $data['sysblock_author_id'];
			DB::Update(self::table(), $data, 'id = %i', $id);
			self::clearCache($id, isset($current['sysblock_alias']) ? $current['sysblock_alias'] : '');
			self::clearCache($id, isset($data['sysblock_alias']) ? $data['sysblock_alias'] : '');
			return $id;
		}

		public static function groups()
		{
			$rows = DB::query(
				'SELECT g.*, COUNT(b.id) AS blocks_count'
				. ' FROM ' . self::groupsTable() . ' g'
				. ' LEFT JOIN ' . self::table() . ' b ON b.sysblock_group_id = g.id'
				. ' GROUP BY g.id, g.position, g.title, g.description'
				. ' ORDER BY g.position ASC, g.id ASC'
			)->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = array(
					'id' => (int) $row['id'],
					'position' => (int) $row['position'],
					'title' => (string) $row['title'],
					'description' => (string) $row['description'],
					'blocks_count' => (int) $row['blocks_count'],
				);
			}

			return $out;
		}

		public static function groupMap()
		{
			$out = array();
			foreach (self::groups() as $group) {
				$out[$group['id']] = $group;
			}

			return $out;
		}

		public static function groupedBlocks(array $blocks, array $groups)
		{
			$out = array();
			foreach ($groups as $group) {
				$out[(int) $group['id']] = array(
					'id' => (int) $group['id'],
					'title' => (string) $group['title'],
					'description' => (string) $group['description'],
					'position' => (int) $group['position'],
					'items' => array(),
				);
			}

			$out[0] = array(
				'id' => 0,
				'title' => 'Без группы',
				'description' => 'Блоки без привязки к группе.',
				'position' => 99999,
				'items' => array(),
			);

			foreach ($blocks as $block) {
				$id = isset($out[(int) $block['sysblock_group_id']]) ? (int) $block['sysblock_group_id'] : 0;
				$out[$id]['items'][] = $block;
			}

			$out = array_filter($out, function ($group) {
				return !empty($group['items']);
			});

			uasort($out, function ($a, $b) {
				if ((int) $a['position'] === (int) $b['position']) {
					return strcmp((string) $a['title'], (string) $b['title']);
				}

				return (int) $a['position'] < (int) $b['position'] ? -1 : 1;
			});

			return array_values($out);
		}

		public static function group($id)
		{
			$row = DB::query('SELECT * FROM ' . self::groupsTable() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) {
				return null;
			}

			return array(
				'id' => (int) $row['id'],
				'position' => (int) $row['position'],
				'title' => (string) $row['title'],
				'description' => (string) $row['description'],
			);
		}

		public static function stats()
		{
			return array(
				'total' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table())->getValue(),
				'active' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE sysblock_active = %s', '1')->getValue(),
				'external' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE sysblock_external = %s', '1')->getValue(),
				'ajax' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE sysblock_ajax = %s', '1')->getValue(),
				'groups' => (int) DB::query('SELECT COUNT(*) FROM ' . self::groupsTable())->getValue(),
			);
		}

		public static function aliasExists($alias, $excludeId = 0)
		{
			return (bool) DB::query(
				'SELECT id FROM ' . self::table() . ' WHERE sysblock_alias = %s AND id != %i LIMIT 1',
				(string) $alias,
				(int) $excludeId
			)->getValue();
		}

		public static function save($id, array $input, $authorId)
		{
			$data = self::input($input);
			if ((int) $id > 0) {
				DB::Update(self::table(), $data, 'id = %i', (int) $id);
				self::clearCache((int) $id, $data['sysblock_alias']);
				Revisions::capture((int) $id, 'update', (int) $authorId, 'Сохранение блока');
				return (int) $id;
			}

			$data['sysblock_author_id'] = (int) $authorId > 0 ? (int) $authorId : 1;
			$data['sysblock_created'] = time();
			DB::Insert(self::table(), $data);
			$newId = (int) DB::insertId();
			Revisions::capture($newId, 'create', (int) $authorId, 'Создание блока');
			return $newId;
		}

		public static function delete($id, $authorId = 0)
		{
			$raw = self::raw($id);
			if (!$raw) {
				return false;
			}

			Revisions::capture((int) $id, 'delete', (int) $authorId, 'Удаление блока', $raw);
			$row = self::row($raw);
			if (!$row) {
				return false;
			}

			DB::Delete(self::table(), 'id = %i', (int) $id);
			self::clearCache((int) $id, $row['sysblock_alias']);
			return true;
		}

		public static function copy($id, $name, $authorId)
		{
			$row = self::one($id);
			if (!$row) {
				throw new \RuntimeException('Системный блок не найден');
			}

			$name = trim((string) $name);
			if ($name === '') {
				$name = $row['sysblock_name'] . ' (копия)';
			}

			$alias = self::uniqueAlias($row['sysblock_alias'] . '-copy');
			$data = array(
				'sysblock_group_id' => (int) $row['sysblock_group_id'],
				'sysblock_name' => $name,
				'sysblock_description' => (string) $row['sysblock_description'],
				'sysblock_alias' => $alias,
				'sysblock_text' => (string) $row['sysblock_text'],
				'sysblock_active' => '0',
				'sysblock_eval' => (string) $row['sysblock_eval'],
				'sysblock_external' => (string) $row['sysblock_external'],
				'sysblock_ajax' => (string) $row['sysblock_ajax'],
				'sysblock_visual' => (string) $row['sysblock_visual'],
				'sysblock_editor' => isset($row['sysblock_editor']) ? (string) $row['sysblock_editor'] : self::legacyEditorMode($row),
				'sysblock_author_id' => (int) $authorId > 0 ? (int) $authorId : 1,
				'sysblock_created' => time(),
			);
			DB::Insert(self::table(), $data);
			$newId = (int) DB::insertId();
			Revisions::capture($newId, 'copy', (int) $authorId, 'Копия блока #' . (int) $id);
			return $newId;
		}

		public static function saveGroup($id, array $input)
		{
			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			if ($title === '') {
				throw new \RuntimeException('Укажите название группы');
			}

			$data = array(
				'title' => $title,
				'description' => trim(isset($input['description']) ? (string) $input['description'] : ''),
			);
			if ((int) $id <= 0) {
				$data['position'] = (int) DB::query('SELECT COALESCE(MAX(position), 0) + 1 FROM ' . self::groupsTable())->getValue();
			}

			if ((int) $id > 0) {
				DB::Update(self::groupsTable(), $data, 'id = %i', (int) $id);
				return (int) $id;
			}

			DB::Insert(self::groupsTable(), $data);
			return (int) DB::insertId();
		}

		public static function reorderGroups(array $ids)
		{
			$clean = array();
			foreach ($ids as $id) {
				$id = (int) $id;
				if ($id > 0 && !in_array($id, $clean, true)) {
					$clean[] = $id;
				}
			}

			if (empty($clean)) {
				throw new \RuntimeException('Не передан порядок групп');
			}

			$position = 1;
			foreach ($clean as $id) {
				DB::Update(self::groupsTable(), array('position' => $position), 'id = %i', $id);
				$position++;
			}

			return count($clean);
		}

		public static function deleteGroup($id)
		{
			$id = (int) $id;
			if ($id <= 0) {
				return false;
			}

			DB::Update(self::table(), array('sysblock_group_id' => 0), 'sysblock_group_id = %i', $id);
			DB::Delete(self::groupsTable(), 'id = %i', $id);
			return true;
		}

		public static function clearCache($id, $alias = '')
		{
			FileCacheInvalidator::sysblock($id, $alias);
		}

		protected static function input(array $input)
		{
			$editor = isset($input['sysblock_editor'])
				? self::editorMode($input['sysblock_editor'])
				: self::legacyEditorMode($input);

			return array(
				'sysblock_group_id' => (int) (isset($input['sysblock_group_id']) ? $input['sysblock_group_id'] : 0),
				'sysblock_name' => trim(isset($input['sysblock_name']) ? (string) $input['sysblock_name'] : ''),
				'sysblock_description' => trim(isset($input['sysblock_description']) ? (string) $input['sysblock_description'] : ''),
				'sysblock_alias' => trim(isset($input['sysblock_alias']) ? (string) $input['sysblock_alias'] : ''),
				'sysblock_text' => isset($input['sysblock_text']) ? (string) $input['sysblock_text'] : '',
				'sysblock_active' => !empty($input['sysblock_active']) ? '1' : '0',
				'sysblock_eval' => $editor === 'php' ? '1' : '0',
				'sysblock_external' => !empty($input['sysblock_external']) ? '1' : '0',
				'sysblock_ajax' => !empty($input['sysblock_ajax']) ? '1' : '0',
				'sysblock_visual' => $editor === 'rich' ? '1' : '0',
				'sysblock_editor' => $editor,
			);
		}

		protected static function row(array $row)
		{
			$author = '';
			if (!empty($row['author_name'])) {
				$author = (string) $row['author_name'];
			} elseif (!empty($row['author_login'])) {
				$author = '@' . (string) $row['author_login'];
			} elseif (!empty($row['author_email'])) {
				$author = (string) $row['author_email'];
			}

			$row['id'] = (int) $row['id'];
			$row['sysblock_group_id'] = (int) $row['sysblock_group_id'];
			$row['sysblock_author_id'] = (int) $row['sysblock_author_id'];
			$row['sysblock_created'] = (int) $row['sysblock_created'];
			$row['author'] = $author !== '' ? $author : ('#' . $row['sysblock_author_id']);
			$row['group_title'] = isset($row['group_title']) && $row['group_title'] !== null ? $row['group_title'] : 'Без группы';
			$row['created_label'] = $row['sysblock_created'] > 0 ? date('d.m.Y H:i', $row['sysblock_created']) : '-';
			$row['text_size'] = strlen((string) $row['sysblock_text']);
			$row['text_size_label'] = self::formatBytes($row['text_size']);
			$row['sysblock_editor'] = isset($row['sysblock_editor']) && $row['sysblock_editor'] !== ''
				? self::editorMode($row['sysblock_editor'])
				: self::legacyEditorMode($row);
			return $row;
		}

		protected static function editorMode($mode)
		{
			$mode = strtolower(trim((string) $mode));
			return in_array($mode, array('php', 'html', 'rich', 'text'), true) ? $mode : 'html';
		}

		protected static function legacyEditorMode(array $row)
		{
			if (!empty($row['sysblock_eval'])) {
				return 'php';
			}

			return !empty($row['sysblock_visual']) ? 'rich' : 'html';
		}

		protected static function uniqueAlias($base)
		{
			$base = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $base);
			$base = trim($base, '-_');
			if ($base === '' || is_numeric($base)) {
				$base = 'sysblock';
			}

			$base = substr($base, 0, 20);
			$alias = $base;
			$i = 2;
			while (self::aliasExists($alias, 0)) {
				$suffix = '-' . $i;
				$alias = substr($base, 0, 20 - strlen($suffix)) . $suffix;
				$i++;
			}

			return $alias;
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

		protected static function removeDir($dir)
		{
			if (!is_dir($dir)) {
				return;
			}

			$base = realpath(BASEPATH . '/tmp/cache/sql/sysblocks');
			$real = realpath($dir);
			if (!$base || !$real || strpos($real, $base) !== 0) {
				return;
			}

			$items = @scandir($real);
			if ($items === false) {
				return;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				$path = $real . DIRECTORY_SEPARATOR . $item;
				if (is_dir($path)) {
					self::removeDir($path);
				} elseif (is_file($path)) {
					@unlink($path);
				}
			}

			@rmdir($real);
		}
	}
