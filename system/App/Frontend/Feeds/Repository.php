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
	use App\Common\DatabaseSchema;
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
			$rows = $this->productRows($feed, $limit);
			return $rows ? $this->attachAttributes($this->attachFields($rows, $feed), $feed) : array();
		}

		/** Same selection as export, without field hydration or XML generation. */
		public function containsProduct(array $feed, $productId)
		{
			return (int) $productId > 0 && count($this->productRows($feed, 1, (int) $productId)) > 0;
		}

		protected function productRows(array $feed, $limit = 0, $productId = 0)
		{
			$selected = $this->selectedCategoryIds($feed);
			if (!$selected) { return array(); }
			$conditions = $this->conditions($feed);
			$where = 'p.is_active=1 AND p.is_deleted=0 AND p.is_hidden=0';
			if (class_exists('App\\Modules\\Products\\ProductSnapshot')) {
				$where .= ' AND ' . \App\Modules\Products\ProductSnapshot::publishedSql('p');
			}

			$args = array();
			$priceRules = array();
			if ($conditions['include_with_price']) {
				$minimum = max(0, (float) $feed['min_price']);
				if ($minimum > 0) {
					$priceRules[] = 'p.price>=%s';
					$args[] = (string) $minimum;
				} else {
					$priceRules[] = 'p.price>0';
				}
			}

			if ($conditions['include_without_price']) { $priceRules[] = 'p.price<=0'; }
			if (!$priceRules) { return array(); }
			$where .= ' AND (' . implode(' OR ', $priceRules) . ')';
			if ($conditions['include_in_price'] !== $conditions['include_not_in_price']) {
				$notInPrice = 'EXISTS (SELECT 1 FROM ' . ContentTables::table('document_fields') . ' np'
					. ' INNER JOIN ' . ContentTables::table('rubric_fields') . ' nrf'
					. ' ON nrf.Id=np.rubric_field_id AND nrf.rubric_field_alias=%s'
					. ' WHERE np.document_id=p.product_id AND ('
					. 'COALESCE(np.field_number_value,0)>0 OR LOWER(TRIM(np.field_value)) IN (\'1\',\'true\',\'on\',\'yes\')))';
				if (class_exists('App\\Modules\\Products\\ProductRoles')) {
					$notInPrice = \App\Modules\Products\ProductRoles::enabledSql('exclude_from_price', 'p');
				} else {
					$args[] = 'noprice';
				}

				$where .= $conditions['include_not_in_price'] ? ' AND (' . $notInPrice . ')' : ' AND NOT (' . $notInPrice . ')';
			}

			if ((int) $feed['rubric_id'] > 0) { $where .= ' AND p.rubric_id=%i'; $args[] = (int) $feed['rubric_id']; }
			if ($selected) { $where .= ' AND EXISTS (SELECT 1 FROM ' . CatalogTables::table('catalog_category_products') . ' cp WHERE cp.product_id=p.product_id AND cp.catalog_item_id IN (' . implode(',', $selected) . '))'; }
			$categoryProjection = $selected ? ',(SELECT cp.catalog_item_id FROM ' . CatalogTables::table('catalog_category_products') . ' cp WHERE cp.product_id=p.product_id AND cp.catalog_item_id IN (' . implode(',', $selected) . ') ORDER BY cp.is_primary DESC,cp.position,cp.catalog_item_id LIMIT 1) feed_category_id' : ',0 feed_category_id';
			if ((int) $productId > 0) { $where .= ' AND p.product_id=%i'; $args[] = (int) $productId; }
			$sql = 'SELECT p.*' . $categoryProjection . ' FROM ' . CatalogTables::table('catalog_product_index') . ' p WHERE ' . $where . ' ORDER BY p.position,p.product_id';
			if ((int) $limit > 0) { $sql .= ' LIMIT ' . (int) $limit; }
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			return $rows;
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

		protected function attachAttributes(array $products, array $feed)
		{
			if (!$products) { return array(); }
			$attributeIds = array();
			foreach ($feed['params'] as $param) {
				if (!empty($param['attribute_id'])) { $attributeIds[] = (int) $param['attribute_id']; }
			}

			$attributeIds = array_values(array_unique(array_filter($attributeIds)));
			if (!$attributeIds) {
				foreach ($products as &$product) { $product['attributes'] = array(); }
				unset($product);
				return $products;
			}

			$productIds = array_map('intval', array_column($products, 'product_id'));
			$rows = DB::query(
				'SELECT v.document_id,v.attribute_id,v.value_json,v.value_string,v.value_number,a.value_type'
					. ' FROM ' . CatalogTables::table('catalog_product_attribute_values') . ' v'
					. ' INNER JOIN ' . CatalogTables::table('catalog_attributes') . ' a ON a.id=v.attribute_id AND a.status=1'
					. ' WHERE v.state=%s AND v.document_id IN (' . implode(',', $productIds) . ')'
					. ' AND v.attribute_id IN (' . implode(',', $attributeIds) . ')',
				'verified'
			)->getAll() ?: array();
			$options = $this->attributeOptions($rows);
			$values = array();
			foreach ($rows as $row) {
				$row['options'] = isset($options[(int) $row['attribute_id']]) ? $options[(int) $row['attribute_id']] : array();
				$values[(int) $row['document_id']][(int) $row['attribute_id']] = $this->attributeValue($row);
			}

			foreach ($products as &$product) {
				$product['attributes'] = isset($values[(int) $product['product_id']])
					? $values[(int) $product['product_id']]
					: array();
			}

			unset($product);
			return $products;
		}

		protected function attributeOptions(array $rows)
		{
			$ids = array();
			foreach ($rows as $row) {
				if (in_array($row['value_type'], array('choice', 'multi_choice'), true) && trim((string) $row['value_string']) === '') {
					$ids[] = (int) $row['attribute_id'];
				}
			}

			$table = CatalogTables::table('catalog_attribute_options');
			if (!$ids || !DatabaseSchema::tableExists($table)) { return array(); }
			$options = DB::query('SELECT attribute_id,value_key,label FROM ' . $table
				. ' WHERE attribute_id IN (' . implode(',', array_unique($ids)) . ') ORDER BY position,id')->getAll() ?: array();
			$result = array();
			foreach ($options as $option) {
				$result[(int) $option['attribute_id']][(string) $option['value_key']] = (string) $option['label'];
			}

			return $result;
		}

		protected function attributeValue(array $row)
		{
			if (class_exists(\App\Modules\Products\AttributeValueFormatter::class)) {
				return \App\Modules\Products\AttributeValueFormatter::format($row, isset($row['options']) && is_array($row['options']) ? $row['options'] : array());
			}

			$value = trim(isset($row['value_string']) ? (string) $row['value_string'] : '');
			$decoded = json_decode(isset($row['value_json']) ? (string) $row['value_json'] : '', true);
			if (isset($row['value_type']) && (string) $row['value_type'] === 'boolean') {
				if ($value === '' && $decoded === null && !isset($row['value_number'])) { return ''; }
				$enabled = (isset($row['value_number']) && (float) $row['value_number'] > 0)
					|| in_array(strtolower($value), array('1', 'true', 'on', 'yes', 'да'), true)
					|| $decoded === true || $decoded === 1 || $decoded === '1';
				return $enabled ? 'Да' : 'Нет';
			}

			if ($value !== '') { return $value; }
			$options = isset($row['options']) && in_array(isset($row['value_type']) ? $row['value_type'] : '', array('choice', 'multi_choice'), true)
				? $row['options'] : array();
			if (is_array($decoded)) {
				$flat = array();
				array_walk_recursive($decoded, function ($item) use (&$flat, $options) {
					if (is_scalar($item) && trim((string) $item) !== '') {
						$key = trim((string) $item);
						$flat[] = isset($options[$key]) ? $options[$key] : $key;
					}
				});
				if ($flat) { $value = implode(', ', array_values(array_unique($flat))); }
			} elseif (is_scalar($decoded) && trim((string) $decoded) !== '') {
				$key = trim((string) $decoded);
				$value = isset($options[$key]) ? $options[$key] : $key;
			}

			if ($value === '' && isset($row['value_number']) && $row['value_number'] !== null) {
				$value = (string) $row['value_number'];
				if (strpos($value, '.') !== false && stripos($value, 'e') === false) {
					$value = rtrim(rtrim($value, '0'), '.');
				}
			}

			return $value;
		}

		protected function conditions(array $feed)
		{
			$stored = isset($feed['conditions']) && is_array($feed['conditions']) ? $feed['conditions'] : array();
			return array(
				'include_with_price' => array_key_exists('include_with_price', $stored) ? !empty($stored['include_with_price']) : true,
				'include_without_price' => array_key_exists('include_without_price', $stored) ? !empty($stored['include_without_price']) : false,
				'include_in_price' => array_key_exists('include_in_price', $stored) ? !empty($stored['include_in_price']) : true,
				'include_not_in_price' => array_key_exists('include_not_in_price', $stored) ? !empty($stored['include_not_in_price']) : false,
			);
		}

		protected function hydrate(array $row)
		{
			$row['id'] = (int) $row['id']; $row['rubric_id'] = (int) $row['rubric_id']; $row['catalog_field_id']=isset($row['catalog_field_id'])?(int)$row['catalog_field_id']:0; $row['status'] = (int) $row['status'];
			$row['include_descendants'] = (int) $row['include_descendants']; $row['min_price'] = (float) $row['min_price']; $row['cache_ttl'] = (int) $row['cache_ttl'];
			$row['mappings'] = $this->json($row['mappings_json']);
			$row['params'] = $this->json($row['params_json']);
			$row['conditions'] = $this->json($row['conditions_json']);
			$row['conditions'] = $this->conditions($row);
			return $row;
		}

		protected function json($value) { $data = json_decode((string) $value, true); return is_array($data) ? $data : array(); }
	}
