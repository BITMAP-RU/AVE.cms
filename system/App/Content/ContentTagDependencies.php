<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ContentTagDependencies.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Finds saved content that still calls a block, request or navigation tag. */
	class ContentTagDependencies
	{
		public static function block($id, $alias)
		{
			return self::references('block', $id, $alias);
		}

		public static function request($id, $alias)
		{
			$result = self::references('request', $id, $alias);
			$settings = ContentTables::table('module_catalog_settings');
			if (self::tableExists($settings)) {
				foreach (DB::query(
					'SELECT id,rubric_id FROM ' . $settings . ' WHERE request_id=%i ORDER BY id',
					(int) $id
				)->getAll() as $row) {
					$result[] = 'настройки каталога рубрики #' . (int) $row['rubric_id'];
				}
			}

			return self::unique($result);
		}

		public static function navigation($id, $alias)
		{
			$result = self::references('navigation', $id, $alias);
			$documents = ContentTables::table('documents');
			foreach (DB::query(
				'SELECT Id,document_title FROM ' . $documents
					. ' WHERE document_linked_navi_id=%i AND document_deleted!=%s ORDER BY Id LIMIT 20',
				(int) $id,
				'1'
			)->getAll() as $row) {
				$result[] = 'документ «' . self::title($row['document_title'], '#' . (int) $row['Id']) . '»';
			}

			$settings = ContentTables::table('module_catalog_settings');
			if (self::tableExists($settings)) {
				foreach (DB::query(
					'SELECT id,rubric_id FROM ' . $settings . ' WHERE navi_id=%i ORDER BY id',
					(int) $id
				)->getAll() as $row) {
					$result[] = 'настройки каталога рубрики #' . (int) $row['rubric_id'];
				}
			}

			$items = ContentTables::table('module_catalog_items');
			if (self::tableExists($items)) {
				foreach (DB::query(
					'SELECT id,name FROM ' . $items . ' WHERE navi_id=%i ORDER BY id LIMIT 20',
					(int) $id
				)->getAll() as $row) {
					$result[] = 'раздел каталога «' . self::title($row['name'], '#' . (int) $row['id']) . '»';
				}
			}

			return self::unique($result);
		}

		protected static function references($type, $id, $alias)
		{
			$pattern = self::pattern($type, $id, $alias);
			if ($pattern === '') {
				return array();
			}

			$result = array();
			foreach (self::contentSources() as $source) {
				if (!self::tableExists($source['table'])) {
					continue;
				}

				$sql = 'SELECT ' . $source['id'] . ' AS source_id,'
					. $source['title'] . ' AS source_title,'
					. $source['text'] . ' AS source_text'
					. ' FROM ' . $source['table']
					. ' WHERE ' . $source['text'] . ' LIKE %ss';
				foreach (DB::query($sql, '[tag:')->getAll() as $row) {
					if (!preg_match($pattern, (string) $row['source_text'])) {
						continue;
					}

					if ($type === 'block'
						&& $source['code'] === 'blocks'
						&& (int) $row['source_id'] === (int) $id) {
						continue;
					}

					$result[] = $source['label'] . ' «'
						. self::title($row['source_title'], '#' . (int) $row['source_id']) . '»';
				}
			}

			foreach (self::documentFieldSources() as $source) {
				if (!self::tableExists($source['table'])) {
					continue;
				}

				$rows = DB::query(
					'SELECT f.document_id,d.document_title,f.field_value AS source_text'
						. ' FROM ' . $source['table'] . ' f'
						. ' INNER JOIN ' . ContentTables::table('documents') . ' d ON d.Id=f.document_id'
						. ' WHERE f.field_value LIKE %ss AND d.document_deleted!=%s'
						. ' ORDER BY f.document_id LIMIT 100',
					'[tag:',
					'1'
				)->getAll();
				foreach ($rows as $row) {
					if (preg_match($pattern, (string) $row['source_text'])) {
						$result[] = 'поле документа «'
							. self::title($row['document_title'], '#' . (int) $row['document_id']) . '»';
					}
				}
			}

			return self::unique($result);
		}

		protected static function pattern($type, $id, $alias)
		{
			$prefixes = array(
				'block' => array('sysblock', 'block'),
				'request' => array('request'),
				'navigation' => array('navigation'),
			);
			if (!isset($prefixes[$type])) {
				return '';
			}

			$keys = array();
			foreach (array((string) (int) $id, trim((string) $alias)) as $key) {
				if ($key !== '' && $key !== '0') {
					$keys[$key] = preg_quote($key, '/');
				}
			}

			if (!$keys) {
				return '';
			}

			return '/\[tag:(?:' . implode('|', $prefixes[$type]) . '):(?:'
				. implode('|', array_values($keys)) . ')(?=[:\]])/i';
		}

		protected static function contentSources()
		{
			return array(
				array(
					'code' => 'templates',
					'table' => ContentTables::table('templates'),
					'id' => 'Id',
					'title' => 'template_title',
					'text' => 'template_text',
					'label' => 'шаблон сайта',
				),
				array(
					'code' => 'rubrics',
					'table' => ContentTables::table('rubrics'),
					'id' => 'Id',
					'title' => 'rubric_title',
					'text' => "CONCAT_WS('\\n',rubric_template,rubric_header_template,rubric_og_template,rubric_footer_template,rubric_teaser_template)",
					'label' => 'шаблоны рубрики',
				),
				array(
					'code' => 'rubric_templates',
					'table' => ContentTables::table('rubric_templates'),
					'id' => 'id',
					'title' => 'title',
					'text' => 'template',
					'label' => 'дополнительный шаблон рубрики',
				),
				array(
					'code' => 'requests',
					'table' => ContentTables::table('request'),
					'id' => 'Id',
					'title' => 'request_title',
					'text' => "CONCAT_WS('\\n',request_template_item,request_template_main)",
					'label' => 'шаблон запроса',
				),
				array(
					'code' => 'blocks',
					'table' => ContentTables::table('sysblocks'),
					'id' => 'id',
					'title' => 'sysblock_name',
					'text' => 'sysblock_text',
					'label' => 'блок',
				),
				array(
					'code' => 'navigation',
					'table' => ContentTables::table('navigation'),
					'id' => 'navigation_id',
					'title' => 'title',
					'text' => "CONCAT_WS('\\n',level1,level2,level3,level1_active,level2_active,level3_active,"
						. 'level1_begin,level1_end,level2_begin,level2_end,level3_begin,level3_end,begin,end)',
					'label' => 'шаблоны навигации',
				),
			);
		}

		protected static function documentFieldSources()
		{
			return array(
				array('table' => ContentTables::table('document_fields')),
				array('table' => ContentTables::table('document_fields_text')),
			);
		}

		protected static function title($title, $fallback)
		{
			$title = trim(htmlspecialchars_decode((string) $title, ENT_QUOTES));
			return $title !== '' ? $title : (string) $fallback;
		}

		protected static function unique(array $values)
		{
			return array_values(array_unique(array_filter($values, 'strlen')));
		}

		protected static function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}
	}
