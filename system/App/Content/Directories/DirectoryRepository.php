<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Directories/DirectoryRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Directories;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\CacheKey;
	use App\Common\DatabaseSchema;
	use App\Content\ContentTables;
	use App\Helpers\Json;
	use App\Helpers\Str;
	use DB;

	/** Lightweight shared value lists used by choice fields. */
	class DirectoryRepository
	{
		const CACHE_TTL = 3600;

		public static function all($query = '', $includeInactive = true)
		{
			if (!self::schemaReady()) {
				return array();
			}

			$query = trim((string) $query);
			$sql = 'SELECT d.*, (SELECT COUNT(*) FROM ' . self::itemsTable()
				. ' i WHERE i.directory_id = d.id) AS items_count,'
				. ' (SELECT COUNT(*) FROM ' . self::itemsTable()
				. ' i WHERE i.directory_id = d.id AND i.is_active = 1) AS active_items_count'
				. ' FROM ' . self::table() . ' d WHERE 1=1';
			$params = array();
			if (!$includeInactive) {
				$sql .= ' AND d.is_active = 1';
			}

			if ($query !== '') {
				$sql .= ' AND (d.name LIKE %ss OR d.code LIKE %ss OR d.description LIKE %ss)';
				$params = array($query, $query, $query);
			}

			$sql .= ' ORDER BY d.name ASC, d.id ASC';
			$result = empty($params)
				? DB::query($sql)
				: call_user_func_array(array(DB::class, 'query'), array_merge(array($sql), $params));

			return array_map(array(self::class, 'normalizeDirectory'), $result->getAll() ?: array());
		}

		public static function choices($includeInactive = false)
		{
			$out = array();
			foreach (self::all('', $includeInactive) as $directory) {
				$out[(string) $directory['id']] = $directory['name'];
			}

			return $out;
		}

		public static function find($id)
		{
			if (!self::schemaReady()) {
				return null;
			}

			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::normalizeDirectory($row) : null;
		}

		public static function save($id, array $input)
		{
			if (!self::schemaReady()) {
				throw new \RuntimeException('Схема справочников не установлена');
			}

			$id = (int) $id;
			$name = trim(isset($input['name']) ? (string) $input['name'] : '');
			$code = Str::machineAlias(isset($input['code']) ? $input['code'] : '');
			if ($name === '') {
				throw new \InvalidArgumentException('Укажите название справочника');
			}

			if ($code === '') {
				$code = Str::slug($name);
			}

			if ($code === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $code)) {
				throw new \InvalidArgumentException('Код: латиница, цифры, _ и -, от 2 до 64 символов');
			}

			$duplicate = DB::query(
				'SELECT id FROM ' . self::table() . ' WHERE code = %s AND id != %i LIMIT 1',
				$code,
				$id
			)->getValue();
			if ($duplicate) {
				throw new \InvalidArgumentException('Справочник с таким кодом уже существует');
			}

			$now = time();
			$data = array(
				'name' => mb_substr($name, 0, 190, 'UTF-8'),
				'code' => $code,
				'description' => trim(isset($input['description']) ? (string) $input['description'] : ''),
				'is_active' => !empty($input['is_active']) ? 1 : 0,
				'updated_at' => $now,
			);
			if ($id > 0) {
				if (!self::find($id)) {
					throw new \RuntimeException('Справочник не найден');
				}

				DB::Update(self::table(), $data, 'id = %i', $id);
			} else {
				$data['settings_json'] = '';
				$data['created_at'] = $now;
				DB::Insert(self::table(), $data);
				$id = (int) DB::insertId();
			}

			self::clear($id);
			return self::find($id);
		}

		public static function delete($id)
		{
			$id = (int) $id;
			$directory = self::find($id);
			if (!$directory) {
				return false;
			}

			if (self::usageCount($id) > 0) {
				throw new \RuntimeException('Справочник используется в полях. Сначала выберите для них другой источник.');
			}

			DB::startTransaction();
			try {
				DB::Delete(self::itemsTable(), 'directory_id = %i', $id);
				DB::Delete(self::table(), 'id = %i', $id);
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				throw $e;
			}

			self::clear($id);
			return true;
		}

		public static function items($directoryId, $includeInactive = true)
		{
			if (!self::schemaReady()) {
				return array();
			}

			$sql = 'SELECT * FROM ' . self::itemsTable() . ' WHERE directory_id = %i';
			if (!$includeInactive) {
				$sql .= ' AND is_active = 1';
			}

			$sql .= ' ORDER BY sort_order ASC, id ASC';
			$rows = DB::query($sql, (int) $directoryId)->getAll() ?: array();
			return array_map(array(self::class, 'normalizeItem'), $rows);
		}

		public static function item($id)
		{
			if (!self::schemaReady()) {
				return null;
			}

			$row = DB::query('SELECT * FROM ' . self::itemsTable() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::normalizeItem($row) : null;
		}

		public static function saveItem($directoryId, $id, array $input)
		{
			$directoryId = (int) $directoryId;
			$id = (int) $id;
			if (!self::find($directoryId)) {
				throw new \RuntimeException('Справочник не найден');
			}

			$label = trim(isset($input['label']) ? (string) $input['label'] : '');
			$key = trim(isset($input['item_key']) ? (string) $input['item_key'] : '');
			if ($label === '') {
				throw new \InvalidArgumentException('Укажите подпись значения');
			}

			if ($key === '') {
				$key = Str::slug($label);
			}

			$key = mb_substr($key, 0, 120, 'UTF-8');
			if ($key === '') {
				throw new \InvalidArgumentException('Укажите стабильный ключ значения');
			}

			if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,119}$/', $key)) {
				throw new \InvalidArgumentException('Ключ: латиница, цифры, _, -, точка или двоеточие');
			}

			$duplicate = DB::query(
				'SELECT id FROM ' . self::itemsTable()
					. ' WHERE directory_id = %i AND item_key = %s AND id != %i LIMIT 1',
				$directoryId,
				$key,
				$id
			)->getValue();
			if ($duplicate) {
				throw new \InvalidArgumentException('Такой ключ уже есть в этом справочнике');
			}

			$data = array(
				'directory_id' => $directoryId,
				'item_key' => $key,
				'label' => mb_substr($label, 0, 255, 'UTF-8'),
				'sort_order' => max(0, (int) (isset($input['sort_order']) ? $input['sort_order'] : 0)),
				'is_active' => !empty($input['is_active']) ? 1 : 0,
				'parent_id' => max(0, (int) (isset($input['parent_id']) ? $input['parent_id'] : 0)),
				'data_json' => self::normalizeData(isset($input['data_json']) ? $input['data_json'] : ''),
				'updated_at' => time(),
			);

			if ($id > 0) {
				$current = self::item($id);
				if (!$current || $current['directory_id'] !== $directoryId) {
					throw new \RuntimeException('Значение справочника не найдено');
				}

				DB::Update(self::itemsTable(), $data, 'id = %i', $id);
			} else {
				if ($data['sort_order'] === 0) {
					$data['sort_order'] = (int) DB::query(
						'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM ' . self::itemsTable() . ' WHERE directory_id = %i',
						$directoryId
					)->getValue();
				}

				$data['created_at'] = time();
				DB::Insert(self::itemsTable(), $data);
				$id = (int) DB::insertId();
			}

			self::clear($directoryId);
			return self::item($id);
		}

		public static function deleteItem($directoryId, $id)
		{
			$item = self::item($id);
			if (!$item || $item['directory_id'] !== (int) $directoryId) {
				return false;
			}

			DB::Delete(self::itemsTable(), 'id = %i AND directory_id = %i', (int) $id, (int) $directoryId);
			self::clear((int) $directoryId);
			return true;
		}

		public static function reorderItems($directoryId, array $ids)
		{
			$directoryId = (int) $directoryId;
			$position = 10;
			foreach ($ids as $id) {
				DB::Update(
					self::itemsTable(),
					array('sort_order' => $position, 'updated_at' => time()),
					'id = %i AND directory_id = %i',
					(int) $id,
					$directoryId
				);
				$position += 10;
			}

			self::clear($directoryId);
		}

		public static function optionMap($directoryId, $includeInactive = false)
		{
			$directoryId = (int) $directoryId;
			if ($directoryId < 1 || !self::schemaReady()) {
				return array();
			}

			$key = 'content.directory.options.' . $directoryId . '.' . ($includeInactive ? 'all' : 'active');
			return Cache::rememberTagged(
				$key,
				self::CACHE_TTL,
				array(CacheKey::tag('directory'), CacheKey::tag('directory', $directoryId)),
				function () use ($directoryId, $includeInactive) {
					$out = array();
					foreach (self::items($directoryId, $includeInactive) as $item) {
						$out[(string) $item['item_key']] = (string) $item['label'];
					}

					return $out;
				}
			);
		}

		public static function usageCount($directoryId)
		{
			$directoryId = (int) $directoryId;
			if ($directoryId < 1) {
				return 0;
			}

			$usage = self::usageMap();
			return isset($usage[$directoryId]) ? (int) $usage[$directoryId] : 0;
		}

		public static function usageMap()
		{
			$rows = DB::query(
				'SELECT rubric_field_settings FROM ' . ContentTables::table('rubric_fields')
					. " WHERE rubric_field_settings LIKE %ss",
				'"option_source":"directory"'
			)->getAll() ?: array();
			$usage = array();
			foreach ($rows as $row) {
				$settings = Json::toArray((string) $row['rubric_field_settings'], array());
				$id = isset($settings['directory_id']) ? (int) $settings['directory_id'] : 0;
				if ($id > 0) {
					$usage[$id] = isset($usage[$id]) ? $usage[$id] + 1 : 1;
				}
			}

			return $usage;
		}

		public static function schemaReady()
		{
			static $ready = null;
			if ($ready === null) {
				$ready = DatabaseSchema::tableExists(self::table())
					&& DatabaseSchema::tableExists(self::itemsTable());
			}

			return $ready;
		}

		public static function table()
		{
			return ContentTables::table('directories');
		}

		public static function itemsTable()
		{
			return ContentTables::table('directory_items');
		}

		public static function clear($directoryId = 0)
		{
			Cache::forgetTag(CacheKey::tag('directory'));
			if ((int) $directoryId > 0) {
				Cache::forgetTag(CacheKey::tag('directory', (int) $directoryId));
			}
		}

		public static function normalizeDirectory($row)
		{
			$row = (array) $row;
			foreach (array('id', 'is_active', 'created_at', 'updated_at', 'items_count', 'active_items_count') as $key) {
				$row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
			}

			$row['name'] = isset($row['name']) ? (string) $row['name'] : '';
			$row['code'] = isset($row['code']) ? (string) $row['code'] : '';
			$row['description'] = isset($row['description']) ? (string) $row['description'] : '';
			$row['is_active'] = $row['is_active'] === 1;
			return $row;
		}

		public static function normalizeItem($row)
		{
			$row = (array) $row;
			foreach (array('id', 'directory_id', 'sort_order', 'is_active', 'parent_id', 'created_at', 'updated_at') as $key) {
				$row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
			}

			$row['item_key'] = isset($row['item_key']) ? (string) $row['item_key'] : '';
			$row['label'] = isset($row['label']) ? (string) $row['label'] : '';
			$row['data_json'] = isset($row['data_json']) ? (string) $row['data_json'] : '';
			$row['is_active'] = $row['is_active'] === 1;
			return $row;
		}

		protected static function normalizeData($raw)
		{
			$raw = trim((string) $raw);
			if ($raw === '') {
				return '';
			}

			$decoded = json_decode($raw, true);
			if (!is_array($decoded)) {
				throw new \InvalidArgumentException('Дополнительные данные должны быть корректным JSON-объектом');
			}

			return Json::encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}
