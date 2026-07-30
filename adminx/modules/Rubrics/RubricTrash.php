<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/RubricTrash.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Helpers\Json;
	use DB;

	/**
	 * Корзина рубрик: полный обратимый снимок рубрики со всей её схемой.
	 *
	 * Удаление рубрики разрешено только для пустой рубрики без зависимостей
	 * (см. Model::deleteRubric), поэтому на её строки, поля и группы ничто не
	 * ссылается. Это позволяет честно убрать рубрику из активных таблиц (никакие
	 * списки/пикеры фильтровать не нужно), а рядом сохранить слепок для
	 * восстановления. Идентификаторы автоинкрементные — MySQL не переиспользует
	 * освободившиеся, поэтому восстановление с исходными id безопасно.
	 */
	class RubricTrash
	{
		const VERSION = 1;

		public static function table()
		{
			return ContentTables::table('rubric_trash');
		}

		public static function available()
		{
			try { return DatabaseSchema::tableExists(self::table()); }
			catch (\Throwable $e) { return false; }
		}

		/**
		 * Логические таблицы рубрики в порядке восстановления (родитель первым).
		 * Ключ — это столбец связи с рубрикой; для самой таблицы рубрик это Id.
		 */
		protected static function tables()
		{
			return array(
				array('name' => 'rubric', 'table' => Model::rubricsTable(), 'key' => 'Id'),
				array('name' => 'group', 'table' => Model::groupsTable(), 'key' => 'rubric_id'),
				array('name' => 'field', 'table' => Model::fieldsTable(), 'key' => 'rubric_id'),
				array('name' => 'template', 'table' => Model::templatesTable(), 'key' => 'rubric_id'),
				array('name' => 'permission', 'table' => Model::permissionsTable(), 'key' => 'rubric_id'),
				array('name' => 'view', 'table' => AdminView::table(), 'key' => 'rubric_id'),
				array('name' => 'fieldset_link', 'table' => RubricFieldSetLinks::linksTable(), 'key' => 'rubric_id'),
				array('name' => 'revision', 'table' => RubricRevisions::table(), 'key' => 'rubric_id'),
			);
		}

		/**
		 * Снять полный слепок рубрики в корзину. Вызывается внутри транзакции
		 * удаления, до каскадных DELETE. Возвращает id записи корзины или 0.
		 */
		public static function capture($rubricId, $authorId = 0)
		{
			$rubricId = (int) $rubricId;
			if ($rubricId <= 0 || !self::available()) {
				return 0;
			}

			$rubricRow = DB::query('SELECT * FROM ' . Model::rubricsTable() . ' WHERE Id=%i LIMIT 1', $rubricId)->getAssoc();
			if (!$rubricRow) {
				return 0;
			}

			$payload = array('version' => self::VERSION, 'rubric_id' => $rubricId, 'tables' => array());
			$fieldsCount = 0;
			$groupsCount = 0;
			foreach (self::tables() as $definition) {
				if (!self::tableReady($definition['table'])) {
					continue;
				}

				$rows = DB::query(
					'SELECT * FROM ' . $definition['table'] . ' WHERE ' . $definition['key'] . '=%i',
					$rubricId
				)->getAll() ?: array();
				$payload['tables'][$definition['name']] = $rows;
				if ($definition['name'] === 'field') { $fieldsCount = count($rows); }
				if ($definition['name'] === 'group') { $groupsCount = count($rows); }
			}

			$json = Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (!is_string($json)) {
				throw new \RuntimeException('Не удалось сохранить рубрику в корзину');
			}

			DB::Insert(self::table(), array(
				'rubric_id' => $rubricId,
				'rubric_title' => mb_substr((string) (isset($rubricRow['rubric_title']) ? $rubricRow['rubric_title'] : ''), 0, 255, 'UTF-8'),
				'rubric_alias' => mb_substr((string) (isset($rubricRow['rubric_alias']) ? $rubricRow['rubric_alias'] : ''), 0, 255, 'UTF-8'),
				'fields_count' => $fieldsCount,
				'groups_count' => $groupsCount,
				'payload_json' => $json,
				'author_id' => (int) $authorId,
				'author_name' => self::authorName((int) $authorId),
				'deleted_at' => time(),
			));

			return (int) DB::insertId();
		}

		public static function listing($limit = 100)
		{
			if (!self::available()) {
				return array();
			}

			$rows = DB::query(
				'SELECT id,rubric_id,rubric_title,rubric_alias,fields_count,groups_count,author_id,author_name,deleted_at'
					. ' FROM ' . self::table() . ' ORDER BY deleted_at DESC,id DESC LIMIT %i',
				max(1, min(500, (int) $limit))
			)->getAll() ?: array();

			return array_map(array(__CLASS__, 'formatRow'), $rows);
		}

		public static function one($id)
		{
			if (!self::available()) {
				return null;
			}

			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::formatRow($row) : null;
		}

		public static function count()
		{
			if (!self::available()) {
				return 0;
			}

			return (int) DB::query('SELECT COUNT(*) FROM ' . self::table())->getValue();
		}

		/**
		 * Восстановить рубрику из корзины: реинсертить все строки с исходными id
		 * и убрать запись корзины. Бросает исключение при конфликте id.
		 */
		public static function restore($trashId)
		{
			if (!self::available()) {
				throw new \RuntimeException('Корзина недоступна');
			}

			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id=%i LIMIT 1', (int) $trashId)->getAssoc();
			if (!$row) {
				throw new \RuntimeException('Запись корзины не найдена');
			}

			$payload = Json::toArray((string) $row['payload_json']);
			$rubricId = (int) (isset($payload['rubric_id']) ? $payload['rubric_id'] : $row['rubric_id']);
			$stored = isset($payload['tables']) && is_array($payload['tables']) ? $payload['tables'] : array();
			if ($rubricId <= 0 || empty($stored['rubric'])) {
				throw new \RuntimeException('Слепок рубрики повреждён');
			}

			if (DB::query('SELECT Id FROM ' . Model::rubricsTable() . ' WHERE Id=%i LIMIT 1', $rubricId)->getValue()) {
				throw new \RuntimeException('Рубрика с id #' . $rubricId . ' уже существует — восстановление невозможно');
			}

			DB::startTransaction();
			try {
				foreach (self::tables() as $definition) {
					$rows = isset($stored[$definition['name']]) && is_array($stored[$definition['name']])
						? $stored[$definition['name']]
						: array();
					if (!$rows || !self::tableReady($definition['table'])) {
						continue;
					}

					foreach ($rows as $record) {
						if (is_array($record) && $record) {
							DB::Insert($definition['table'], $record);
						}
					}
				}

				DB::Delete(self::table(), 'id=%i', (int) $trashId);
				DB::commit();
			} catch (\Throwable $e) {
				DB::rollback();
				throw $e;
			}

			Model::clearRubricCachePublic($rubricId);
			return $rubricId;
		}

		public static function purge($trashId)
		{
			if (!self::available()) {
				return false;
			}

			$exists = (int) DB::query('SELECT COUNT(*) FROM ' . self::table() . ' WHERE id=%i', (int) $trashId)->getValue();
			if ($exists < 1) {
				return false;
			}

			DB::Delete(self::table(), 'id=%i', (int) $trashId);
			return true;
		}

		protected static function formatRow(array $row)
		{
			$deleted = isset($row['deleted_at']) ? (int) $row['deleted_at'] : 0;
			return array(
				'id' => (int) $row['id'],
				'rubric_id' => (int) $row['rubric_id'],
				'rubric_title' => (string) (isset($row['rubric_title']) ? $row['rubric_title'] : ''),
				'rubric_alias' => (string) (isset($row['rubric_alias']) ? $row['rubric_alias'] : ''),
				'fields_count' => (int) (isset($row['fields_count']) ? $row['fields_count'] : 0),
				'groups_count' => (int) (isset($row['groups_count']) ? $row['groups_count'] : 0),
				'author_id' => (int) (isset($row['author_id']) ? $row['author_id'] : 0),
				'author_name' => (string) (isset($row['author_name']) ? $row['author_name'] : ''),
				'deleted_at' => $deleted,
				'deleted_label' => $deleted > 0 ? date('d.m.Y H:i:s', $deleted) : '-',
			);
		}

		protected static function tableReady($table)
		{
			try { return DatabaseSchema::tableExists($table); }
			catch (\Throwable $e) { return false; }
		}

		protected static function authorName($id)
		{
			if ((int) $id <= 0) { return ''; }
			$row = DB::query('SELECT name,login,email FROM ' . SystemTables::table('users') . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			if (!$row) { return '#' . (int) $id; }
			if (!empty($row['name'])) { return (string) $row['name']; }
			if (!empty($row['login'])) { return '@' . (string) $row['login']; }

			return !empty($row['email']) ? (string) $row['email'] : '#' . (int) $id;
		}
	}
