<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationAssignmentRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\FileCacheInvalidator;
	use App\Helpers\Json;
	use DB;

	class PresentationAssignmentRepository
	{
		protected static $runtime = array();

		public static function all($presentationId)
		{
			if (!PresentationRepository::available()) { return array(); }
			$rows = DB::query(
				'SELECT * FROM ' . PresentationRepository::assignmentsTable()
					. ' WHERE presentation_id=%i ORDER BY context_code,target_type,target_key',
				(int) $presentationId
			)->getAll() ?: array();
			return array_map(array(self::class, 'format'), $rows);
		}

		public static function save($presentationId, array $input, $authorId)
		{
			if (!PresentationRepository::one((int) $presentationId)) { throw new \RuntimeException('Представление не найдено'); }
			$context = strtolower(trim(isset($input['context_code']) ? (string) $input['context_code'] : ''));
			$type = strtolower(trim(isset($input['target_type']) ? (string) $input['target_type'] : ''));
			$key = strtolower(trim(isset($input['target_key']) ? (string) $input['target_key'] : '0'));
			$mode = strtolower(trim(isset($input['mode']) ? (string) $input['mode'] : 'legacy'));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $context)) { throw new \InvalidArgumentException('Укажите код контекста'); }
			if (!in_array($type, array('global', 'catalog', 'request', 'rubric', 'module'), true)) { throw new \InvalidArgumentException('Выберите тип назначения'); }
			if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,95}$/', $key)) { throw new \InvalidArgumentException('Некорректная цель назначения'); }
			if (!in_array($mode, array('legacy', 'preview', 'native'), true)) { $mode = 'legacy'; }
			if ($mode === 'native') {
				$presentation = PresentationRepository::one((int) $presentationId);
				if (!$presentation || !$presentation['is_published']) {
					throw new \InvalidArgumentException('Сначала опубликуйте представление');
				}
			}

			$existing = DB::query(
				'SELECT id FROM ' . PresentationRepository::assignmentsTable()
					. ' WHERE context_code=%s AND target_type=%s AND target_key=%s LIMIT 1',
				$context,
				$type,
				$key
			)->getValue();
			$data = array(
				'presentation_id' => (int) $presentationId,
				'context_code' => $context,
				'target_type' => $type,
				'target_key' => $key,
				'mode' => $mode,
				'settings_json' => Json::encode(array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'author_id' => (int) $authorId,
				'updated_at' => time(),
			);
			if ($existing) {
				DB::Update(PresentationRepository::assignmentsTable(), $data, 'id=%i', (int) $existing);
				$id = (int) $existing;
			} else {
				DB::Insert(PresentationRepository::assignmentsTable(), $data);
				$id = (int) DB::insertId();
			}

			self::resetRuntime();
			FileCacheInvalidator::publicPresentation();
			return $id;
		}

		public static function delete($id)
		{
			$deleted = DB::Delete(PresentationRepository::assignmentsTable(), 'id=%i', (int) $id);
			self::resetRuntime();
			FileCacheInvalidator::publicPresentation();
			return $deleted;
		}

		/**
		 * Returns the most specific assignment, including legacy/preview entries.
		 * A specific legacy assignment deliberately blocks a broader native one.
		 */
		public static function resolve($contextCode, array $targets = array())
		{
			if (!PresentationRepository::available()) { return null; }
			$contextCode = strtolower(trim((string) $contextCode));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $contextCode)) { return null; }
			if (!array_key_exists($contextCode, self::$runtime)) {
				$rows = DB::query(
					'SELECT a.*,p.code presentation_code,p.kind presentation_kind,p.version presentation_version,'
						. ' p.is_published,p.published_item_markup,p.published_wrapper_markup,'
						. ' p.published_empty_markup,p.published_css,p.published_settings_json'
						. ' FROM ' . PresentationRepository::assignmentsTable() . ' a'
						. ' INNER JOIN ' . PresentationRepository::table() . ' p ON p.id=a.presentation_id'
						. ' WHERE a.context_code=%s',
					$contextCode
				)->getAll() ?: array();
				self::$runtime[$contextCode] = array();
				foreach ($rows as $row) {
					$key = strtolower((string) $row['target_type']) . ':' . strtolower((string) $row['target_key']);
					self::$runtime[$contextCode][$key] = self::formatRuntime($row);
				}
			}

			foreach (self::normalizeTargets($targets) as $target) {
				$key = $target['type'] . ':' . $target['key'];
				if (isset(self::$runtime[$contextCode][$key])) {
					return self::$runtime[$contextCode][$key];
				}
			}

			return isset(self::$runtime[$contextCode]['global:0'])
				? self::$runtime[$contextCode]['global:0']
				: null;
		}

		public static function resetRuntime()
		{
			self::$runtime = array();
		}

		public static function format($row)
		{
			$row = (array) $row;
			$row['id'] = (int) $row['id'];
			$row['presentation_id'] = (int) $row['presentation_id'];
			$row['updated_label'] = !empty($row['updated_at']) ? date('d.m.Y H:i', (int) $row['updated_at']) : '—';
			$labels = array('legacy' => 'Текущий вывод', 'preview' => 'Подготовка', 'native' => 'Новое представление');
			$row['mode_label'] = isset($labels[$row['mode']]) ? $labels[$row['mode']] : (string) $row['mode'];
			return $row;
		}

		protected static function normalizeTargets(array $targets)
		{
			$normalized = array();
			foreach ($targets as $type => $key) {
				if (is_array($key)) {
					$type = isset($key['type']) ? $key['type'] : '';
					$key = isset($key['key']) ? $key['key'] : '';
				}

				$type = strtolower(trim((string) $type));
				$key = strtolower(trim((string) $key));
				if (!in_array($type, array('catalog', 'request', 'rubric', 'module'), true)) { continue; }
				if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,95}$/', $key)) { continue; }
				$normalized[$type . ':' . $key] = array('type' => $type, 'key' => $key);
			}

			return array_values($normalized);
		}

		protected static function formatRuntime(array $row)
		{
			$row['id'] = (int) $row['id'];
			$row['presentation_id'] = (int) $row['presentation_id'];
			$row['presentation_version'] = (int) $row['presentation_version'];
			$row['is_published'] = !empty($row['is_published']);
			$row['settings'] = Json::toArray(isset($row['published_settings_json'])
				? (string) $row['published_settings_json']
				: '{}');
			if (!is_array($row['settings'])) { $row['settings'] = array(); }
			return $row;
		}
	}
