<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\FileCacheInvalidator;
	use App\Content\ContentTables;
	use App\Helpers\Json;
	use DB;

	class PresentationRepository
	{
		protected static $kinds = array(
			'card' => 'Карточка',
			'compact_card' => 'Компактная карточка',
			'list' => 'Список',
			'table' => 'Таблица',
			'slider' => 'Слайдер',
			'tree' => 'Дерево',
			'page' => 'Страница материала',
			'filter' => 'Фильтры',
			'custom' => 'Пользовательское',
		);

		public static function table()
		{
			return ContentTables::table('presentations');
		}

		public static function revisionsTable()
		{
			return ContentTables::table('presentation_revisions');
		}

		public static function assignmentsTable()
		{
			return ContentTables::table('presentation_assignments');
		}

		public static function available()
		{
			return DatabaseSchema::tableExists(self::table())
				&& DatabaseSchema::tableExists(self::revisionsTable())
				&& DatabaseSchema::tableExists(self::assignmentsTable());
		}

		public static function kinds()
		{
			return self::$kinds;
		}

		public static function all($query = '', $kind = '', $state = '')
		{
			if (!self::available()) { return array(); }
			$sql = 'SELECT p.*,(SELECT COUNT(*) FROM ' . self::assignmentsTable()
				. ' a WHERE a.presentation_id=p.id) usage_count FROM ' . self::table() . ' p WHERE 1=1';
			$args = array();
			$query = trim((string) $query);
			if ($query !== '') {
				$sql .= ' AND (p.title LIKE %ss OR p.code LIKE %ss OR p.id=%i)';
				$args = array($query, $query, (int) $query);
			}

			if (isset(self::$kinds[$kind])) { $sql .= ' AND p.kind=%s'; $args[] = $kind; }
			if ($state === 'published') { $sql .= ' AND p.is_published=1'; }
			if ($state === 'draft') { $sql .= ' AND p.is_published=0'; }
			if ($state === 'changed') {
				$sql .= ' AND (p.is_published=0 OR p.draft_item_markup!=p.published_item_markup'
					. ' OR p.draft_wrapper_markup!=p.published_wrapper_markup'
					. ' OR p.draft_empty_markup!=p.published_empty_markup OR p.draft_css!=p.published_css)';
			}

			$sql .= ' ORDER BY p.updated_at DESC,p.id DESC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			return array_map(function ($row) { return self::format($row, false); }, $rows);
		}

		public static function one($id)
		{
			if (!self::available()) { return null; }
			$row = DB::query(
				'SELECT p.*,(SELECT COUNT(*) FROM ' . self::assignmentsTable()
					. ' a WHERE a.presentation_id=p.id) usage_count FROM ' . self::table() . ' p WHERE p.id=%i LIMIT 1',
				(int) $id
			)->getAssoc();
			return $row ? self::format($row, true) : null;
		}

		public static function raw($id)
		{
			if (!self::available()) { return null; }
			return DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc() ?: null;
		}

		public static function runtime($id)
		{
			if (!self::available()) { return null; }
			$row = DB::query(
				'SELECT id presentation_id,code presentation_code,kind presentation_kind,version presentation_version,'
					. ' is_published,published_item_markup,published_wrapper_markup,published_empty_markup,published_css,published_settings_json'
					. ' FROM ' . self::table() . ' WHERE id=%i LIMIT 1',
				(int) $id
			)->getAssoc();
			if (!$row || empty($row['is_published'])) { return null; }
			$row['id'] = 0;
			$row['mode'] = 'native';
			$row['presentation_id'] = (int) $row['presentation_id'];
			$row['presentation_version'] = (int) $row['presentation_version'];
			$row['is_published'] = true;
			$row['settings'] = Json::toArray((string) $row['published_settings_json'], array());
			return $row;
		}

		public static function stats()
		{
			$items = self::all();
			return array(
				'total' => count($items),
				'published' => count(array_filter($items, function ($item) { return $item['is_published']; })),
				'changed' => count(array_filter($items, function ($item) { return $item['has_changes']; })),
				'used' => count(array_filter($items, function ($item) { return $item['usage_count'] > 0; })),
			);
		}

		public static function save($id, array $input, $authorId)
		{
			if (!self::available()) { throw new \RuntimeException('Обновите схему ядра'); }
			$id = (int) $id;
			$data = self::input($input, $id);
			$data['author_id'] = (int) $authorId;
			$data['updated_at'] = time();
			if ($id > 0) {
				if (!self::raw($id)) { throw new \RuntimeException('Представление не найдено'); }
				DB::Update(self::table(), $data, 'id=%i', $id);
				PresentationRevisionRepository::capture($id, 'update', $authorId, 'Сохранение черновика');
				return $id;
			}

			$data += array(
				'published_item_markup' => '',
				'published_wrapper_markup' => '',
				'published_empty_markup' => '',
				'published_css' => '',
				'published_settings_json' => '{}',
				'is_published' => 0,
				'version' => 0,
				'created_at' => time(),
				'published_at' => 0,
			);
			DB::Insert(self::table(), $data);
			$id = (int) DB::insertId();
			PresentationRevisionRepository::capture($id, 'create', $authorId, 'Создание представления');
			return $id;
		}

		public static function publish($id, $authorId)
		{
			$row = self::raw((int) $id);
			if (!$row) { throw new \RuntimeException('Представление не найдено'); }
			if (trim((string) $row['draft_item_markup']) === '') {
				throw new \InvalidArgumentException('Заполните шаблон элемента');
			}

			DB::Update(self::table(), array(
				'published_item_markup' => (string) $row['draft_item_markup'],
				'published_wrapper_markup' => (string) $row['draft_wrapper_markup'],
				'published_empty_markup' => (string) $row['draft_empty_markup'],
				'published_css' => (string) $row['draft_css'],
				'published_settings_json' => (string) $row['draft_settings_json'],
				'is_published' => 1,
				'version' => max(1, (int) $row['version'] + 1),
				'author_id' => (int) $authorId,
				'updated_at' => time(),
				'published_at' => time(),
			), 'id=%i', (int) $id);
			PresentationRevisionRepository::capture((int) $id, 'publish', $authorId, 'Публикация представления');
			FileCacheInvalidator::publicPresentation();
			return self::one((int) $id);
		}

		public static function copy($id, $authorId)
		{
			$row = self::one((int) $id);
			if (!$row) { throw new \RuntimeException('Представление не найдено'); }
			$base = $row['code'] . '_copy';
			$code = $base;
			$index = 2;
			while (DB::query('SELECT id FROM ' . self::table() . ' WHERE code=%s LIMIT 1', $code)->getValue()) {
				$code = $base . '_' . $index++;
			}

			$row['title'] .= ' (копия)';
			$row['code'] = $code;
			return self::save(0, $row, $authorId);
		}

		public static function delete($id)
		{
			$row = self::one((int) $id);
			if (!$row) { return false; }
			if ($row['usage_count'] > 0) { throw new \RuntimeException('Сначала удалите назначения представления'); }
			DB::Delete(self::revisionsTable(), 'presentation_id=%i', (int) $id);
			DB::Delete(self::table(), 'id=%i', (int) $id);
			FileCacheInvalidator::publicPresentation();
			return true;
		}

		public static function snapshot(array $row)
		{
			$keys = array(
				'title', 'code', 'kind', 'description', 'draft_item_markup', 'draft_wrapper_markup',
				'draft_empty_markup', 'draft_css', 'draft_settings_json', 'published_item_markup',
				'published_wrapper_markup', 'published_empty_markup', 'published_css',
				'published_settings_json', 'is_published', 'version',
			);
			$out = array('id' => isset($row['id']) ? (int) $row['id'] : 0);
			foreach ($keys as $key) { $out[$key] = isset($row[$key]) ? $row[$key] : ''; }
			return $out;
		}

		public static function applySnapshot($id, array $snapshot, $authorId)
		{
			$current = self::raw((int) $id);
			if (!$current) { throw new \RuntimeException('Представление не найдено'); }
			$snapshot['title'] = isset($snapshot['title']) ? $snapshot['title'] : $current['title'];
			$snapshot['code'] = isset($snapshot['code']) ? $snapshot['code'] : $current['code'];
			$data = self::input($snapshot, (int) $id);
			$data['author_id'] = (int) $authorId;
			$data['updated_at'] = time();
			DB::Update(self::table(), $data, 'id=%i', (int) $id);
			return (int) $id;
		}

		protected static function input(array $input, $id)
		{
			$title = trim(isset($input['title']) ? (string) $input['title'] : '');
			$code = strtolower(trim(isset($input['code']) ? (string) $input['code'] : ''));
			$kind = strtolower(trim(isset($input['kind']) ? (string) $input['kind'] : 'card'));
			if ($title === '') { throw new \InvalidArgumentException('Укажите название представления'); }
			if (!isset(self::$kinds[$kind])) { throw new \InvalidArgumentException('Выберите вид представления'); }
			if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $code)) {
				throw new \InvalidArgumentException('Код: латиница, цифры, дефис или подчёркивание');
			}

			if (DB::query('SELECT id FROM ' . self::table() . ' WHERE code=%s AND id!=%i LIMIT 1', $code, (int) $id)->getValue()) {
				throw new \InvalidArgumentException('Такой код уже используется');
			}

			$settings = isset($input['draft_settings_json']) ? Json::toArray((string) $input['draft_settings_json']) : array();
			$markup = array(
				'Элемент' => isset($input['draft_item_markup']) ? (string) $input['draft_item_markup'] : '',
				'Обёртка' => isset($input['draft_wrapper_markup']) ? (string) $input['draft_wrapper_markup'] : '',
				'Пустой результат' => isset($input['draft_empty_markup']) ? (string) $input['draft_empty_markup'] : '',
			);
			$syntax = PresentationSyntax::checkMany($markup);
			if (!$syntax['ok']) {
				throw new \InvalidArgumentException($syntax['message']);
			}

			return array(
				'title' => $title,
				'code' => $code,
				'kind' => $kind,
				'description' => trim(isset($input['description']) ? (string) $input['description'] : ''),
				'draft_item_markup' => $markup['Элемент'],
				'draft_wrapper_markup' => $markup['Обёртка'],
				'draft_empty_markup' => $markup['Пустой результат'],
				'draft_css' => isset($input['draft_css']) ? (string) $input['draft_css'] : '',
				'draft_settings_json' => Json::encode(is_array($settings) ? $settings : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
		}

		protected static function format(array $row, $withCode)
		{
			$row['id'] = (int) $row['id'];
			$row['version'] = (int) $row['version'];
			$row['usage_count'] = isset($row['usage_count']) ? (int) $row['usage_count'] : 0;
			$row['is_published'] = !empty($row['is_published']);
			$row['kind_label'] = isset(self::$kinds[$row['kind']]) ? self::$kinds[$row['kind']] : $row['kind'];
			$row['has_changes'] = !$row['is_published']
				|| (string) $row['draft_item_markup'] !== (string) $row['published_item_markup']
				|| (string) $row['draft_wrapper_markup'] !== (string) $row['published_wrapper_markup']
				|| (string) $row['draft_empty_markup'] !== (string) $row['published_empty_markup']
				|| (string) $row['draft_css'] !== (string) $row['published_css']
				|| (string) $row['draft_settings_json'] !== (string) $row['published_settings_json'];
			$row['updated_label'] = !empty($row['updated_at']) ? date('d.m.Y H:i', (int) $row['updated_at']) : '—';
			if (!$withCode) {
				foreach (array_keys($row) as $key) {
					if (strpos($key, '_markup') !== false || strpos($key, '_css') !== false || strpos($key, '_settings_json') !== false) {
						unset($row[$key]);
					}
				}
			}

			return $row;
		}
	}
