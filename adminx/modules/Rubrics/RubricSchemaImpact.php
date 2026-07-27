<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/RubricSchemaImpact.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use App\Content\Requests\RequestResultContract;
	use App\Helpers\Hooks;
	use App\Helpers\Json;
	use DB;

	/** Read-only dependency report shown before restoring a rubric schema revision. */
	class RubricSchemaImpact
	{
		public static function analyze($rubricId, array $fieldReferences)
		{
			$rubricId = (int) $rubricId;
			$fields = self::normalizeFieldReferences($fieldReferences);
			$report = array(
				'requests' => array(),
				'templates' => array(),
				'api' => array(),
				'modules' => array(),
				'warnings' => array(),
			);

			if ($rubricId <= 0 || !$fields) {
				return self::finalize($report);
			}

			try {
				self::collectRequests($rubricId, $fields, $report);
			} catch (\Throwable $e) {
				$report['warnings'][] = 'Не удалось проверить запросы и API-контракты.';
			}

			try {
				self::collectRubricTemplates($rubricId, $fields, $report);
			} catch (\Throwable $e) {
				$report['warnings'][] = 'Не удалось проверить шаблоны рубрики.';
			}

			self::collectDocumentApi($rubricId, $fields, $report);
			self::collectCatalog($rubricId, $fields, $report);
			self::collectFeeds($rubricId, $fields, $report);
			self::collectModuleExtensions($rubricId, $fields, $report);

			return self::finalize($report);
		}

		public static function normalizeFieldReferences(array $references)
		{
			$out = array();
			foreach ($references as $key => $reference) {
				if (!is_array($reference)) { continue; }
				$id = isset($reference['id']) ? (int) $reference['id'] : (int) $key;
				if ($id <= 0) { continue; }
				$aliases = isset($reference['aliases']) && is_array($reference['aliases'])
					? $reference['aliases']
					: array(isset($reference['alias']) ? $reference['alias'] : '');
				$cleanAliases = array();
				foreach ($aliases as $alias) {
					$alias = strtolower(trim((string) $alias));
					if ($alias !== '' && !in_array($alias, $cleanAliases, true)) { $cleanAliases[] = $alias; }
				}

				$out[$id] = array(
					'id' => $id,
					'title' => trim((string) (isset($reference['title']) ? $reference['title'] : '')),
					'aliases' => $cleanAliases,
				);
			}

			ksort($out, SORT_NUMERIC);
			return $out;
		}

		/** Return affected field IDs referenced by fld/rfld/field tags in a template. */
		public static function templateReferences($template, array $fieldReferences)
		{
			$fields = self::normalizeFieldReferences($fieldReferences);
			if (!$fields || trim((string) $template) === '') { return array(); }
			preg_match_all('/\[tag:(?:r?fld|field):([a-zA-Z0-9_-]+)/i', (string) $template, $matches);
			$tokens = isset($matches[1]) ? array_map('strtolower', $matches[1]) : array();
			$out = array();
			foreach ($fields as $fieldId => $field) {
				if (in_array((string) $fieldId, $tokens, true) || array_intersect($field['aliases'], $tokens)) {
					$out[] = (int) $fieldId;
				}
			}

			return $out;
		}

		protected static function collectRequests($rubricId, array $fields, array &$report)
		{
			$requestTable = ContentTables::table('request');
			$conditionTable = ContentTables::table('request_conditions');
			if (!self::tableExists($requestTable)) { return; }

			$conditions = array();
			if (self::tableExists($conditionTable)) {
				$rows = DB::query(
					'SELECT c.request_id,c.condition_field_id FROM ' . $conditionTable . ' c'
						. ' INNER JOIN ' . $requestTable . ' r ON r.Id=c.request_id'
						. ' WHERE r.rubric_id=%i AND c.condition_field_id IN %li',
					(int) $rubricId,
					array_keys($fields)
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$conditions[(int) $row['request_id']][] = (int) $row['condition_field_id'];
				}
			}

			$requests = DB::query(
				'SELECT Id,request_title,request_alias,request_order_by_nat,request_sort_rules,request_template_item,request_template_main,request_result_contract'
					. ' FROM ' . $requestTable . ' WHERE rubric_id=%i ORDER BY Id',
				(int) $rubricId
			)->getAll() ?: array();
			foreach ($requests as $request) {
				$id = (int) $request['Id'];
				$details = array('Источник данных: эта рубрика');
				if (!empty($conditions[$id])) {
					$details[] = 'Условия: ' . self::fieldList($conditions[$id], $fields);
				}

				$orderFieldIds = array();
				foreach (\App\Frontend\RequestSort::rulesForRequest($request) as $sortRule) {
					if ($sortRule['source'] === 'field' && isset($fields[(int) $sortRule['key']])) {
						$orderFieldIds[] = (int) $sortRule['key'];
					}
				}

				if ($orderFieldIds) { $details[] = 'Сортировка: ' . self::fieldList(array_unique($orderFieldIds), $fields); }

				$templateIds = array_values(array_unique(array_merge(
					self::templateReferences((string) $request['request_template_item'], $fields),
					self::templateReferences((string) $request['request_template_main'], $fields)
				)));
				if ($templateIds) {
					$details[] = 'Шаблон результата: ' . self::fieldList($templateIds, $fields);
					$report['templates'][] = self::item(
						'request:' . $id,
						'Шаблоны запроса «' . self::requestTitle($request) . '»',
						'Запрос #' . $id,
						array('Используются поля: ' . self::fieldList($templateIds, $fields))
					);
				}

				$contract = RequestResultContract::decode((string) $request['request_result_contract']);
				$contractIds = array_values(array_intersect(array_keys($fields), $contract['fields']));
				if ($contractIds) {
					$details[] = 'Контракт результата: ' . self::fieldList($contractIds, $fields);
					$report['api'][] = self::item(
						'request:' . $id,
						self::requestTitle($request),
						'Контракт запроса #' . $id,
						array('API и структурированный preview получают: ' . self::fieldList($contractIds, $fields))
					);
				}

				$report['requests'][] = self::item(
					'id:' . $id,
					self::requestTitle($request),
					'Запрос #' . $id . self::aliasMeta($request['request_alias']),
					$details
				);
			}
		}

		protected static function collectRubricTemplates($rubricId, array $fields, array &$report)
		{
			$rubric = DB::query(
				'SELECT rubric_title,rubric_template,rubric_teaser_template,rubric_header_template,rubric_og_template,rubric_footer_template'
					. ' FROM ' . Model::rubricsTable() . ' WHERE Id=%i LIMIT 1',
				(int) $rubricId
			)->getAssoc();
			if ($rubric) {
				$parts = array(
					'rubric_template' => 'Документ',
					'rubric_teaser_template' => 'Тизер',
					'rubric_header_template' => 'Шапка',
					'rubric_og_template' => 'Open Graph',
					'rubric_footer_template' => 'Подвал',
				);
				foreach ($parts as $column => $label) {
					$ids = self::templateReferences(isset($rubric[$column]) ? $rubric[$column] : '', $fields);
					if (!$ids) { continue; }
					$report['templates'][] = self::item(
						'rubric:' . $column,
						$label . ': «' . (string) $rubric['rubric_title'] . '»',
						'Основной шаблон рубрики',
						array('Используются поля: ' . self::fieldList($ids, $fields))
					);
				}
			}

			if (!self::tableExists(Model::templatesTable())) { return; }
			$templates = DB::query(
				'SELECT id,title,template FROM ' . Model::templatesTable() . ' WHERE rubric_id=%i ORDER BY id',
				(int) $rubricId
			)->getAll() ?: array();
			foreach ($templates as $template) {
				$ids = self::templateReferences((string) $template['template'], $fields);
				if (!$ids) { continue; }
				$report['templates'][] = self::item(
					'extra:' . (int) $template['id'],
					trim((string) $template['title']) ?: 'Шаблон #' . (int) $template['id'],
					'Дополнительный шаблон #' . (int) $template['id'],
					array('Используются поля: ' . self::fieldList($ids, $fields))
				);
			}
		}

		protected static function collectDocumentApi($rubricId, array $fields, array &$report)
		{
			$report['api'][] = self::item(
				'document-api:rubric:' . (int) $rubricId,
				'JSON API документов',
				'/api/v1/documents · рубрика #' . (int) $rubricId,
				array(
					'Чтение и запись используют схему: ' . self::fieldList(array_keys($fields), $fields),
					'После восстановления проверьте клиентов, которые передают alias или ID этих полей.',
				)
			);
		}

		protected static function collectCatalog($rubricId, array $fields, array &$report)
		{
			$settingsTable = CatalogTables::table('module_catalog_settings');
			$itemsTable = CatalogTables::table('module_catalog_items');
			$variantTable = CatalogTables::table('catalog_variant_attributes');
			try {
				if (self::tableExists($settingsTable)) {
					$rows = DB::query('SELECT * FROM ' . $settingsTable . ' WHERE rubric_id=%i ORDER BY id', (int) $rubricId)->getAll() ?: array();
					foreach ($rows as $row) {
						$used = self::catalogSettingsFields($row, $fields);
						$details = array('Каталог построен на этой рубрике');
						if ($used) { $details[] = 'Назначения товаров: ' . self::fieldList($used, $fields); }
						$report['modules'][] = self::item('catalog:settings:' . (int) $row['id'], 'Настройки каталога', 'Модуль «Каталог»', $details);
					}
				}

				if (self::tableExists($itemsTable)) {
					$rows = DB::query('SELECT id,name,field_id,fields_use,filters_use FROM ' . $itemsTable . ' WHERE rubric_id=%i ORDER BY id', (int) $rubricId)->getAll() ?: array();
					foreach ($rows as $row) {
						$used = self::catalogItemFields($row, $fields);
						$details = array('Раздел использует эту рубрику');
						if ($used) { $details[] = 'Поля и фильтры: ' . self::fieldList($used, $fields); }
						$report['modules'][] = self::item(
							'catalog:item:' . (int) $row['id'],
							trim((string) $row['name']) ?: 'Раздел #' . (int) $row['id'],
							'Раздел каталога #' . (int) $row['id'],
							$details
						);
					}
				}

				if (self::tableExists($variantTable)) {
					$rows = DB::query(
						'SELECT field_id,COUNT(*) count FROM ' . $variantTable . ' WHERE field_id IN %li GROUP BY field_id ORDER BY field_id',
						array_keys($fields)
					)->getAll() ?: array();
					foreach ($rows as $row) {
						$fieldId = (int) $row['field_id'];
						$report['modules'][] = self::item(
							'products:variants:' . $fieldId,
							'Варианты товаров',
							'Модуль «Товары»',
							array(self::fieldLabel($fields[$fieldId]) . ': ' . (int) $row['count'] . ' значений вариантов')
						);
					}
				}
			} catch (\Throwable $e) {
				$report['warnings'][] = 'Не удалось полностью проверить зависимости каталога.';
			}
		}

		protected static function collectFeeds($rubricId, array $fields, array &$report)
		{
			$table = ContentTables::table('feed_definitions');
			if (!self::tableExists($table)) { return; }
			try {
				$catalogColumn = self::columnExists($table, 'catalog_field_id') ? 'catalog_field_id' : '0 AS catalog_field_id';
				$rows = DB::query(
					'SELECT id,name,alias,' . $catalogColumn . ',mappings_json,params_json,conditions_json'
						. ' FROM ' . $table . ' WHERE rubric_id=%i ORDER BY id',
					(int) $rubricId
				)->getAll() ?: array();
				foreach ($rows as $row) {
					$used = array();
					$catalogFieldId = (int) $row['catalog_field_id'];
					if ($catalogFieldId > 0 && isset($fields[$catalogFieldId])) { $used[$catalogFieldId] = $catalogFieldId; }
					foreach (array('mappings_json', 'params_json', 'conditions_json') as $column) {
						$data = Json::toArray((string) $row[$column]);
						if (is_array($data)) { self::findStructuredFields($data, $fields, $used); }
					}

					$details = array('Фид построен на этой рубрике');
					if ($used) { $details[] = 'Настройки полей: ' . self::fieldList(array_keys($used), $fields); }
					$report['modules'][] = self::item(
						'feeds:' . (int) $row['id'],
						trim((string) $row['name']) ?: 'Фид #' . (int) $row['id'],
						'Модуль «Товарные фиды»' . self::aliasMeta($row['alias']),
						$details
					);
				}
			} catch (\Throwable $e) {
				$report['warnings'][] = 'Не удалось проверить зависимости товарных фидов.';
			}
		}

		protected static function collectModuleExtensions($rubricId, array $fields, array &$report)
		{
			if (!class_exists(Hooks::class) || !Hooks::exists('content.rubric.schema_impact')) { return; }
			$payload = array(
				'rubric_id' => (int) $rubricId,
				'field_ids' => array_keys($fields),
				'fields' => array_values($fields),
				'items' => array(),
			);
			try {
				$payload = Hooks::filter('content.rubric.schema_impact', $payload);
				$items = is_array($payload) && isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
				foreach ($items as $index => $item) {
					if (!is_array($item)) { continue; }
					$report['modules'][] = self::item(
						isset($item['key']) ? $item['key'] : 'extension:' . $index,
						isset($item['title']) ? $item['title'] : 'Зависимость модуля',
						isset($item['meta']) ? $item['meta'] : 'Модуль',
						isset($item['details']) && is_array($item['details']) ? $item['details'] : array()
					);
				}
			} catch (\Throwable $e) {
				$report['warnings'][] = 'Один из модулей не завершил анализ зависимостей.';
			}
		}

		protected static function catalogSettingsFields(array $row, array $fields)
		{
			$columns = array(
				'field_id', 'product_title_field_id', 'product_article_field_id', 'product_price_field_id',
				'product_old_price_field_id', 'product_stock_field_id', 'product_images_field_id',
			);
			$used = array();
			foreach ($columns as $column) {
				$id = isset($row[$column]) ? (int) $row[$column] : 0;
				if ($id > 0 && isset($fields[$id])) { $used[$id] = $id; }
			}

			foreach (array('fields_default', 'filters_default', 'filters_default_settings', 'product_card_settings') as $column) {
				if (!isset($row[$column])) { continue; }
				$data = Json::toArray((string) $row[$column]);
				if (is_array($data)) { self::findStructuredFields($data, $fields, $used); }
			}

			foreach (array('fields_default', 'filters_default') as $column) {
				preg_match_all('/\d+/', isset($row[$column]) ? (string) $row[$column] : '', $matches);
				foreach (isset($matches[0]) ? $matches[0] : array() as $value) {
					$fieldId = (int) $value;
					if (isset($fields[$fieldId])) { $used[$fieldId] = $fieldId; }
				}
			}

			return array_values($used);
		}

		protected static function catalogItemFields(array $row, array $fields)
		{
			$used = array();
			$id = isset($row['field_id']) ? (int) $row['field_id'] : 0;
			if ($id > 0 && isset($fields[$id])) { $used[$id] = $id; }
			foreach (array('fields_use', 'filters_use') as $column) {
				preg_match_all('/\d+/', isset($row[$column]) ? (string) $row[$column] : '', $matches);
				foreach (isset($matches[0]) ? $matches[0] : array() as $value) {
					$fieldId = (int) $value;
					if (isset($fields[$fieldId])) { $used[$fieldId] = $fieldId; }
				}
			}

			return array_values($used);
		}

		protected static function findStructuredFields(array $data, array $fields, array &$used)
		{
			foreach ($data as $key => $value) {
				if (is_array($value)) {
					self::findStructuredFields($value, $fields, $used);
					continue;
				}

				$fieldId = 0;
				if (in_array((string) $key, array('field_id', 'fieldId'), true)) { $fieldId = (int) $value; }
				if (is_string($value) && preg_match('/^field:(\d+)$/', $value, $match)) { $fieldId = (int) $match[1]; }
				if ($fieldId > 0 && isset($fields[$fieldId])) { $used[$fieldId] = $fieldId; }
			}
		}

		protected static function finalize(array $report)
		{
			foreach (array('requests', 'templates', 'api', 'modules') as $section) {
				$report[$section] = self::uniqueItems($report[$section]);
			}

			$report['warnings'] = array_values(array_unique(array_filter(array_map('strval', $report['warnings']))));
			$report['summary'] = array(
				'requests' => count($report['requests']),
				'templates' => count($report['templates']),
				'api' => count($report['api']),
				'modules' => count($report['modules']),
			);
			$report['fingerprint'] = hash(
				'sha256',
				Json::encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			);

			return $report;
		}

		protected static function uniqueItems(array $items)
		{
			$out = array();
			foreach ($items as $item) {
				if (!is_array($item)) { continue; }
				$key = (string) (isset($item['key']) ? $item['key'] : '');
				if ($key === '') { $key = sha1(Json::encode($item)); }
				$out[$key] = self::item(
					$key,
					isset($item['title']) ? $item['title'] : 'Зависимость',
					isset($item['meta']) ? $item['meta'] : '',
					isset($item['details']) && is_array($item['details']) ? $item['details'] : array()
				);
			}

			ksort($out, SORT_STRING);
			return array_values($out);
		}

		protected static function item($key, $title, $meta, array $details)
		{
			return array(
				'key' => trim((string) $key),
				'title' => trim((string) $title),
				'meta' => trim((string) $meta),
				'details' => array_values(array_unique(array_filter(array_map('strval', $details)))),
			);
		}

		protected static function fieldList(array $ids, array $fields)
		{
			$labels = array();
			foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
				if (isset($fields[$id])) { $labels[] = self::fieldLabel($fields[$id]); }
			}

			return implode(', ', $labels);
		}

		protected static function fieldLabel(array $field)
		{
			$title = trim((string) $field['title']);
			$alias = !empty($field['aliases']) ? (string) $field['aliases'][0] : '';
			return ($title !== '' ? $title : 'Поле #' . (int) $field['id'])
				. ($alias !== '' ? ' [' . $alias . ']' : ' [#' . (int) $field['id'] . ']');
		}

		protected static function requestTitle(array $request)
		{
			$title = trim((string) $request['request_title']);
			return $title !== '' ? $title : 'Запрос #' . (int) $request['Id'];
		}

		protected static function aliasMeta($alias)
		{
			$alias = trim((string) $alias);
			return $alias !== '' ? ' · ' . $alias : '';
		}

		protected static function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}

		protected static function columnExists($table, $column)
		{
			return (bool) DB::query('SHOW COLUMNS FROM ' . $table . ' LIKE %s', (string) $column)->getValue();
		}
	}
