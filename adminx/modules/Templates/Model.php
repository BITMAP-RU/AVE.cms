<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Common\FileCacheInvalidator;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\Templates\TemplateMutationService;
	use App\Frontend\PageTemplateRepository;
	use App\Helpers\File;

	class Model
	{
		public static function table()
		{
			return ContentTables::table('templates');
		}

		public static function rubricsTable()
		{
			return ContentTables::table('rubrics');
		}

		public static function all($q = '')
		{
			$sql = 'SELECT t.*, u.name AS author_name, u.login AS author_login, u.email AS author_email'
				. ', ' . self::usedCountExpression() . ' AS used_count'
				. ' FROM ' . self::table() . ' t'
				. ' LEFT JOIN ' . SystemTables::table('users') . ' u ON u.id = t.template_author_id'
				. ' WHERE 1=1';
			$args = array();
			$q = trim((string) $q);
			if ($q !== '') {
				$sql .= ' AND (t.template_title LIKE %ss OR t.template_text LIKE %ss OR t.Id = %i)';
				$args[] = $q;
				$args[] = $q;
				$args[] = (int) $q;
			}

			$sql .= ' ORDER BY t.Id ASC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();
			$out = array();
			foreach ($rows as $row) {
				$out[] = self::row($row);
			}

			return $out;
		}

		public static function one($id)
		{
			$row = DB::query(
				'SELECT t.*, u.name AS author_name, u.login AS author_login, u.email AS author_email'
				. ', ' . self::usedCountExpression() . ' AS used_count'
				. ' FROM ' . self::table() . ' t'
				. ' LEFT JOIN ' . SystemTables::table('users') . ' u ON u.id = t.template_author_id'
				. ' WHERE t.Id = %i LIMIT 1',
				(int) $id
			)->getAssoc();
			return $row ? self::row($row, true) : null;
		}

		public static function raw($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE Id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? $row : null;
		}

		public static function revisionFields()
		{
			return array('Id', 'template_title', 'template_text', 'template_author_id', 'template_created');
		}

		public static function snapshot(array $row)
		{
			$out = array();
			foreach (self::revisionFields() as $field) {
				$out[$field] = isset($row[$field]) ? $row[$field] : '';
			}

			$out['Id'] = (int) $out['Id'];
			$out['template_author_id'] = (int) $out['template_author_id'];
			$out['template_created'] = (int) $out['template_created'];
			return $out;
		}

		public static function applySnapshot($id, array $snapshot, $authorId)
		{
			$id = (int) $id;
			$current = self::raw($id);
			if (!$current) {
				throw new \RuntimeException('Шаблон не найден');
			}

			$data = self::snapshot($snapshot);
			unset($data['Id']);
			$data['template_author_id'] = (int) $authorId > 0 ? (int) $authorId : (int) $data['template_author_id'];
			DB::Update(self::table(), $data, 'Id = %i', $id);
			self::writeCache($id, $data['template_text']);
			return $id;
		}

		public static function stats()
		{
			return array(
				'total' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table())->getValue(),
				'used' => self::tableExists(self::rubricsTable()) ? (int) DB::query('SELECT COUNT(DISTINCT rubric_template_id) FROM ' . self::rubricsTable())->getValue() : 0,
				'php' => (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE template_text LIKE %s', '%<?%')->getValue(),
				'bytes' => (int) DB::query('SELECT COALESCE(SUM(CHAR_LENGTH(template_text)), 0) FROM ' . self::table())->getValue(),
			);
		}

		public static function rebuildCaches()
		{
			$rows = DB::query('SELECT Id, template_text FROM ' . self::table())->getAll();
			$count = 0;
			foreach ($rows as $row) {
				self::writeCache((int) $row['Id'], (string) $row['template_text']);
				$count++;
			}

			return $count;
		}

		public static function save($id, array $input, $authorId)
		{
			$isUpdate = (int) $id > 0;
			$templateId = (new TemplateMutationService())->save((int) $id, array(
				'template_title' => isset($input['template_title']) ? $input['template_title'] : '',
				'template_text' => isset($input['template_text']) ? $input['template_text'] : '',
			), (int) $authorId);
			Revisions::capture(
				$templateId,
				$isUpdate ? 'update' : 'create',
				(int) $authorId,
				$isUpdate ? 'Сохранение шаблона' : 'Создание шаблона'
			);
			return $templateId;
		}

		public static function copy($id, $title, $authorId)
		{
			$row = self::one($id);
			if (!$row) {
				throw new \RuntimeException('Шаблон не найден');
			}

			$title = trim((string) $title);
			if ($title === '') {
				$title = $row['template_title'] . ' (копия)';
			}

			$data = array(
				'template_title' => self::uniqueTitle($title),
				'template_text' => self::encodeText((string) $row['template_text']),
				'template_author_id' => (int) $authorId > 0 ? (int) $authorId : 1,
				'template_created' => time(),
			);
			DB::Insert(self::table(), $data);
			$newId = (int) DB::insertId();
			self::writeCache($newId, $data['template_text']);
			Revisions::capture($newId, 'copy', (int) $authorId, 'Копия шаблона #' . (int) $id);
			return $newId;
		}

		public static function delete($id, $authorId = 0)
		{
			$id = (int) $id;
			if ($id === 1) {
				throw new \RuntimeException('Шаблон #1 является системным и не может быть удалён');
			}

			if (self::usedCount($id) > 0) {
				throw new \RuntimeException('Шаблон используется рубриками и не может быть удалён');
			}

			$raw = self::raw($id);
			if (!$raw) {
				return false;
			}

			Revisions::capture($id, 'delete', (int) $authorId, 'Удаление шаблона', $raw);
			return (new TemplateMutationService())->delete($id);
		}

		public static function clearCache($id)
		{
			self::removeCache((int) $id);
		}

		public static function usedCount($id)
		{
			if (!self::tableExists(self::rubricsTable())) {
				return 0;
			}

			return (int) DB::query('SELECT COUNT(*) FROM ' . self::rubricsTable() . ' WHERE rubric_template_id = %i', (int) $id)->getValue();
		}

		protected static function usedCountExpression()
		{
			if (!self::tableExists(self::rubricsTable())) {
				return '0';
			}

			return '(SELECT COUNT(*) FROM ' . self::rubricsTable() . ' r WHERE r.rubric_template_id = t.Id)';
		}

		protected static function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}

		protected static function input(array $input)
		{
			return array(
				'template_title' => trim(isset($input['template_title']) ? (string) $input['template_title'] : ''),
				'template_text' => self::encodeText(isset($input['template_text']) ? (string) $input['template_text'] : ''),
			);
		}

		protected static function row(array $row, $withText = false)
		{
			$author = '';
			if (!empty($row['author_name'])) {
				$author = (string) $row['author_name'];
			} elseif (!empty($row['author_login'])) {
				$author = '@' . (string) $row['author_login'];
			} elseif (!empty($row['author_email'])) {
				$author = (string) $row['author_email'];
			}

			$cacheText = self::readCache((int) $row['Id']);
			// Legacy templates are escaped in SQL. The editor must always return
			// the database value decoded; an old cache may still contain slashes.
			$text = self::decodeText((string) $row['template_text']);
			$row['template_text'] = $text;
			$row['Id'] = (int) $row['Id'];
			$row['id'] = (int) $row['Id'];
			$row['template_author_id'] = (int) $row['template_author_id'];
			$row['template_created'] = (int) $row['template_created'];
			$row['used_count'] = isset($row['used_count']) ? (int) $row['used_count'] : self::usedCount($row['Id']);
			$row['author'] = $author !== '' ? $author : ('#' . $row['template_author_id']);
			$row['created_label'] = $row['template_created'] > 0 ? date('d.m.Y H:i', $row['template_created']) : '-';
			$row['text_size'] = strlen($text);
			$row['text_size_label'] = self::formatBytes($row['text_size']);
			$row['has_php'] = Syntax::hasPhp($text);
			$row['cache_exists'] = $cacheText !== null;
			$row['can_delete'] = $row['Id'] !== 1 && $row['used_count'] <= 0;
			return $row;
		}

		protected static function uniqueTitle($base)
		{
			$base = trim((string) $base);
			if ($base === '') {
				$base = 'Новый шаблон';
			}

			$title = $base;
			$i = 2;
			while (DB::query('SELECT Id FROM ' . self::table() . ' WHERE template_title = %s LIMIT 1', $title)->getValue()) {
				$title = $base . ' #' . $i;
				$i++;
			}

			return $title;
		}

		protected static function cacheFile($id)
		{
			return PageTemplateRepository::cacheFilePath((int) $id);
		}

		protected static function readCache($id)
		{
			$file = self::cacheFile((int) $id);
			return is_file($file) ? File::getContent($file) : null;
		}

		protected static function writeCache($id, $text)
		{
			$file = self::cacheFile((int) $id);
			PageTemplateRepository::prepareCacheDirectory($file);
			File::putAtomic($file, self::decodeText((string) $text));
			FileCacheInvalidator::pageTemplates();
		}

		public static function decodeText($text)
		{
			return stripslashes((string) $text);
		}

		protected static function encodeText($text)
		{
			return addslashes((string) $text);
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

		protected static function removeCache($id)
		{
			$file = self::cacheFile((int) $id);
			if ($file !== null && is_file($file)) {
				File::delete($file);
			}
		}
	}
