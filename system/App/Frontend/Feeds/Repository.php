<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/Repository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use DB;

	class Repository
	{
		public function active()
		{
			$rows = DB::query('SELECT * FROM ' . Schema::table('definitions') . ' WHERE status=1 ORDER BY id')->getAll() ?: array();
			$result = array();
			foreach ($rows as $row) { $result[] = $this->hydrate((array) $row); }
			return $result;
		}

		public function findByAlias($alias)
		{
			$row = DB::query('SELECT * FROM ' . Schema::table('definitions') . ' WHERE alias=%s AND status=1 LIMIT 1', (string) $alias)->getAssoc();
			return $row ? $this->hydrate($row) : null;
		}

		public function find($id)
		{
			$row = DB::query('SELECT * FROM ' . Schema::table('definitions') . ' WHERE id=%i LIMIT 1', (int) $id)->getAssoc();
			return $row ? $this->hydrate($row) : null;
		}

		public function categories($feedId)
		{
			$rows = DB::query('SELECT catalog_item_id,mode FROM ' . Schema::table('categories') . ' WHERE feed_id=%i', (int) $feedId)->getAll();
			$result = array('include' => array(), 'exclude' => array());
			foreach ($rows ?: array() as $row) { $result[$row['mode'] === 'exclude' ? 'exclude' : 'include'][] = (int) $row['catalog_item_id']; }
			return $result;
		}

		public function categoryTree($rubricId = 0, $fieldId = 0)
		{
			$where = " WHERE s.purpose='commerce'";
			$args = array();
			if ((int) $rubricId > 0) {
				$where .= ' AND i.rubric_id=%i';
				$args[] = (int) $rubricId;
			}

			if ((int) $fieldId > 0) {
				$where .= ' AND i.field_id=%i';
				$args[] = (int) $fieldId;
			}

			$sql = 'SELECT i.id,i.parent_id,i.name,i.status,i.position,i.level'
				. ' FROM ' . CatalogTables::table('module_catalog_items') . ' i'
				. ' INNER JOIN ' . CatalogTables::table('module_catalog_settings') . ' s'
					. ' ON s.rubric_id=i.rubric_id AND s.field_id=i.field_id'
				. $where
				. ' ORDER BY i.parent_id,i.position,i.id';
			return call_user_func_array(
				array('DB', 'query'),
				array_merge(array($sql), $args)
			)->getAll() ?: array();
		}

		public function products(array $feed, $limit = 0)
		{
			$selected = $this->selectedCategoryIds($feed);
			$where = 'p.is_active=1 AND p.is_deleted=0 AND p.is_hidden=0 AND p.price>=%s';
			$args = array((string) $feed['min_price']);
			if ((int) $feed['rubric_id'] > 0) { $where .= ' AND p.rubric_id=%i'; $args[] = (int) $feed['rubric_id']; }
			if ($selected) { $where .= ' AND EXISTS (SELECT 1 FROM ' . CatalogTables::table('catalog_category_products') . ' cp WHERE cp.product_id=p.product_id AND cp.catalog_item_id IN (' . implode(',', $selected) . '))'; }
			$categoryProjection = $selected ? ',(SELECT cp.catalog_item_id FROM ' . CatalogTables::table('catalog_category_products') . ' cp WHERE cp.product_id=p.product_id AND cp.catalog_item_id IN (' . implode(',', $selected) . ') ORDER BY cp.is_primary DESC,cp.position,cp.catalog_item_id LIMIT 1) feed_category_id' : ',0 feed_category_id';
			$sql = 'SELECT p.*' . $categoryProjection . ' FROM ' . CatalogTables::table('catalog_product_index') . ' p WHERE ' . $where . ' ORDER BY p.position,p.product_id';
			if ((int) $limit > 0) { $sql .= ' LIMIT ' . (int) $limit; }
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			return $this->attachFields($rows, $feed);
		}

		public function outputCategories(array $feed)
		{
			$selected = $this->selectedCategoryIds($feed);
			if (!$selected) { return array(); }
			$rows = DB::query('SELECT id,parent_id,name FROM ' . CatalogTables::table('module_catalog_items') . ' WHERE id IN (' . implode(',', $selected) . ') ORDER BY parent_id,position,id')->getAll() ?: array();
			$allowed = array_flip($selected);
			foreach ($rows as &$row) { if (!isset($allowed[(int) $row['parent_id']]) || $feed['category_mode'] === 'flat') { $row['parent_id'] = 0; } }
			unset($row);
			return $rows;
		}

		protected function selectedCategoryIds(array $feed)
		{
			$selection = $this->categories($feed['id']);
			$tree = $this->categoryTree(
				isset($feed['rubric_id']) ? $feed['rubric_id'] : 0,
				isset($feed['catalog_field_id']) ? $feed['catalog_field_id'] : 0
			);
			$children = array();
			$active = array();
			foreach ($tree as $row) {
				if ((int) $row['status'] === 1) {
					$active[(int) $row['id']] = true;
				}
			}

			if (!$selection['include']) {
				return array_values(array_diff(array_keys($active), $selection['exclude']));
			}

			foreach ($tree as $row) {
				if (isset($active[(int) $row['id']])) {
					$children[(int) $row['parent_id']][] = (int) $row['id'];
				}
			}

			$ids = array_values(array_filter(
				$selection['include'],
				function ($id) use ($active) { return isset($active[(int) $id]); }
			));
			if (!empty($feed['include_descendants'])) {
				$queue = $ids;
				while ($queue) {
					$parent = array_shift($queue);
					foreach (isset($children[$parent]) ? $children[$parent] : array() as $child) {
						if (!in_array($child, $ids, true)) {
							$ids[] = $child;
							$queue[] = $child;
						}
					}
				}
			}

			return array_values(array_diff(array_unique(array_map('intval', $ids)), $selection['exclude']));
		}

		protected function attachFields(array $products, array $feed)
		{
			if (!$products) { return array(); }
			$fieldIds = array();
			foreach ($feed['mappings'] as $source) { if (preg_match('/^field:(\d+)$/', (string) $source, $m)) { $fieldIds[] = (int) $m[1]; } }
			foreach ($feed['params'] as $param) { if (!empty($param['field_id'])) { $fieldIds[] = (int) $param['field_id']; } }
			$fieldIds = array_values(array_unique(array_filter($fieldIds)));
			if (!$fieldIds) { foreach ($products as &$product) { $product['fields'] = array(); } return $products; }
			$productIds = array_map('intval', array_column($products, 'product_id'));
			$rows = DB::query('SELECT f.document_id,f.rubric_field_id,f.field_value,f.field_number_value,t.field_value text_value'
				. ' FROM ' . ContentTables::table('document_fields') . ' f LEFT JOIN ' . ContentTables::table('document_fields_text') . ' t'
				. ' ON t.document_id=f.document_id AND t.rubric_field_id=f.rubric_field_id'
				. ' WHERE f.document_id IN (' . implode(',', $productIds) . ') AND f.rubric_field_id IN (' . implode(',', $fieldIds) . ')')->getAll() ?: array();
			$values = array(); foreach ($rows as $row) { $values[(int) $row['document_id']][(int) $row['rubric_field_id']] = (string) $row['field_value'] . (string) $row['text_value']; }
			foreach ($products as &$product) { $product['fields'] = isset($values[(int) $product['product_id']]) ? $values[(int) $product['product_id']] : array(); }
			unset($product);
			return $products;
		}

		protected function hydrate(array $row)
		{
			$row['id'] = (int) $row['id']; $row['rubric_id'] = (int) $row['rubric_id']; $row['catalog_field_id']=isset($row['catalog_field_id'])?(int)$row['catalog_field_id']:0; $row['status'] = (int) $row['status'];
			$row['include_descendants'] = (int) $row['include_descendants']; $row['min_price'] = (float) $row['min_price']; $row['cache_ttl'] = (int) $row['cache_ttl'];
			$row['mappings'] = $this->json($row['mappings_json']); $row['params'] = $this->json($row['params_json']); $row['conditions'] = $this->json($row['conditions_json']);
			return $row;
		}

		protected function json($value) { $data = json_decode((string) $value, true); return is_array($data) ? $data : array(); }
	}
