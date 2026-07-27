<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/FeedPresets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\CatalogTables;
	use App\Helpers\Hooks;
	use DB;

	/** Extensible feed presets. Site-specific paths and mappings belong to modules. */
	class FeedPresets
	{
		protected static $seeded = false;

		public static function seed()
		{
			if (self::$seeded) { return; }
			self::$seeded = true;
			foreach (self::definitions() as $definition) {
				if (is_array($definition)) { self::create($definition); }
			}
		}

		public static function ensureCategory($categoryId)
		{
			$categoryId = (int) $categoryId;
			if ($categoryId < 1 || !self::categoryExists($categoryId)) { return null; }
			$alias = 'catalog-category-' . $categoryId;
			self::create(array(
				'alias' => $alias,
				'name' => 'Категория каталога ' . $categoryId,
				'categories' => array($categoryId),
			));
			return $alias;
		}

		public static function aliasForPath($path)
		{
			$path = ltrim((string) $path, '/'); $paths = self::paths();
			return isset($paths[$path]) ? $paths[$path] : null;
		}

		public static function paths()
		{
			$paths = Hooks::filter('feeds.preset_paths', array());
			if (!is_array($paths)) { return array(); }

			$normalized = array();
			foreach ($paths as $path => $alias) {
				$path = ltrim(trim((string) $path), '/');
				$alias = trim((string) $alias);
				if ($path === '' || $alias === '' || strpos($path, '..') !== false) { continue; }
				$normalized[$path] = $alias;
			}

			return $normalized;
		}

		public static function definitions()
		{
			$definitions = Hooks::filter('feeds.preset_definitions', array());
			return is_array($definitions) ? array_values($definitions) : array();
		}

		protected static function create(array $definition)
		{
			$alias = isset($definition['alias']) ? trim((string) $definition['alias']) : '';
			$name = isset($definition['name']) ? trim((string) $definition['name']) : '';
			if ($alias === '' || $name === '') { return; }
			if (DB::query('SELECT id FROM ' . Schema::table('definitions') . ' WHERE alias=%s LIMIT 1', $alias)->getValue()) { return; }
			$rubricId = isset($definition['rubric_id']) ? (int) $definition['rubric_id'] : 0;
			$catalogFieldId = isset($definition['catalog_field_id']) ? (int) $definition['catalog_field_id'] : 0;
			if ($catalogFieldId < 1 && $rubricId > 0) {
				$catalogFieldId = (int) DB::query(
					'SELECT field_id FROM ' . CatalogTables::table('module_catalog_settings')
					. " WHERE rubric_id=%i AND purpose='commerce' ORDER BY id LIMIT 1",
					$rubricId
				)->getValue();
			}

			$mapping = isset($definition['mappings']) && is_array($definition['mappings'])
				? $definition['mappings']
				: array();
			$categories = isset($definition['categories']) && is_array($definition['categories'])
				? $definition['categories']
				: array();
			$host = isset($definition['base_url']) ? trim((string) $definition['base_url']) : '';
			if ($host === '' && defined('HOST') && HOST) { $host = rtrim(HOST, '/'); }
			$now = time();
			DB::Insert(Schema::table('definitions'), array(
				'name' => $name,
				'alias' => $alias,
				'status' => !isset($definition['status']) || !empty($definition['status']) ? 1 : 0,
				'profile' => isset($definition['profile']) ? (string) $definition['profile'] : 'yml_catalog',
				'rubric_id' => $rubricId,
				'catalog_field_id' => $catalogFieldId,
				'site_name' => isset($definition['site_name']) ? (string) $definition['site_name'] : '',
				'company_name' => isset($definition['company_name']) ? (string) $definition['company_name'] : '',
				'base_url' => $host,
				'currency' => isset($definition['currency']) ? (string) $definition['currency'] : 'RUB',
				'category_mode' => isset($definition['category_mode']) ? (string) $definition['category_mode'] : 'tree',
				'include_descendants' => !empty($definition['include_descendants']) ? 1 : 0,
				'min_price' => isset($definition['min_price']) ? (float) $definition['min_price'] : 0,
				'cache_ttl' => isset($definition['cache_ttl']) ? max(0, (int) $definition['cache_ttl']) : 3600,
				'access_token' => isset($definition['access_token']) ? (string) $definition['access_token'] : '',
				'mappings_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'params_json' => json_encode(isset($definition['params']) ? $definition['params'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'conditions_json' => json_encode(isset($definition['conditions']) ? $definition['conditions'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'created_at' => $now,
				'updated_at' => $now,
			));
			$feedId = (int) DB::insertId();
			foreach (array_unique(array_map('intval', $categories)) as $categoryId) {
				if ($categoryId > 0 && self::categoryExists($categoryId)) { DB::Insert(Schema::table('categories'), array('feed_id'=>$feedId,'catalog_item_id'=>$categoryId,'mode'=>'include')); }
			}
		}

		protected static function categoryExists($categoryId)
		{
			return (bool) DB::query('SELECT id FROM ' . CatalogTables::table('module_catalog_items') . ' WHERE id=%i LIMIT 1', (int) $categoryId)->getValue();
		}
	}
