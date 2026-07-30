<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/PresentationModel.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\ModuleManager;
	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use App\Content\Documents\DocumentPickerRepository;
	use App\Content\Presentation\PresentationAssignmentRepository;
	use App\Content\Presentation\PresentationPreview;
	use App\Content\Presentation\PresentationRepository;
	use App\Content\Presentation\PresentationSyntax;
	use DB;

	class PresentationModel
	{
		protected static $targets;

		public static function documents($limit = 100)
		{
			return (new DocumentPickerRepository())->search('', array(), max(1, min(100, (int) $limit)));
		}

		public static function legacySources()
		{
			$sources = array();
			self::appendLegacy(
				$sources,
				CatalogTables::table('catalog_card_templates'),
				'Товарные карточки',
				'/catalog/card-templates',
				'ti-layout-cards'
			);
			self::appendLegacy(
				$sources,
				CatalogTables::table('catalog_filter_templates'),
				'Фильтры товарного каталога',
				'/catalog/filter-templates',
				'ti-adjustments-horizontal'
			);
			return $sources;
		}

		public static function assignmentTargets()
		{
			if (self::$targets !== null) { return self::$targets; }
			$targets = array(
				'global' => array(array(
					'key' => '0', 'title' => 'Везде', 'meta' => 'Общее назначение',
					'url' => '', 'available' => true,
				)),
				'rubric' => array(),
				'request' => array(),
				'catalog' => array(),
				'module' => array(),
			);
			$rubrics = ContentTables::table('rubrics');
			if (DatabaseSchema::tableExists($rubrics)) {
				$rows = DB::query('SELECT Id,rubric_title FROM ' . $rubrics . ' ORDER BY rubric_title,Id')->getAll() ?: array();
				foreach ($rows as $row) {
					$targets['rubric'][] = array(
						'key' => (string) (int) $row['Id'],
						'title' => self::decode($row['rubric_title']),
						'meta' => 'Рубрика #' . (int) $row['Id'],
						'url' => '/rubrics/' . (int) $row['Id'],
						'available' => true,
					);
				}
			}

			$requests = ContentTables::table('request');
			if (DatabaseSchema::tableExists($requests)) {
				$rows = DB::query('SELECT Id,request_title,request_alias FROM ' . $requests . ' ORDER BY request_title,Id')->getAll() ?: array();
				foreach ($rows as $row) {
					$title = self::decode($row['request_title']);
					if ($title === '') { $title = 'Запрос #' . (int) $row['Id']; }
					$targets['request'][] = array(
						'key' => (string) (int) $row['Id'],
						'title' => $title,
						'meta' => trim((string) $row['request_alias']) !== '' ? (string) $row['request_alias'] : 'Запрос #' . (int) $row['Id'],
						'url' => '/requests/' . (int) $row['Id'],
						'available' => true,
					);
				}
			}

			$items = CatalogTables::table('module_catalog_items');
			$settings = CatalogTables::table('module_catalog_settings');
			if (DatabaseSchema::tableExists($items) && DatabaseSchema::tableExists($settings)) {
				$rows = DB::query(
					'SELECT i.id,i.name,i.level,i.rubric_id,i.field_id,COALESCE(s.purpose,\'content\') purpose'
						. ' FROM ' . $items . ' i LEFT JOIN ' . $settings
						. ' s ON s.rubric_id=i.rubric_id AND s.field_id=i.field_id'
						. ' ORDER BY s.purpose DESC,i.rubric_id,i.field_id,i.level,i.position,i.id'
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$targets['catalog'][] = array(
						'key' => (string) (int) $row['id'],
						'title' => str_repeat('— ', max(0, min(5, (int) $row['level']))) . self::decode($row['name']),
						'meta' => ((string) $row['purpose'] === 'commerce' ? 'Товарный раздел' : 'Раздел контента') . ' #' . (int) $row['id'],
						'url' => '/catalog/' . (int) $row['rubric_id'] . '/' . (int) $row['field_id'] . '?item=' . (int) $row['id'],
						'available' => true,
					);
				}
			}

			foreach (ModuleManager::all() as $module) {
				if (empty($module['installed']) || empty($module['enabled'])) { continue; }
				$extension = isset($module['admin_extension']) && is_array($module['admin_extension'])
					? $module['admin_extension']
					: array();
				$url = isset($extension['url']) ? trim((string) $extension['url']) : '';
				if ($url === '') { $url = '/modules/' . (string) $module['code']; }
				$targets['module'][] = array(
					'key' => strtolower((string) $module['code']),
					'title' => (string) $module['name'],
					'meta' => 'Модуль · ' . (string) $module['code'],
					'url' => $url,
					'available' => true,
				);
			}

			usort($targets['module'], function ($left, $right) {
				return strcasecmp((string) $left['title'], (string) $right['title']);
			});
			self::$targets = $targets;
			return self::$targets;
		}

		public static function assignments($presentationId)
		{
			$targets = self::assignmentTargetMap();
			$items = PresentationAssignmentRepository::all((int) $presentationId);
			foreach ($items as &$item) {
				$key = (string) $item['target_type'] . ':' . (string) $item['target_key'];
				$target = isset($targets[$key]) ? $targets[$key] : null;
				$item['target_title'] = $target ? (string) $target['title'] : 'Цель больше не существует';
				$item['target_meta'] = $target ? (string) $target['meta'] : $key;
				$item['target_url'] = $target ? (string) $target['url'] : '';
				$item['target_available'] = (bool) $target;
			}

			unset($item);
			return $items;
		}

		public static function validateAssignment(array $input)
		{
			$type = strtolower(trim(isset($input['target_type']) ? (string) $input['target_type'] : ''));
			$key = strtolower(trim(isset($input['target_key']) ? (string) $input['target_key'] : ''));
			if ($type === 'global') { $key = '0'; }
			$targets = self::assignmentTargetMap();
			if (!isset($targets[$type . ':' . $key])) {
				throw new \InvalidArgumentException('Выберите существующую цель назначения');
			}

			$input['target_type'] = $type;
			$input['target_key'] = $key;
			return $input;
		}

		public static function diagnose($presentationId, array $input)
		{
			$presentationId = (int) $presentationId;
			$item = PresentationRepository::one($presentationId);
			if (!$item) { throw new \RuntimeException('Представление не найдено'); }
			$checks = array();
			$markup = array(
				'Элемент' => isset($input['draft_item_markup']) ? (string) $input['draft_item_markup'] : (string) $item['draft_item_markup'],
				'Обёртка' => isset($input['draft_wrapper_markup']) ? (string) $input['draft_wrapper_markup'] : (string) $item['draft_wrapper_markup'],
				'Пустой результат' => isset($input['draft_empty_markup']) ? (string) $input['draft_empty_markup'] : (string) $item['draft_empty_markup'],
			);
			if (trim($markup['Элемент']) === '') {
				$checks[] = self::check('error', 'Шаблон элемента пуст', 'Заполните разметку одного материала.');
			} else {
				$syntax = PresentationSyntax::checkMany($markup);
				$checks[] = self::check($syntax['ok'] ? 'ok' : 'error', 'Twig-шаблоны', $syntax['message']);
			}

			$assignments = self::assignments($presentationId);
			if (!$assignments) {
				$checks[] = self::check('warning', 'Нет назначений', 'Представление сохранено, но пока нигде не используется.', '/public-site/presentations');
			} else {
				$missing = array_filter($assignments, function ($assignment) { return empty($assignment['target_available']); });
				$native = array_filter($assignments, function ($assignment) { return (string) $assignment['mode'] === 'native'; });
				$checks[] = self::check(
					$missing ? 'error' : 'ok',
					'Цели назначения',
					$missing ? 'Есть связи с удалёнными рубриками, запросами, разделами или модулями.' : 'Все ' . count($assignments) . ' назначений ведут к существующим целям.'
				);
				if ($native && empty($item['is_published'])) {
					$checks[] = self::check('error', 'Новое представление не опубликовано', 'Native-назначение начнёт работать только после публикации.');
				} elseif ($native) {
					$checks[] = self::check('ok', 'Публичный режим', count($native) . ' назначений используют опубликованную версию.');
				} else {
					$checks[] = self::check('ok', 'Публичный сайт защищён', 'Назначений native нет: текущая разметка сайта не заменяется.');
				}
			}

			$documentId = isset($input['preview_document_id']) ? (int) $input['preview_document_id'] : 0;
			if ($documentId > 0) {
				try {
					PresentationPreview::render($documentId, $input);
					$checks[] = self::check('ok', 'Предпросмотр', 'Черновик успешно собран на документе #' . $documentId . '.');
				} catch (\Throwable $e) {
					$checks[] = self::check('error', 'Предпросмотр', $e->getMessage());
				}
			} else {
				$checks[] = self::check('warning', 'Предпросмотр не проверен', 'Выберите реальный материал и повторите диагностику.');
			}

			$summary = array('ok' => 0, 'warning' => 0, 'error' => 0);
			foreach ($checks as $check) { $summary[$check['status']]++; }
			return array(
				'ready' => $summary['error'] === 0,
				'summary' => $summary,
				'checks' => $checks,
			);
		}

		protected static function appendLegacy(array &$sources, $table, $title, $url, $icon)
		{
			if (!DatabaseSchema::tableExists($table)) { return; }
			$sources[] = array(
				'title' => (string) $title,
				'url' => (string) $url,
				'icon' => (string) $icon,
				'count' => (int) DB::query('SELECT COUNT(*) FROM ' . $table)->getValue(),
			);
		}

		protected static function assignmentTargetMap()
		{
			$map = array();
			foreach (self::assignmentTargets() as $type => $items) {
				foreach ($items as $item) { $map[$type . ':' . (string) $item['key']] = $item; }
			}

			return $map;
		}

		protected static function check($status, $title, $message, $url = '')
		{
			return array(
				'status' => (string) $status,
				'title' => (string) $title,
				'message' => (string) $message,
				'url' => (string) $url,
			);
		}

		protected static function decode($value)
		{
			return html_entity_decode(stripslashes((string) $value), ENT_QUOTES, 'UTF-8');
		}
	}
