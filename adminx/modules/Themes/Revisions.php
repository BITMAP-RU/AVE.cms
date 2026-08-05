<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Themes/Revisions.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Themes;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use DB;

	class Revisions
	{
		public static function table()
		{
			return SystemTables::table('theme_asset_revisions');
		}

		public static function capture($theme, $path, $action, $content, $authorId, $sourceRevisionId)
		{
			$content = (string) $content;
			$checksum = hash('sha256', $content);
			$last = DB::query(
				'SELECT checksum,action FROM ' . self::table() . ' WHERE theme=%s AND asset_path=%s ORDER BY created_at DESC,id DESC LIMIT 1',
				(string) $theme, (string) $path
			)->getAssoc();
			if ($last && (string) $last['checksum'] === $checksum && (string) $last['action'] === (string) $action) { return 0; }
			DB::Insert(self::table(), array(
				'theme' => (string) $theme, 'asset_path' => (string) $path, 'action' => (string) $action,
				'checksum' => $checksum, 'content' => $content, 'content_size' => strlen($content),
				'author_id' => (int) $authorId, 'author_name' => self::authorName($authorId),
				'source_revision_id' => (int) $sourceRevisionId, 'created_at' => time(),
			));
			return (int) DB::insertId();
		}

		public static function listing($theme, $path = '', $limit = 200)
		{
			$sql = 'SELECT id,theme,asset_path,action,checksum,content_size,author_id,author_name,source_revision_id,created_at FROM ' . self::table() . ' WHERE theme=%s';
			$args = array((string) $theme);
			if ((string) $path !== '') { $sql .= ' AND asset_path=%s'; $args[] = (string) $path; }
			$sql .= ' ORDER BY created_at DESC,id DESC LIMIT %i';
			$args[] = max(1, min(500, (int) $limit));
			array_unshift($args, $sql);
			$rows = call_user_func_array(array(DB::class, 'query'), $args)->getAll() ?: array();
			return array_map(array(__CLASS__, 'format'), $rows);
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) { return null; }
			$row = self::format($row);
			$row['content'] = (string) $row['raw']['content'];
			try {
				$current = Model::file($row['theme'], $row['path']);
				$row['current_exists'] = true;
				$row['changed'] = (string) $current['content'] !== $row['content'];
			} catch (\Throwable $e) {
				$row['current_exists'] = false;
				$row['changed'] = true;
			}

			unset($row['raw']);
			return $row;
		}

		public static function restore($id, $authorId)
		{
			$revision = self::one($id);
			if (!$revision) { throw new \RuntimeException('Ревизия не найдена'); }
			return Model::saveFile($revision['theme'], $revision['path'], $revision['content'], $authorId, 'restore', $revision['id']);
		}

		public static function delete($id)
		{
			$exists = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE id=%i', (int) $id)->getValue();
			if ($exists < 1) { return false; }
			DB::Delete(self::table(), 'id=%i', (int) $id);
			return true;
		}

		public static function deleteForPath($theme, $path)
		{
			$count = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE theme=%s AND asset_path=%s', (string) $theme, (string) $path)->getValue();
			DB::Delete(self::table(), 'theme=%s AND asset_path=%s', (string) $theme, (string) $path);
			return $count;
		}

		public static function deleteTheme($theme)
		{
			$count = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE theme=%s', (string) $theme)->getValue();
			DB::Delete(self::table(), 'theme=%s', (string) $theme);
			return $count;
		}

		protected static function format(array $row)
		{
			$labels = array(
				'create' => array('Создание', 'badge-green'), 'upload' => array('Загрузка', 'badge-cyan'),
				'update' => array('Сохранение', 'badge-blue'), 'manifest' => array('Реестр', 'badge-violet'),
				'backup' => array('Предыдущая версия', 'badge-gray'), 'restore' => array('Восстановление', 'badge-amber'),
				'delete' => array('Удаление', 'badge-red'), 'import' => array('Импорт', 'badge-cyan'),
			);
			$action = isset($row['action']) ? (string) $row['action'] : 'update';
			$label = isset($labels[$action]) ? $labels[$action] : array($action, 'badge-gray');
			$created = isset($row['created_at']) ? (int) $row['created_at'] : 0;
			return array(
				'id' => (int) $row['id'], 'theme' => (string) $row['theme'], 'path' => (string) $row['asset_path'],
				'action' => $action, 'action_label' => $label[0], 'badge' => $label[1],
				'checksum' => (string) $row['checksum'], 'size' => (int) $row['content_size'],
				'size_label' => Model::formatBytes((int) $row['content_size']), 'author_id' => (int) $row['author_id'],
				'author_name' => (string) $row['author_name'], 'source_revision_id' => (int) $row['source_revision_id'],
				'created_at' => $created, 'created_label' => $created ? date('d.m.Y H:i:s', $created) : '-', 'raw' => $row,
			);
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) { return ''; }
			$row = DB::query('SELECT name,login,email FROM ' . SystemTables::table('users') . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) { return '#' . (int) $id; }
			return !empty($row['name']) ? (string) $row['name'] : (!empty($row['login']) ? '@' . $row['login'] : (string) $row['email']);
		}
	}
