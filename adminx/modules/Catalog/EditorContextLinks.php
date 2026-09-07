<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Catalog/EditorContextLinks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Catalog;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\AdminLocale;
	use App\Common\AdminLocation;
	use App\Common\Permission;
	use App\Content\CatalogTables;
	use App\Content\ContentTables;
	use App\Content\Presentation\PresentationRenderer;
	use App\Helpers\Html;
	use App\Modules\Products\CardSettings;
	use App\Modules\Products\CardTemplateRepository;
	use DB;

	/** Saved editor context, linking to HTML parent pages rather than JSON drawer endpoints. */
	class EditorContextLinks
	{
		public static function product(array $document, array $attributes)
		{
			$links = static::page($document);
			$sections = DB::query(
				'SELECT DISTINCT i.id,i.rubric_id,i.field_id,i.name,i.filter_runtime FROM %b cp JOIN %b i ON i.id=cp.catalog_item_id WHERE cp.product_id=%i ORDER BY i.name,i.id',
				CatalogTables::table('catalog_category_products'), CatalogTables::table('module_catalog_items'), (int) $document['Id']
			)->getAll() ?: array();
			$sets = array();
			foreach ($attributes['groups'] as $group) {
				$id = (int) $group['set_id'];
				if (isset($sets[$id])) { continue; }
				$sets[$id] = true;
				static::add($links, 'attribute_set', '/catalog/attribute-sets/' . $id, array(), 'view_products', $group['set_name']);
			}

			if (!$sections) { static::card($links, (int) $document['rubric_id'], array(), array()); }
			$catalogSettings = array();
			foreach ($sections as $section) {
				$key = (int) $section['rubric_id'] . ':' . (int) $section['field_id'];
				if (!isset($catalogSettings[$key])) {
					$catalogSettings[$key] = Model::settings((int) $section['rubric_id'], (int) $section['field_id']);
				}

				static::filters($links, $section);
				static::card($links, (int) $document['rubric_id'], $section, $catalogSettings[$key]);
			}

			return $links;
		}

		public static function section(array $section, $productsAvailable = true)
		{
			$document = !empty($section['document_id']) ? DB::query(
				'SELECT Id,rubric_id,rubric_tmpl_id FROM %b WHERE Id=%i LIMIT 1',
				ContentTables::table('documents'), (int) $section['document_id']
			)->getAssoc() : array();
			$links = $document ? static::page($document) : array();
			static::filters($links, $section, $productsAvailable);
			if (!$productsAvailable) { return $links; }
			$settings = Model::settings((int) $section['rubric_id'], (int) $section['field_id']);
			if (!empty($section['attribute_set_id'])) {
				static::add($links, 'attribute_set', '/catalog/attribute-sets/' . (int) $section['attribute_set_id'], array(), 'view_products', '#' . (int) $section['attribute_set_id']);
			}

			if (isset($settings['purpose']) && $settings['purpose'] === 'commerce') {
				static::card($links, (int) $section['rubric_id'], $section, $settings);
			}

			return $links;
		}

		protected static function filters(array &$links, array $section, $productsAvailable = true)
		{
			$native = isset($section['filter_runtime']) && $section['filter_runtime'] === 'native';
			if ($native && !$productsAvailable) { return; }
			if ($native) {
				static::add($links, 'filters', '/catalog/attributes', array('view' => 'filters', 'section_id' => (int) $section['id']), 'view_products', $section['name']);
			} elseif (static::allowed('manage_catalog')) {
				static::add($links, 'filters', '/catalog/' . (int) $section['rubric_id'] . '/' . (int) $section['field_id'], array('item' => (int) $section['id'], 'tab' => 'filters'), 'view_catalog', $section['name']);
			}
		}

		protected static function card(array &$links, $rubricId, array $section, array $settings)
		{
			$targets = array('rubric' => (string) $rubricId, 'module' => 'products');
			if (!empty($section['rubric_id'])) { $targets['rubric'] = (string) $section['rubric_id']; }
			if (!empty($section['id'])) { $targets['catalog'] = (string) $section['id']; }
			if (!empty($settings['request_id'])) { $targets['request'] = (string) $settings['request_id']; }
			$scope = isset($section['name']) ? $section['name'] : '';
			$presentation = static::presentation($targets);
			if ($presentation && trim((string) $presentation['published_item_markup']) !== '') {
				if (static::allowed('manage_public_presentations')) {
					static::add($links, 'card', '/public-site/presentations', array('edit' => (int) $presentation['presentation_id']), 'view_public_site', $scope);
				}

				return;
			}

			$template = static::cardTemplate($rubricId);
			if ($template && trim((string) $template['published_markup']) !== '') {
				if (static::allowed('manage_products')) {
					static::add($links, 'card', '/catalog/card-templates', array('edit' => (int) $template['id']), 'view_products', $scope);
				}
			} else {
				static::add($links, 'card', '/catalog/public-templates', array('template' => 'product-card'), 'view_products', $scope);
			}
		}

		protected static function page(array $document)
		{
			$links = array();
			if (!static::allowed('view_rubrics') || !static::allowed('manage_rubrics')) { return $links; }
			$query = array('templates' => (int) $document['rubric_id']);
			$alternate = !empty($document['rubric_tmpl_id']) ? static::alternate($document) : null;
			// DocumentRepository falls back to the rubric template when the alternate is empty.
			if ($alternate && trim((string) $alternate['template']) !== '') { $query['template'] = (int) $alternate['id']; }
			static::add($links, 'page', '/rubrics', $query, 'view_rubrics');
			return $links;
		}

		protected static function alternate(array $document)
		{
			return DB::query('SELECT id,template FROM %b WHERE id=%i AND rubric_id=%i LIMIT 1', ContentTables::table('rubric_templates'), (int) $document['rubric_tmpl_id'], (int) $document['rubric_id'])->getAssoc();
		}

		protected static function presentation(array $targets) { return PresentationRenderer::resolve('content_list', $targets); }

		protected static function cardTemplate($rubricId)
		{
			$settings = CardSettings::forRubric($rubricId);
			return CardTemplateRepository::published((int) $settings['template_id']);
		}

		protected static function allowed($permission) { return Permission::check($permission); }

		protected static function add(array &$links, $kind, $path, array $query, $permission, $scope = '')
		{
			if (!static::allowed($permission)) { return; }
			$url = AdminLocation::url($path);
			$links[] = array(
				'kind' => $kind,
				'url' => $query ? Html::appendQuery($url, $query) : $url,
				'label' => AdminLocale::text('catalog_context_' . $kind),
				'scope' => (string) $scope,
			);
		}
	}
