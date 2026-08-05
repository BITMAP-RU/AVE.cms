<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Catalog/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Catalog;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Common\ModuleManager;
	use App\Common\Loader\Load;
	use App\Common\Twig;
	use App\Helpers\Hooks;
	use App\Helpers\Request;
	use App\Adminx\Rubrics\FieldAdminEditors;
	use App\Adminx\Documents\Model as DocumentsModel;
	use App\Adminx\Support\BulkActionExecutor;
	use App\Adminx\Support\CodeEditor;
	use App\Adminx\Support\SavedViews;
	use App\Content\PaymentPrograms;
	use App\Content\Documents\DocumentMediaDraft;
	use App\Content\ProductShipping;
	use App\Frontend\ThemeAssets;
	use App\Adminx\Packages\Products\AttributeNormalization;
	use App\Adminx\Packages\Products\Attributes;
	use App\Adminx\Packages\Products\CardTemplateRevisions;
	use App\Adminx\Packages\Products\CardTemplateSyntax;
	use App\Adminx\Packages\Products\CardTemplates;
	use App\Adminx\Packages\Products\CardContexts;
	use App\Adminx\Packages\Products\FilterTemplateRevisions;
	use App\Adminx\Packages\Products\FilterTemplates;
	use App\Adminx\Packages\Products\QuickEditor;
	use App\Adminx\Packages\Products\QuickEditorColumns;
	use App\Adminx\Packages\Products\QuickEditorProfiles;
	use App\Adminx\Packages\Products\ProductRelations;
	use App\Adminx\Packages\Products\ProductCollections;
	use App\Adminx\Packages\Products\ProductDemands;
	use App\Adminx\Packages\Products\ProductDuplicator;
	use App\Adminx\Packages\Products\ProductReadiness;
	use App\Adminx\Packages\Products\PublicViewTemplates;
	use App\Adminx\Packages\Products\ShippingPackageTemplates;
	use App\Adminx\Packages\Products\VariantGroups;
	use App\Adminx\Packages\Commerce\Promotions;
	use App\Modules\Products\VariantSelectorSettings;
	use App\Modules\Products\ComparisonSettings;
	use App\Modules\Products\DemandService;
	use App\Modules\Products\DemandSettings;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_catalog')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->assets();
			$catalogs = Model::catalogs();
			$items = 0; $active = 0; $filters = 0; $commerce = 0;
			foreach ($catalogs as $catalog) { $items += $catalog['items_count']; $active += $catalog['active_count']; $filters += $catalog['filters_use'] ? 1 : 0; $commerce += $catalog['purpose'] === 'commerce' ? 1 : 0; }
			$productsAvailable = $this->productsAvailable();
			return $this->render('@catalog/index.twig', array(
				'catalogs' => $catalogs,
				'stats' => array('catalogs' => count($catalogs), 'items' => $items, 'active' => $active, 'filters' => $filters, 'commerce' => $commerce),
				'rubric_options' => Model::rubricOptions(),
				'products_available' => $productsAvailable,
				'filter_templates_available' => $productsAvailable && FilterTemplates::available(),
				'can_manage' => Permission::check('manage_catalog'),
			));
		}

		public function products(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			$filters = array(
				'q' => Request::getStr('q', ''),
				'state' => Request::getStr('state', ''),
				'issue' => Request::getStr('issue', ''),
				'payment' => Request::getStr('payment', ''),
				'page' => Request::getInt('page', 1),
				'per_page' => Request::getInt('per_page', 25),
			);
			return $this->render('@products/products.twig', array(
				'result' => Model::products($filters),
				'stats' => Model::productStats(),
				'filters' => $filters,
				'saved_views' => SavedViews::all('catalog_products', Auth::id(), $this->savedViewFields('products')),
				'can_manage' => Permission::check('manage_products'),
				'can_copy' => Permission::check('manage_products') && Permission::check('manage_documents'),
				'can_toggle' => Permission::check('manage_products') && Permission::check('manage_documents'),
			));
		}

		public function productQuality(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			$stats = Model::productStats();
			$issues = Model::productQualityIssues($stats);
			$allowed = array();
			foreach ($issues as $item) { $allowed[(string) $item['code']] = true; }
			$issue = Request::getStr('issue', '');
			if ($issue !== '' && !isset($allowed[$issue])) { $issue = ''; }
			$state = Request::getStr('state', '');
			if (!in_array($state, array('', 'active', 'inactive'), true)) { $state = ''; }
			$filters = array(
				'q' => Request::getStr('q', ''),
				'issue' => $issue,
				'state' => $state,
				'page' => Request::getInt('page', 1),
				'per_page' => Request::getInt('per_page', 25),
				'include_quality' => true,
			);
			return $this->render('@products/product-quality.twig', array(
				'result' => Model::products($filters),
				'stats' => $stats,
				'issues' => $issues,
				'filters' => $filters,
				'saved_views' => SavedViews::all('catalog_quality', Auth::id(), $this->savedViewFields('quality')),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function productComparison(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			return $this->render('@products/product-comparison.twig', array(
				'settings' => ComparisonSettings::get(),
				'templates' => \App\Adminx\Rubrics\Model::templateOptions(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function saveProductComparison(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try {
				$settings = ComparisonSettings::save(Request::postAll());
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_comparison_settings_saved', 0, $settings);
			return $this->success('Настройки сравнения сохранены', array(
				'redirect' => $this->base() . '/catalog/product-comparison',
				'data' => $settings,
			));
		}

		public function productDemands(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			$type = Request::getStr('type', '');
			if (!in_array($type, array('', 'restock', 'preorder', 'price'), true)) { $type = ''; }
			$status = Request::getStr('status', '');
			if (!in_array($status, array('', 'new', 'ready', 'processing', 'notified', 'completed', 'cancelled'), true)) { $status = ''; }
			$filters = array(
				'q' => Request::getStr('q', ''),
				'type' => $type,
				'status' => $status,
				'page' => Request::getInt('page', 1),
				'per_page' => Request::getInt('per_page', 25),
			);
			return $this->render('@products/product-demands.twig', array(
				'result' => ProductDemands::items($filters),
				'stats' => ProductDemands::stats(),
				'filters' => $filters,
				'settings' => DemandSettings::get(),
				'templates' => \App\Adminx\Rubrics\Model::templateOptions(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function saveProductDemandSettings(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try {
				$settings = DemandSettings::save(Request::postAll());
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_demand_settings_saved', 0, $settings);
			return $this->success('Настройки обращений сохранены', array(
				'redirect' => $this->base() . '/catalog/product-demands',
				'data' => $settings,
			));
		}

		public function saveProductDemandStatus(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try {
				$item = ProductDemands::setStatus(
					isset($params['id']) ? (int) $params['id'] : 0,
					Request::postStr('status', '')
				);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_demand_status_changed', (int) $item['id'], array('status' => $item['status']));
			return $this->success('Состояние обращения изменено', array('data' => $item));
		}

		public function notifyProductDemands(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$result = DemandService::notifyReady($id > 0 ? 1 : 100, $id);
			$this->audit('catalog.product_demand_notifications_sent', $id, $result);
			$message = 'Отправлено: ' . (int) $result['sent'] . '.';
			if ($result['skipped'] > 0) { $message .= ' Без email: ' . (int) $result['skipped'] . '.'; }
			if ($result['failed'] > 0) { $message .= ' Ошибок: ' . (int) $result['failed'] . '.'; }
			return $this->success($message, array('data' => $result));
		}

		public function deleteProductDemand(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try {
				$item = ProductDemands::delete(isset($params['id']) ? (int) $params['id'] : 0);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 404);
			}

			$this->audit('catalog.product_demand_deleted', (int) $item['id'], array(
				'type' => $item['request_type'],
				'document_id' => (int) $item['document_id'],
			));
			return $this->success('Обращение удалено');
		}

		public function saveSavedView(array $params = array())
		{
			if (($error = $this->savedViewGuard()) !== null) { return $error; }
			$scope = isset($params['scope']) ? (string) $params['scope'] : '';
			$definition = $this->savedViewDefinition($scope);
			if (!$definition) { return $this->error('Раздел представления не найден', array(), 404); }
			$filters = json_decode(Request::postStr('filters', '{}'), true);
			if (!is_array($filters)) { return $this->error('Некорректный набор фильтров'); }
			try {
				$views = SavedViews::save($definition['scope'], Auth::id(), Request::postStr('title', ''), $filters, $definition['fields']);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage());
			}

			return $this->success('Представление сохранено', array('data' => array('views' => $views)));
		}

		public function deleteSavedView(array $params = array())
		{
			if (($error = $this->savedViewGuard()) !== null) { return $error; }
			$scope = isset($params['scope']) ? (string) $params['scope'] : '';
			$definition = $this->savedViewDefinition($scope);
			if (!$definition) { return $this->error('Раздел представления не найден', array(), 404); }
			try {
				$views = SavedViews::delete($definition['scope'], Auth::id(), isset($params['id']) ? $params['id'] : '', $definition['fields']);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 404);
			}

			return $this->success('Представление удалено', array('data' => array('views' => $views)));
		}

		public function shipping(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->shippingAssets();
			$state = Request::getStr('shipping_state', '');
			$issues = array('ready'=>'shipping_ready','incomplete'=>'shipping','disabled'=>'shipping_disabled');
			if (!isset($issues[$state])) { $state = ''; }
			$filters = array(
				'q' => Request::getStr('q', ''),
				'shipping_state' => $state,
				'per_page' => Request::getInt('per_page', 25),
				'page' => Request::getInt('page', 1),
			);
			return $this->render('@products/shipping.twig', array(
				'result' => Model::products(array(
					'q' => $filters['q'], 'issue' => $state !== '' ? $issues[$state] : '',
					'per_page' => $filters['per_page'], 'page' => $filters['page'],
				)),
					'stats' => Model::productShippingStats(),
					'filters' => $filters,
					'package_templates' => ShippingPackageTemplates::all(),
					'can_manage' => Permission::check('manage_products'),
			));
		}

		public function productCollections(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->collectionAssets();
			return $this->render('@products/product-collections.twig', array(
				'collections' => ProductCollections::all(),
				'stats' => ProductCollections::stats(),
				'categories' => ProductCollections::categories(),
				'source_types' => ProductCollections::sourceTypes(),
				'sort_modes' => ProductCollections::sortModes(),
				'card_templates' => CardTemplates::options(),
				'collections_available' => ProductCollections::available(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function productCollection(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = ProductCollections::one(isset($params['id']) ? (int) $params['id'] : 0);
			return $item ? $this->success('', array('data' => array('collection' => $item)))
				: $this->error('Подборка не найдена', array(), 404);
		}

		public function productCollectionProducts(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			return $this->success('', array('data' => array(
				'items' => ProductCollections::productOptions(Request::getStr('q', ''), 30),
			)));
		}

		public function saveProductCollection(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				$item = ProductCollections::save($id, Request::postAll());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit($id > 0 ? 'catalog.product_collection_saved' : 'catalog.product_collection_created', (int) $item['id'], array(
				'code' => $item['code'],
				'source_type' => $item['source_type'],
				'manual_products' => count($item['manual_ids']),
			));
			return $this->success($id > 0 ? 'Подборка сохранена' : 'Подборка создана', array(
				'data' => array('collection' => $item),
				'redirect' => $this->base() . '/catalog/product-collections',
			));
		}

		public function deleteProductCollection(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!ProductCollections::delete($id)) {
				return $this->error('Подборка не найдена', array(), 404);
			}

			$this->audit('catalog.product_collection_deleted', $id);
			return $this->success('Подборка удалена', array('redirect' => $this->base() . '/catalog/product-collections'));
		}

		public function productRelations(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->relationAssets();
			$type = Request::getStr('type', '');
			return $this->render('@products/product-relations.twig', array(
				'relations' => ProductRelations::relations($type), 'bundles' => ProductRelations::bundles(),
				'types' => ProductRelations::types(), 'stats' => ProductRelations::stats(), 'selected_type' => $type,
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function productRelationProducts(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			return $this->success('', array('data' => array('items' => ProductRelations::productOptions(Request::getStr('q', ''), 40))));
		}

		public function saveProductRelation(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $relation = ProductRelations::saveRelation($id, Request::postAll()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit($id > 0 ? 'catalog.product_relation_saved' : 'catalog.product_relation_created', (int) $relation['id'], array('type' => $relation['relation_type']));
			return $this->success($id > 0 ? 'Связь сохранена' : 'Связь создана', array('data' => array('relation' => $relation), 'redirect' => $this->base() . '/catalog/product-relations'));
		}

		public function deleteProductRelation(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!ProductRelations::deleteRelation($id)) { return $this->error('Связь не найдена', array(), 404); }
			$this->audit('catalog.product_relation_deleted', $id);
			return $this->success('Связь удалена', array('redirect' => $this->base() . '/catalog/product-relations'));
		}

		public function productBundle(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$bundle = ProductRelations::bundle(isset($params['id']) ? (int) $params['id'] : 0);
			return $bundle ? $this->success('', array('data' => array('bundle' => $bundle))) : $this->error('Комплект не найден', array(), 404);
		}

		public function saveProductBundle(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$items = Request::postJsonArray('items');
			if (!is_array($items)) { return $this->error('Некорректный состав комплекта', array(), 422); }
			try { $bundle = ProductRelations::saveBundle($id, Request::postAll(), $items); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit($id > 0 ? 'catalog.product_bundle_saved' : 'catalog.product_bundle_created', (int) $bundle['id'], array('items' => count($bundle['items'])));
			return $this->success($id > 0 ? 'Комплект сохранён' : 'Комплект создан', array('data' => array('bundle' => $bundle), 'redirect' => $this->base() . '/catalog/product-relations#bundles'));
		}

		public function deleteProductBundle(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!ProductRelations::deleteBundle($id)) { return $this->error('Комплект не найден', array(), 404); }
			$this->audit('catalog.product_bundle_deleted', $id);
			return $this->success('Комплект удалён', array('redirect' => $this->base() . '/catalog/product-relations#bundles'));
		}

		public function productShipping(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$product = Model::product($productId);
			if (!$product) { return $this->error('Товар не найден', array(), 404); }

			return $this->success('', array('data' => array(
				'product' => array('id'=>$productId,'title'=>(string)$product['title'],'article'=>(string)$product['article']),
				'profile' => ProductShipping::profile($productId),
			)));
		}

		public function quickEdit(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			AdminAssets::addStyle('/modules/products/admin/assets/quick-edit.css', 53);
			AdminAssets::addScript('/modules/products/admin/assets/quick-edit.js', 52);
			$data = $this->quickEditData();
			if ($this->wantsPartial()) { return $this->partial('@products/quick-edit-table.twig', $data); }
			return $this->render('@products/quick-edit.twig', $data);
		}

		public function cardTemplates(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->cardTemplateAssets();
			return $this->render('@products/card-templates.twig', array(
				'templates' => CardTemplates::all(Request::getStr('q', '')),
				'card_contexts' => CardContexts::all(),
				'card_template_options' => CardTemplates::options(),
				'card_contexts_available' => CardContexts::available(),
				'stats' => CardTemplates::stats(),
				'filters' => array('q' => Request::getStr('q', '')),
				'products' => CardTemplates::productOptions(100),
				'default_markup' => CardTemplates::defaultMarkup(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function publicTemplates(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->publicTemplateAssets();
			return $this->render('@themes/view-overrides.twig', array(
				'page_title' => 'Публичные шаблоны товаров',
				'page_description' => 'Каталог, карточки, страница товара, сравнение и обращения в активной теме.',
				'back_url' => '/catalog/products',
				'back_label' => 'Товары',
				'route_base' => '/catalog/public-templates',
				'list_title' => 'Публичный вывод',
				'tabs_template' => '@products/_tabs.twig',
				'product_tab' => 'public_templates',
				'templates' => PublicViewTemplates::all(),
				'selected' => PublicViewTemplates::one(Request::getStr('template', '')),
				'can_manage' => Permission::check('manage_products') && Permission::check('manage_themes'),
				'can_view_themes' => Permission::check('view_themes'),
			));
		}

		public function lintPublicTemplate(array $params = array())
		{
			if (($error = $this->publicTemplateGuard(false)) !== null) { return $error; }
			$result = PublicViewTemplates::lint(Request::postStr('content', ''));
			return !empty($result['ok'])
				? $this->success($result['message'], array('data' => $result))
				: $this->error($result['message'], array('content' => $result['message']), 422);
		}

		public function savePublicTemplate(array $params = array())
		{
			if (($error = $this->publicTemplateGuard()) !== null) { return $error; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try { $item = PublicViewTemplates::save($code, Request::postStr('content', ''), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.public_template_saved', null, array('theme' => $item['theme'], 'path' => $item['theme_path']));
			return $this->success('Шаблон активной темы сохранён', array('data' => $item));
		}

		public function deletePublicTemplate(array $params = array())
		{
			if (($error = $this->publicTemplateGuard()) !== null) { return $error; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try { $item = PublicViewTemplates::deleteOverride($code, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.public_template_override_deleted', null, array('theme' => $item['theme'], 'path' => $item['theme_path']));
			return $this->success('Переопределение удалено, используется шаблон модуля', array('data' => $item));
		}

		public function cardTemplate(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = CardTemplates::one(isset($params['id']) ? (int) $params['id'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Представление не найдено', array(), 404);
		}

		public function saveCardContext(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$code = isset($params['code']) ? (string) $params['code'] : '';
			try { $context = CardContexts::save($code, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.card_context_saved', null, array(
				'context' => $context['code'],
				'mode' => $context['mode'],
				'template_id' => $context['template_id'],
			));
			return $this->success('Контекст карточки сохранён', array('data' => $context));
		}

		public function filterTemplates(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->cardTemplateAssets();
			return $this->render('@products/filter-templates.twig', array(
				'templates' => FilterTemplates::all(Request::getStr('q', '')),
				'stats' => FilterTemplates::stats(),
				'filters' => array('q' => Request::getStr('q', '')),
				'catalogs' => FilterTemplates::previewOptions(100),
				'default_markup' => FilterTemplates::defaultMarkup(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function filterTemplate(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = FilterTemplates::one(isset($params['id']) ? (int) $params['id'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Представление не найдено', array(), 404);
		}

		public function createFilterTemplate(array $params = array()) { return $this->persistFilterTemplate(0); }
		public function saveFilterTemplate(array $params = array()) { return $this->persistFilterTemplate(isset($params['id']) ? (int) $params['id'] : 0); }

		public function lintFilterTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$result = CardTemplateSyntax::check(Request::postStr('draft_markup', ''));
			return $result['ok'] ? $this->success($result['message'], array('data' => $result))
				: $this->error($result['message'], array('draft_markup' => $result['message']), 422);
		}

		public function previewFilterTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$markup = Request::postStr('draft_markup', ''); $css = Request::postStr('draft_css', '');
			$syntax = CardTemplateSyntax::check($markup);
			if (!$syntax['ok']) { return $this->error($syntax['message'], array('draft_markup' => $syntax['message']), 422); }
			try {
				$this->bootPublicCatalogPreview();
				$catalog = \App\Modules\Products\Repository::forFilterPreviewFromDocumentId(Request::postInt('preview_catalog_id', 0));
				if (!$catalog) { throw new \RuntimeException('Выберите раздел каталога с настроенными фильтрами'); }
				$result = \App\Modules\Products\FilterTemplateRenderer::preview($catalog, $markup, $css);
				$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
					. ThemeAssets::styles()
					. '<link rel="stylesheet" href="/modules/products/app/assets/catalog.css">'
					. '<style>html,body{margin:0;min-height:100%;background:#f5f7fa}body{padding:24px}.catalog-filter-preview-stage{width:min(340px,100%);margin:0 auto;background:#fff}</style>'
					. ($css !== '' ? '<style>' . $css . '</style>' : '') . '</head><body><main class="catalog-filter-preview-stage">'
					. $result['html'] . '</main></body></html>';
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }

			return $this->success('Предпросмотр обновлён', array('data' => array('html' => $html,
				'json' => json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))));
		}

		public function publishFilterTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; } $id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $item = FilterTemplates::publish($id, Auth::id()); } catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.filter_template_published', $id, array('version' => $item['version']));
			return $this->success('Фильтры опубликованы', array('data' => $item));
		}

		public function copyFilterTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $id = FilterTemplates::copy(isset($params['id']) ? (int) $params['id'] : 0, Auth::id()); } catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.filter_template_copied', $id); return $this->success('Копия создана', array('data' => array('id' => $id)));
		}

		public function deleteFilterTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; } $id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $deleted = FilterTemplates::delete($id); } catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			if (!$deleted) { return $this->error('Представление не найдено', array(), 404); }
			$this->audit('catalog.filter_template_deleted', $id); return $this->success('Представление удалено');
		}

		public function filterTemplateRevisions(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); } $id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = FilterTemplates::one($id); if (!$item) { return $this->error('Представление не найдено', array(), 404); }
			return $this->success('', array('data' => array('template' => array('id'=>$id,'title'=>$item['title']), 'revisions'=>FilterTemplateRevisions::listFor($id))));
		}

		public function filterTemplateRevision(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$item=FilterTemplateRevisions::one(isset($params['revision'])?(int)$params['revision']:0);return $item?$this->success('',array('data'=>$item)):$this->error('Ревизия не найдена',array(),404);
		}

		public function restoreFilterTemplateRevision(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try{$id=FilterTemplateRevisions::restore(isset($params['revision'])?(int)$params['revision']:0,Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}
			$this->audit('catalog.filter_template_revision_restored',$id);return $this->success('Ревизия восстановлена в черновик',array('data'=>array('id'=>$id)));
		}

		public function deleteFilterTemplateRevision(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }$id=FilterTemplateRevisions::delete(isset($params['revision'])?(int)$params['revision']:0);
			return $id?$this->success('Ревизия удалена',array('data'=>array('id'=>$id))):$this->error('Ревизия не найдена',array(),404);
		}

		public function deleteFilterTemplateRevisions(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }$id=isset($params['id'])?(int)$params['id']:0;
			if(!FilterTemplates::one($id)){return $this->error('Представление не найдено',array(),404);}
			return $this->success('Ревизии удалены',array('data'=>array('count'=>FilterTemplateRevisions::deleteFor($id))));
		}

		public function createCardTemplate(array $params = array())
		{
			return $this->persistCardTemplate(0);
		}

		public function saveCardTemplate(array $params = array())
		{
			return $this->persistCardTemplate(isset($params['id']) ? (int) $params['id'] : 0);
		}

		public function lintCardTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$result = CardTemplateSyntax::check(Request::postStr('draft_markup', ''));
			return $result['ok']
				? $this->success($result['message'], array('data' => $result))
				: $this->error($result['message'], array('draft_markup' => $result['message']), 422);
		}

		public function previewCardTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$markup = Request::postStr('draft_markup', '');
			$css = Request::postStr('draft_css', '');
			$syntax = CardTemplateSyntax::check($markup);
			if (!$syntax['ok']) { return $this->error($syntax['message'], array('draft_markup' => $syntax['message']), 422); }
			try {
				$this->bootPublicCatalogPreview();
				$item = \App\Modules\Products\ProductCardRepository::find(Request::postInt('preview_product_id', 0));
				if (!$item) { throw new \RuntimeException('Выберите существующий товар'); }
				$card = Twig::twig()->createTemplate($markup, 'catalog-card-admin-preview')->render(array('item' => $item));
				$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
					. '<link rel="stylesheet" href="/templates/public/css/styles.css?2025v012">'
					. '<link rel="stylesheet" href="/templates/public/css/inject.css?2025v012">'
					. '<style>html,body{margin:0;min-height:100%;background:#f5f7fa}body{padding:24px}.catalog-card-preview-stage{max-width:360px;margin:0 auto}.catalog-card-preview-stage .items_list_block{display:block;width:100%}.catalog-card-preview-stage .product_list_col{float:none!important;width:100%!important;max-width:none!important;padding:0!important}</style>'
					. ($css !== '' ? '<style>' . $css . '</style>' : '')
					. '</head><body><main class="catalog-card-preview-stage"><div class="items_list_block clearfix">' . $card . '</div></main></body></html>';
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Предпросмотр обновлён', array('data' => array(
				'html' => $html,
				'json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
			)));
		}

		public function publishCardTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $item = CardTemplates::publish($id, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.card_template_published', $id, array('version' => $item['version']));
			return $this->success('Карточка опубликована', array('data' => $item));
		}

		public function copyCardTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $id = CardTemplates::copy(isset($params['id']) ? (int) $params['id'] : 0, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.card_template_copied', $id);
			return $this->success('Копия создана', array('data' => array('id' => $id)));
		}

		public function deleteCardTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $deleted = CardTemplates::delete($id); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			if (!$deleted) { return $this->error('Представление не найдено', array(), 404); }
			$this->audit('catalog.card_template_deleted', $id);
			return $this->success('Представление удалено');
		}

		public function cardTemplateRevisions(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = CardTemplates::one($id);
			if (!$item) { return $this->error('Представление не найдено', array(), 404); }
			return $this->success('', array('data' => array('template' => array('id' => $id, 'title' => $item['title']), 'revisions' => CardTemplateRevisions::listFor($id))));
		}

		public function cardTemplateRevision(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = CardTemplateRevisions::one(isset($params['revision']) ? (int) $params['revision'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Ревизия не найдена', array(), 404);
		}

		public function restoreCardTemplateRevision(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $id = CardTemplateRevisions::restore(isset($params['revision']) ? (int) $params['revision'] : 0, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.card_template_revision_restored', $id);
			return $this->success('Ревизия восстановлена в черновик', array('data' => array('id' => $id)));
		}

		public function deleteCardTemplateRevision(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = CardTemplateRevisions::delete(isset($params['revision']) ? (int) $params['revision'] : 0);
			return $id ? $this->success('Ревизия удалена', array('data' => array('id' => $id))) : $this->error('Ревизия не найдена', array(), 404);
		}

		public function deleteCardTemplateRevisions(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!CardTemplates::one($id)) { return $this->error('Представление не найдено', array(), 404); }
			return $this->success('Ревизии удалены', array('data' => array('count' => CardTemplateRevisions::deleteFor($id))));
		}

		public function attributes(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->attributeAssets();
			$view = Request::getStr('view', 'overview');
			if (!in_array($view, array('overview', 'normalization', 'attributes', 'sets', 'filters', 'migration', 'sections'), true)) { $view = 'overview'; }
			return $this->render('@products/attributes.twig', array(
				'view' => $view, 'stats' => Attributes::stats(), 'value_types' => Attributes::valueTypes(),
				'attributes' => Attributes::all(Request::getStr('q', ''), Request::getStr('status', '')),
				'product_attributes' => $view === 'migration' ? Attributes::all('', 'active', 'product') : array(),
				'sets' => Attributes::sets(), 'legacy_fields' => $view === 'migration' ? Attributes::legacyFields() : array(),
				'sections' => $view === 'sections' ? Attributes::sections() : array(),
				'section_filters' => $view === 'filters' ? Attributes::sectionFilterEditor(Request::getInt('section_id', 0)) : array(),
				'normalization' => $view === 'normalization' ? AttributeNormalization::audit() : array(),
				'overview' => $view === 'overview' ? Attributes::overview() : array(),
				'filters' => array('q'=>Request::getStr('q',''),'status'=>Request::getStr('status','')),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function prepareAttributeNormalization(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $run = AttributeNormalization::prepare(Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.attribute_normalization_prepared', (int) $run['id'], $run['summary']);
			return $this->success('Черновой контур характеристик подготовлен', array(
				'redirect' => $this->base() . '/catalog/attributes?view=normalization',
				'data' => $run,
			));
		}

		public function approveAttributeNormalization(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $run = AttributeNormalization::approve($id, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.attribute_normalization_approved', $id, $run['summary']);
			return $this->success('Безопасные значения подтверждены, публичный режим не изменён', array(
				'redirect' => $this->base() . '/catalog/attributes?view=normalization',
				'data' => $run,
			));
		}

		public function attributeNormalizationReport(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			$run = AttributeNormalization::run(isset($params['id']) ? (int) $params['id'] : 0);
			return $run ? $this->success('', array('data' => $run)) : $this->error('Прогон не найден', array(), 404);
		}

		public function saveAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $id = Attributes::save($id, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.attribute_saved', $id);
			return $this->success('Характеристика сохранена', array('data'=>array('id'=>$id)));
		}

		public function attribute(array $params = array())
		{
			if(!Permission::check('view_products')){return $this->error('Недостаточно прав',array(),403);}$item=Attributes::one(isset($params['id'])?(int)$params['id']:0);return $item?$this->success('',array('data'=>$item)):$this->error('Характеристика не найдена',array(),404);
		}

		public function deleteAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { Attributes::delete(isset($params['id']) ? (int) $params['id'] : 0); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.attribute_deleted', (int) $params['id']);
			return $this->success('Характеристика удалена');
		}

		public function attributeSet(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$set = Attributes::set(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$set) { return $this->renderStatus('@adminx/404.twig', array('title'=>'Набор не найден'), 404); }
			$this->attributeAssets();
			return $this->render('@products/attribute-set.twig', array('set'=>$set,'attributes'=>Attributes::all('', 'active', 'product'),'can_manage'=>Permission::check('manage_products')));
		}

		public function saveAttributeSet(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id=isset($params['id'])?(int)$params['id']:0;
			try{$id=Attributes::saveSet($id,Request::postAll(),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}
			$this->audit('catalog.attribute_set_saved',$id);return $this->success('Набор сохранён',array('redirect'=>$this->base().'/catalog/attribute-sets/'.$id,'data'=>array('id'=>$id)));
		}

		public function deleteAttributeSet(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{Attributes::deleteSet((int)$params['id']);}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}$this->audit('catalog.attribute_set_deleted',(int)$params['id']);return $this->success('Набор удалён',array('redirect'=>$this->base().'/catalog/attributes?view=sets'));
		}

		public function saveAttributeGroup(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$id=Attributes::saveGroup((int)$params['id'],Request::postInt('group_id',0),Request::postStr('name',''));}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Группа сохранена',array('data'=>array('id'=>$id)));
		}

		public function deleteAttributeGroup(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}Attributes::deleteGroup((int)$params['id'],(int)$params['group']);return $this->success('Группа удалена, характеристики перенесены в «Без группы»');
		}

		public function saveAttributeSetItem(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$id=Attributes::saveSetItem((int)$params['id'],Request::postAll());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Характеристика добавлена',array('data'=>array('id'=>$id)));
		}

		public function deleteAttributeSetItem(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}Attributes::deleteSetItem((int)$params['id'],(int)$params['attribute']);return $this->success('Характеристика удалена из набора');
		}

		public function saveAttributeMapping(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$data=Attributes::saveLegacyMap((int)$params['field'],Request::postAll(),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Сопоставление сохранено',array('data'=>$data));
		}

		public function attributeMappingPreview(array $params = array())
		{
			if(!Permission::check('view_products')){return $this->error('Недостаточно прав',array(),403);}try{$data=Attributes::migrationPreview((int)$params['field']);}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('',array('data'=>$data));
		}

		public function migrateAttributeField(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$data=Attributes::migrate((int)$params['field'],Auth::id(),Request::postBool('verified',false));}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}$this->audit('catalog.attribute_field_migrated',(int)$params['field'],$data);return $this->success('Значения перенесены в новый контур',array('data'=>$data));
		}

		public function verifyAttributeField(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$data=Attributes::verifyMigration((int)$params['field'],Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}$this->audit('catalog.attribute_field_verified',(int)$params['field'],$data);return $this->success('Перенесённые значения подтверждены',array('data'=>$data));
		}

		public function saveAttributeSection(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$data=Attributes::saveSection((int)$params['id'],Request::postAll(),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('Режим раздела сохранён',array('data'=>$data));
		}

		public function saveAttributeSectionFilters(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $data = Attributes::saveSectionFilters($id, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.attribute_section_filters_saved', $id, $data);
			return $this->success('Фильтры раздела сохранены', array(
				'redirect' => $this->base() . '/catalog/attributes?view=filters&section_id=' . $id,
				'data' => $data,
			));
		}

		public function rebuildAttributeShadow(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}try{$data=Attributes::rebuildShadowIndex((int)$params['id']);}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}$this->audit('catalog.attribute_shadow_rebuilt',(int)$params['id'],$data);return $this->success('Теневой индекс раздела перестроен',array('data'=>$data));
		}

		public function attributeShadowReport(array $params = array())
		{
			if(!Permission::check('view_products')){return $this->error('Недостаточно прав',array(),403);}try{$data=Attributes::shadowReport((int)$params['id']);}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}return $this->success('',array('data'=>$data));
		}

		public function saveQuickEdit(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$key = Request::postStr('key', '');
			try { $saved = QuickEditor::save($productId, $key, Request::postStr('value', ''), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array('value' => $e->getMessage()), 422); }
			$this->audit('catalog.product_quick_updated', $productId, $saved);
			return $this->success('Изменение сохранено', array('data' => $saved));
		}

		public function saveQuickEditProfile(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$columns = json_decode(Request::postStr('columns_json', '[]'), true);
			if (!is_array($columns)) { return $this->error('Не удалось прочитать набор колонок', array(), 422); }
			try {
				$columns = QuickEditorColumns::normalize($columns);
				$profile = QuickEditorProfiles::save(
					Request::postInt('id', 0),
					Auth::id(),
					Request::postAll(),
					$columns
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.quick_edit_profile_saved', (int) $profile['id'], array(
				'title' => (string) $profile['title'],
				'columns' => count($columns),
				'shared' => (int) $profile['is_shared'],
			));
			return $this->success('Набор колонок сохранён', array('data' => array('profile' => $profile)));
		}

		public function deleteQuickEditProfile(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!QuickEditorProfiles::delete($id, Auth::id())) {
				return $this->error('Профиль не найден или принадлежит другому пользователю', array(), 404);
			}

			$this->audit('catalog.quick_edit_profile_deleted', $id);
			return $this->success('Набор колонок удалён', array(
				'redirect' => $this->base() . '/catalog/quick-edit',
			));
		}

		public function setProductPaymentProgram(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::product($productId)) { return $this->error('Товар не найден', array(), 404); }
			try { $enabled = PaymentPrograms::setProduct($productId, isset($params['program']) ? $params['program'] : '', Request::postBool('enabled', false)); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.product_payment_program_changed', $productId, array('program' => (string) $params['program'], 'enabled' => $enabled));
			return $this->success(
				$enabled ? 'Платёжная программа доступна товару' : 'Платёжная программа отключена для товара',
				array('data' => array('enabled' => $enabled))
			);
		}

		public function createCatalog(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $catalog = Model::createCatalog(Request::postAll()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.created', $catalog['field_id'], $catalog);
			return $this->success('Каталог создан', array('redirect'=>$this->base().'/catalog/'.$catalog['rubric_id'].'/'.$catalog['field_id'],'data'=>$catalog));
		}

		public function variantGroups(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			if (!Model::hasCommerceCatalogs()) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарные каталоги не настроены'), 404); }
			$this->assets();
			return $this->render('@products/variant-groups.twig', array(
				'groups' => VariantGroups::all(),
				'selector_settings' => VariantSelectorSettings::get(),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function saveVariantSelectorSettings(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$settings = VariantSelectorSettings::save(Request::postAll());
			$this->audit('catalog.variant_selector_settings_saved', 0, $settings);
			return $this->success('Вид выбора вариантов сохранён', array(
				'redirect' => $this->base() . '/catalog/variant-groups',
				'data' => $settings,
			));
		}

		public function variantGroup(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$group = VariantGroups::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$group) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Группа не найдена'), 404); }
			$this->shippingAssets();
			$search = Request::getStr('q', '');
			return $this->render('@products/variant-group.twig', array(
				'group' => $group, 'search' => $search,
				'variant_fields' => VariantGroups::fields((int) $group['id']),
				'variant_attributes' => VariantGroups::nativeAttributes(),
				'package_templates' => ShippingPackageTemplates::all(),
				'candidates' => $search !== '' ? Model::products(array('q' => $search, 'page' => 1)) : array('items' => array()),
				'can_manage' => Permission::check('manage_products'),
			));
		}

		public function saveVariantGroup(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $id = VariantGroups::save($id, Request::postStr('title', ''), Request::postBool('status', false), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_group_saved', $id);
			return $this->success('Группа вариантов сохранена', array('redirect' => $this->base() . '/catalog/variant-groups/' . $id));
		}

		public function addVariantProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { VariantGroups::add((int) $params['id'], Request::postInt('product_id', 0)); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_added', (int) $params['id'], array('product_id' => Request::postInt('product_id', 0)));
			return $this->success('Товар добавлен в группу');
		}

		public function primaryVariantProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { VariantGroups::primary((int) $params['id'], (int) $params['product']); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_primary_changed', (int) $params['id'], array('product_id' => (int) $params['product']));
			return $this->success('Основной вариант изменён');
		}

		public function removeVariantProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { VariantGroups::remove((int) $params['id'], (int) $params['product']); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_removed', (int) $params['id'], array('product_id' => (int) $params['product']));
			return $this->success('Товар исключён из группы');
		}

		public function statusVariantProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$status = Request::postBool('status', false);
			try { VariantGroups::status((int) $params['id'], (int) $params['product'], $status); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_status_changed', (int) $params['id'], array('product_id' => (int) $params['product'], 'status' => $status));
			return $this->success($status ? 'Вариант включён' : 'Вариант выключен');
		}

		public function saveVariantAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { VariantGroups::saveAttribute((int) $params['id'], (int) $params['product'], Request::postInt('field_id', 0), Request::postStr('label', ''), Request::postStr('swatch', '')); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_attribute_saved', (int) $params['id'], array('product_id' => (int) $params['product'], 'field_id' => Request::postInt('field_id', 0)));
			return $this->success('Атрибут варианта сохранён');
		}

		public function deleteVariantAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			VariantGroups::deleteAttribute((int) $params['id'], (int) $params['product'], (int) $params['field']);
			$this->audit('catalog.variant_attribute_deleted', (int) $params['id'], array('product_id' => (int) $params['product'], 'field_id' => (int) $params['field']));
			return $this->success('Атрибут удалён');
		}

		public function saveVariantNativeAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$attributeId = Request::postInt('attribute_id', 0);
			try {
				VariantGroups::saveNativeAttribute(
					(int) $params['id'],
					(int) $params['product'],
					$attributeId,
					Request::postInt('option_id', 0),
					Request::postStr('label', ''),
					Request::postStr('swatch', '')
				);
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_native_attribute_saved', (int) $params['id'], array(
				'product_id' => (int) $params['product'],
				'attribute_id' => $attributeId,
				'option_id' => Request::postInt('option_id', 0),
			));
			return $this->success('Характеристика варианта сохранена');
		}

		public function deleteVariantNativeAttribute(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			VariantGroups::deleteNativeAttribute((int) $params['id'], (int) $params['product'], (int) $params['attribute']);
			$this->audit('catalog.variant_native_attribute_deleted', (int) $params['id'], array(
				'product_id' => (int) $params['product'],
				'attribute_id' => (int) $params['attribute'],
			));
			return $this->success('Характеристика варианта удалена');
		}

		public function reorderVariantProducts(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$order = Request::postJsonArray('order');
			if (!is_array($order)) { return $this->error('Некорректный порядок', array(), 422); }
			try { VariantGroups::reorder((int) $params['id'], $order); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variants_reordered', (int) $params['id'], array('order' => $order));
			return $this->success('Порядок вариантов сохранён');
		}

		public function copyVariantProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$sourceId = (int) $params['product'];
			$newId = 0;
			try {
				$copy = ProductDuplicator::copy($sourceId, Auth::id());
				$newId = (int) $copy['id'];
				VariantGroups::add((int) $params['id'], $newId);
			} catch (\Throwable $e) {
				if ($newId > 0) { try { DocumentsModel::delete($newId); DocumentsModel::purge($newId); } catch (\Throwable $cleanupError) {} }
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.variant_copied', (int) $params['id'], array('source_id' => $sourceId, 'product_id' => $newId));
			return $this->success('Вариант создан копированием', array('redirect' => $this->base() . '/catalog/products/' . $newId . '/edit'));
		}

		public function copyProduct(array $params = array())
		{
			if (($error = $this->guardPermission('manage_products', 'manage_documents')) !== null) { return $error; }
			$sourceId = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				$copy = ProductDuplicator::copy($sourceId, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_copied', (int) $copy['id'], array(
				'source_id' => $sourceId,
				'attributes' => (int) $copy['attributes'],
				'shipping' => !empty($copy['shipping']),
			));
			return $this->success('Копия товара создана', array(
				'id' => (int) $copy['id'],
				'redirect' => $this->base() . '/catalog/products/' . (int) $copy['id'] . '/edit',
			));
		}

		public function toggleProduct(array $params = array())
		{
			if (($error = $this->guardPermission('manage_products', 'manage_documents')) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::product($productId)) { return $this->error('Товар не найден', array(), 404); }
			try {
				$status = DocumentsModel::toggleStatus($productId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			if ($status === false) { return $this->error('Товар не найден', array(), 404); }
			$this->audit('catalog.product_status_changed', $productId, array('status' => (int) $status));
			return $this->success($status ? 'Товар опубликован' : 'Товар снят с публикации', array(
				'data' => array('status' => (int) $status),
			));
		}

		public function deleteVariantGroup(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { VariantGroups::delete((int) $params['id']); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_group_deleted', (int) $params['id']);
			return $this->success('Группа удалена', array('redirect' => $this->base() . '/catalog/variant-groups'));
		}

		public function reindexProducts(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $result = Model::reindexProducts(); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 500); }
			$this->audit('catalog.products_reindexed', null, $result);
			return $this->success('Индекс товаров перестроен', array('data' => $result));
		}

		public function reindexProduct(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if ($id <= 0 || !DocumentsModel::one($id)) { return $this->error('Товар не найден', array(), 404); }
			try {
				$result = Model::reindexDocument($id);
				$product = Model::product($id);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 500);
			}

			$this->audit('catalog.product_reindexed', $id, $result);
			return $this->success('Товар переиндексирован', array('data' => array('product' => $product, 'result' => $result)));
		}

		public function createProductVariantGroup(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$product = Model::product($productId);
			if (!$product) { return $this->error('Товар не найден', array(), 404); }
			if (VariantGroups::membership($productId)) { return $this->error('Товар уже состоит в группе', array(), 422); }
			try {
				$groupId = VariantGroups::save(0, Request::postStr('title', (string) $product['title']), true, Auth::id());
				VariantGroups::add($groupId, $productId);
			} catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.variant_group_created_from_product', $groupId, array('product_id' => $productId));
			return $this->success('Группа вариантов создана', array('redirect' => $this->base() . '/catalog/variant-groups/' . $groupId));
		}

		public function editProduct(array $params = array())
		{
			if (!Permission::check('manage_products') || !Permission::check('manage_documents')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$product = Model::product($id);
			$document = DocumentsModel::one($id);
			if (!$product || !$document) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товар не найден'), 404); }
			AdminAssets::addStyle($this->base() . '/modules/Documents/assets/documents.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Documents/assets/documents.js', 50);
			CodeEditor::useRichEditor();
			$this->attributeAssets();
			$variantGroup = VariantGroups::membership($id);
			$shippingProfile = ProductShipping::profile($id);
			$nativeAttributes = Attributes::productEditor($id);
			$mediaDraftToken = DocumentMediaDraft::issue(Auth::id(), $id);
			return $this->render('@documents/edit.twig', array(
				'document' => $document,
				'field_groups' => DocumentsModel::fieldsForDocument($id, $mediaDraftToken),
				'media_draft_token' => $mediaDraftToken,
				'rubrics' => DocumentsModel::rubrics(),
				'templates' => DocumentsModel::rubricTemplates((int) $document['rubric_id']),
				'navigation_items' => DocumentsModel::navigationItems(),
				'authors' => DocumentsModel::authors(),
				'is_new' => false, 'can_manage' => true, 'quick_edit' => Request::getBool('quick_edit', false),
				'actor_id' => Auth::id(),
				'catalog_mode' => true, 'product' => $product, 'variant_group' => $variantGroup,
					'shipping_profile' => $shippingProfile,
					'package_templates' => ShippingPackageTemplates::all(),
					'native_attributes' => $nativeAttributes,
					'product_readiness' => ProductReadiness::build($product, $nativeAttributes, $shippingProfile, $variantGroup),
					'product_promotions_available' => Promotions::available(),
					'product_promotions' => Promotions::forProduct($id),
					'can_manage_product_promotions' => Permission::check('manage_orders'),
				'return_url' => $this->base() . '/catalog/products',
				'product_copy_url' => $this->base() . '/catalog/products/' . $id . '/copy',
				'submit_url' => $this->base() . '/documents/' . $id,
			));
		}

		public function saveProductPromotion(array $params = array())
		{
			if (($error = $this->productPromotionGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$promotionId = isset($params['promotion']) ? (int) $params['promotion'] : 0;
			$product = Model::product($productId);
			if (!$product) { return $this->error('Товар не найден', array(), 404); }
			if ($promotionId > 0 && !Promotions::isDirectProductRule($promotionId, $productId)) {
				return $this->error('Эта акция управляется через общий конструктор', array(), 409);
			}

			$rewardType = Request::postStr('reward_type', 'percent');
			$targetMode = Request::postStr('target_mode', 'current');
			$targetId = $targetMode === 'current' && $rewardType !== 'gift'
				? $productId
				: Request::postInt('target_product_id', 0);
			$title = Request::postStr('title', '');
			if ($title === '') { $title = 'Акция: ' . (string) $product['title']; }
			$input = array(
				'title' => $title,
				'code' => Request::postStr('code', ''),
				'description' => Request::postStr('description', ''),
				'status' => Request::postBool('status', false),
				'condition_scope' => 'products',
				'condition_product_ids' => array($productId),
				'condition_quantity' => Request::postInt('condition_quantity', 1),
				'condition_min_total' => 0,
				'reward_type' => $rewardType,
				'reward_scope' => 'products',
				'reward_product_ids' => $rewardType === 'gift' ? array() : array($targetId),
				'reward_product_id' => $rewardType === 'gift' ? $targetId : 0,
				'reward_value' => Request::post('reward_value', 0),
				'reward_quantity' => Request::postInt('reward_quantity', 1),
				'max_uses_per_cart' => 0,
				'allow_coupon' => Request::postBool('allow_coupon', false),
				'stop_processing' => false,
				'starts_at' => Request::postStr('starts_at', ''),
				'ends_at' => Request::postStr('ends_at', ''),
				'priority' => Request::postInt('priority', 100),
			);
			try {
				$promotionId = Promotions::save($promotionId, $input);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_promotion_saved', $productId, array('promotion_id' => $promotionId));
			return $this->success('Акция товара сохранена', array('data' => array('id' => $promotionId), 'reload' => true));
		}

		public function toggleProductPromotion(array $params = array())
		{
			if (($error = $this->productPromotionGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$promotionId = isset($params['promotion']) ? (int) $params['promotion'] : 0;
			if (!Promotions::isDirectProductRule($promotionId, $productId)) {
				return $this->error('Эта акция управляется через общий конструктор', array(), 409);
			}

			try {
				Promotions::toggle($promotionId, Request::postBool('active', false));
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_promotion_toggled', $productId, array('promotion_id' => $promotionId));
			return $this->success('Состояние акции обновлено');
		}

		public function deleteProductPromotion(array $params = array())
		{
			if (($error = $this->productPromotionGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$promotionId = isset($params['promotion']) ? (int) $params['promotion'] : 0;
			if (!Promotions::isDirectProductRule($promotionId, $productId)) {
				return $this->error('Эта акция управляется через общий конструктор', array(), 409);
			}

			try {
				Promotions::delete($promotionId);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.product_promotion_deleted', $productId, array('promotion_id' => $promotionId));
			return $this->success('Акция товара удалена', array('reload' => true));
		}

		public function saveProductShipping(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int) $params['id'] : 0;
			$packages = Request::postJsonArray('packages');
			if (!is_array($packages)) { return $this->error('Некорректный список грузовых мест', array(), 422); }
			try { $profile = ProductShipping::save($productId, Request::postBool('shipping_enabled', false), $packages); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.product_shipping_saved', $productId, array('packages' => count($profile['packages']), 'complete' => $profile['complete']));
			return $this->success('Упаковка товара сохранена', array('data' => array('profile' => $profile)));
		}

		public function saveProductAttributes(array $params = array())
		{
			if(($error=$this->productGuard())!==null){return $error;}$productId=isset($params['id'])?(int)$params['id']:0;if(!Model::product($productId)){return $this->error('Товар не найден',array(),404);}try{$data=Attributes::saveProductValues($productId,Request::postAll(),Auth::id());}catch(\Throwable $e){return $this->error($e->getMessage(),array(),422);}$this->audit('catalog.product_attributes_saved',$productId,$data);return $this->success('Характеристики товара сохранены',array('data'=>$data));
		}

		public function copyProductShippingToVariants(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$productId = isset($params['id']) ? (int)$params['id'] : 0; $membership = VariantGroups::membership($productId);
			if (!$membership) { return $this->error('Товар не входит в группу вариантов', array(), 422); }
			$group = VariantGroups::one((int)$membership['id']); $targets = array();
			foreach (isset($group['variants']) ? $group['variants'] : array() as $variant) { $targets[] = (int)$variant['product_id']; }
			try { $done = ProductShipping::copy($productId, $targets); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.product_shipping_copied', $productId, array('group_id'=>(int)$membership['id'],'updated'=>$done));
			return $this->success('Упаковка применена к вариантам: ' . $done, array('data'=>array('updated'=>$done)));
		}

		public function bulkProductShipping(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$ids=Request::postJsonArray('ids');if(!is_array($ids)||count($ids)<1||count($ids)>200){return $this->error('Выберите от 1 до 200 товаров',array(),422);}
			$enabled=Request::postBool('enabled',false);$done=ProductShipping::setEnabled($ids,$enabled);
			$this->audit('catalog.product_shipping_bulk_status',null,array('enabled'=>$enabled,'updated'=>$done));
			return $this->success(($enabled?'Расчёт включён: ':'Расчёт выключен: ').$done,array('data'=>array('updated'=>$done)));
		}

		public function bulkProducts(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$authorId = Auth::id();
			try {
				$result = BulkActionExecutor::execute(
					Request::postStr('action', ''),
					Request::post('ids', array()),
					array(
						'publish' => function ($id) use ($authorId) {
							QuickEditor::save($id, 'active', '1', $authorId);
							return true;
						},
						'disable' => function ($id) use ($authorId) {
							QuickEditor::save($id, 'active', '0', $authorId);
							return true;
						},
						'show_price' => function ($id) use ($authorId) {
							QuickEditor::save($id, 'show_price', '1', $authorId);
							return true;
						},
						'hide_price' => function ($id) use ($authorId) {
							QuickEditor::save($id, 'show_price', '0', $authorId);
							return true;
						},
						'reindex' => function ($id) {
							Model::reindexDocument($id);
							return true;
						},
					),
					300
				);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('catalog.products_bulk_action', null, $result);
			$message = 'Обработано товаров: ' . (int) $result['done'];
			if (!empty($result['errors'])) { $message .= ', ошибок: ' . count($result['errors']); }
			return $this->success($message, array('data' => $result));
		}

		public function shippingPackageTemplates(array $params = array())
		{
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			return $this->success('', array('data' => array('templates' => ShippingPackageTemplates::all())));
		}

		public function saveShippingPackageTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $template = ShippingPackageTemplates::save($id, Request::postAll()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit($id > 0 ? 'catalog.shipping_template_saved' : 'catalog.shipping_template_created', $template['id'], array('title' => $template['title']));
			return $this->success($id > 0 ? 'Шаблон упаковки сохранён' : 'Шаблон упаковки создан', array('data' => array(
				'template' => $template,
				'templates' => ShippingPackageTemplates::all(),
			)));
		}

		public function deleteShippingPackageTemplate(array $params = array())
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$template = ShippingPackageTemplates::one($id);
			if (!$template) { return $this->error('Шаблон упаковки не найден', array(), 404); }
			ShippingPackageTemplates::delete($id);
			$this->audit('catalog.shipping_template_deleted', $id, array('title' => $template['title']));
			return $this->success('Шаблон упаковки удалён', array('data' => array('templates' => ShippingPackageTemplates::all())));
		}

		public function createProduct(array $params = array())
		{
			if (!Permission::check('manage_products') || !Permission::check('manage_documents')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			$allowed = Model::productRubrics();
			$rubricId = Request::getInt('rubric_id', 0);
			if (!in_array($rubricId, $allowed, true)) { $rubricId = !empty($allowed) ? (int) reset($allowed) : 0; }
			if ($rubricId <= 0) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Товарная рубрика не настроена'), 404); }
			$document = DocumentsModel::blank($rubricId);
			AdminAssets::addStyle($this->base() . '/modules/Documents/assets/documents.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Documents/assets/documents.js', 50);
			CodeEditor::useRichEditor(); $this->assets();
			return $this->render('@documents/edit.twig', array(
				'document' => $document, 'field_groups' => DocumentsModel::fieldsForRubric($rubricId, 0),
				'rubrics' => array_values(array_filter(DocumentsModel::rubrics(), function ($rubric) use ($allowed) { return in_array((int) $rubric['Id'], $allowed, true); })),
				'templates' => DocumentsModel::rubricTemplates($rubricId), 'navigation_items' => DocumentsModel::navigationItems(),
				'authors' => DocumentsModel::authors(), 'is_new' => true, 'can_manage' => true,
				'actor_id' => Auth::id(),
				'catalog_mode' => true, 'return_url' => $this->base() . '/catalog/products',
				'submit_url' => $this->base() . '/documents',
				'stay_url_template' => $this->base() . '/catalog/products/{id}/edit',
			));
		}

		public function edit(array $params = array())
		{
			if (!Permission::check('view_catalog')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$rubricId = isset($params['rubric']) ? (int) $params['rubric'] : 0;
			$fieldId = isset($params['field']) ? (int) $params['field'] : 0;
			$catalog = Model::catalog($rubricId, $fieldId);
			if (!$catalog) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Каталог не найден'), 404); }
			$this->assets();
			$fields = Model::rubricFields($rubricId);
			$fieldGroups = array();
			foreach ($fields as &$field) {
				$field['filter_options'] = Model::filterStyleOptions($field['type']);
				$editor = FieldAdminEditors::describe($field['type']);
				$field['type_title'] = isset($editor['title']) && $editor['title'] !== '' ? (string) $editor['title'] : (string) $field['type'];
				$groupId = (int) $field['group_id'];
				if (!isset($fieldGroups[$groupId])) {
					$fieldGroups[$groupId] = array(
						'id' => $groupId,
						'title' => $field['group'] !== '' ? $field['group'] : 'Без группы',
						'items' => array(),
					);
				}

				$fieldGroups[$groupId]['items'][] = &$field;
			}

			unset($field);
			$settings = Model::settings($rubricId, $fieldId);
			$productsAvailable = $this->productsAvailable();
			$filterTemplatesAvailable = $productsAvailable && FilterTemplates::available();
			$settings['fields_default_ids'] = $this->ids(isset($settings['fields_default']) ? $settings['fields_default'] : '');
			$settings['filters_default_ids'] = $this->ids(isset($settings['filters_default']) ? $settings['filters_default'] : '');
			$styles = @unserialize(isset($settings['filters_default_settings']) ? (string) $settings['filters_default_settings'] : '', array('allowed_classes' => false));
			$settings['filter_styles'] = is_array($styles) ? $styles : array();
			return $this->render('@catalog/edit.twig', array(
				'catalog' => $catalog, 'settings' => $settings,
				'tree' => Model::tree($rubricId, $fieldId), 'flat' => Model::items($rubricId, $fieldId),
				'fields' => $fields,
				'field_groups' => array_values($fieldGroups),
				'request_options' => Model::requestOptions(),
				'navigation_options' => Model::navigationOptions(),
				'rubric_options' => Model::rubricOptions(),
				'card_template_options' => $productsAvailable ? CardTemplates::options() : array(),
				'filter_template_options' => $filterTemplatesAvailable ? FilterTemplates::options() : array(),
				'filter_templates_available' => $filterTemplatesAvailable,
				'products_available' => $productsAvailable,
				'can_manage' => Permission::check('manage_catalog'),
			));
		}

		public function item(array $params = array())
		{
			if (!Permission::check('view_catalog')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = Model::item(isset($params['id']) ? (int) $params['id'] : 0);
			if ($item) { $item['condition_context'] = Model::conditionContext($item['id']); }
			return $item ? $this->success('', array('data' => $item)) : $this->error('Раздел не найден', array(), 404);
		}

		public function documents(array $params = array())
		{
			if (!Permission::check('view_catalog')) { return $this->error('Недостаточно прав', array(), 403); }
			return $this->success('', array('data' => array(
				'items' => Model::searchDocuments(Request::getStr('q', ''), Request::getInt('limit', 30)),
			)));
		}

		public function storeItem(array $params = array()) { return $this->persistItem(0, $params); }

		public function updateItem(array $params = array())
		{
			return $this->persistItem(isset($params['id']) ? (int) $params['id'] : 0, $params);
		}

		public function deleteItem(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			$item = Model::item($id);
			if (!$item || $item['rubric_id'] !== (int) $params['rubric'] || $item['field_id'] !== (int) $params['field']) {
				return $this->error('Раздел не найден', array(), 404);
			}

			$deleted = Model::deleteItem($id);
			$this->audit('catalog.item_deleted', $id, array('ids' => $deleted));
			return $this->success('Раздел и вложенные разделы удалены');
		}

		public function reorder(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$rows = Request::postJsonArray('order');
			if (!is_array($rows)) { return $this->error('Некорректный порядок разделов', array(), 422); }
			try { Model::reorder((int) $params['rubric'], (int) $params['field'], $rows); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.reordered', null, array('rubric_id' => (int) $params['rubric'], 'field_id' => (int) $params['field']));
			return $this->success('Порядок разделов сохранён');
		}

		public function setItemStatus(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $status = Model::setItemStatus($id, (int) $params['rubric'], (int) $params['field'], Request::postBool('status', false)); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 404); }
			$this->audit('catalog.item_status_changed', $id, array('status' => $status));
			return $this->success($status ? 'Раздел включён' : 'Раздел скрыт', array('data' => array('status' => $status)));
		}

		public function recompileItem(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $result = Model::recompileItem((int) $params['rubric'], (int) $params['field'], $id); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.filters_recompiled', $id, array('rubric_id' => (int) $params['rubric'], 'field_id' => (int) $params['field']));
			return $this->success('Фильтры раздела пересобраны', array('data' => $result));
		}

		public function saveSettings(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $id = Model::saveSettings((int) $params['rubric'], (int) $params['field'], Request::postAll()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.settings_saved', $id, array('rubric_id' => (int) $params['rubric'], 'field_id' => (int) $params['field']));
			return $this->success('Настройки каталога сохранены');
		}

		public function syncCondition(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$itemId = isset($params['id']) ? (int) $params['id'] : 0;
			$filterId = isset($params['filter']) ? (int) $params['filter'] : 0;
			if (!$this->itemInCatalog($itemId, $params)) { return $this->error('Раздел не найден', array(), 404); }
			try { $context = Model::syncCondition($itemId, $filterId, Request::postBool('force', false)); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), strpos($e->getMessage(), 'отличается') !== false ? 409 : 422); }
			$this->audit('catalog.condition_synced', $itemId, array('field_id' => $filterId, 'request_id' => (int) $context['request']['id']));
			return $this->success('Условие запроса синхронизировано', array('data' => array('condition_context' => $context)));
		}

		public function syncMissingConditions(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$itemId = isset($params['id']) ? (int) $params['id'] : 0;
			if (!$this->itemInCatalog($itemId, $params)) { return $this->error('Раздел не найден', array(), 404); }
			try { $context = Model::syncMissingConditions($itemId); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('catalog.conditions_synced', $itemId, array('created' => (int) $context['created'], 'request_id' => (int) $context['request']['id']));
			$message = (int) $context['created'] > 0 ? 'Недостающие условия созданы: ' . (int) $context['created'] : 'Все доступные условия уже созданы';
			return $this->success($message, array('data' => array('condition_context' => $context)));
		}

		protected function persistItem($id, array $params)
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $saved = Model::saveItem($id, (int) $params['rubric'], (int) $params['field'], Request::postAll()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array('name' => $e->getMessage()), 422); }
			$this->audit($id > 0 ? 'catalog.item_saved' : 'catalog.item_created', $saved, array('rubric_id' => (int) $params['rubric'], 'field_id' => (int) $params['field']));
			return $this->success($id > 0 ? 'Раздел сохранён' : 'Раздел создан', array('data' => array(
				'id' => $saved,
				'condition_context' => Model::conditionContext($saved),
			)));
		}

		protected function persistCardTemplate($id)
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $saved = CardTemplates::save((int) $id, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array('draft_markup' => $e->getMessage()), 422); }
			$this->audit($id > 0 ? 'catalog.card_template_saved' : 'catalog.card_template_created', $saved);
			return $this->success($id > 0 ? 'Черновик сохранён' : 'Представление создано', array('data' => array('id' => $saved)));
		}

		protected function persistFilterTemplate($id)
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			try { $saved = FilterTemplates::save((int) $id, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array('draft_markup' => $e->getMessage()), 422); }
			$this->audit($id > 0 ? 'catalog.filter_template_saved' : 'catalog.filter_template_created', $saved);
			return $this->success($id > 0 ? 'Черновик сохранён' : 'Представление создано', array('data' => array('id' => $saved)));
		}

		protected function bootPublicCatalogPreview()
		{
			$path = BASEPATH . '/modules/products/app';
			Load::regNamespace('App\\Modules\\Products', $path);
			Twig::addPath($path . '/view', 'products_public');
			ThemeAssets::registerViewOverrides();
		}

		protected function itemInCatalog($id, array $params)
		{
			$item = Model::item((int) $id);
			return $item && $item['rubric_id'] === (int) $params['rubric'] && $item['field_id'] === (int) $params['field'];
		}

		protected function guard()
		{
			return $this->guardPermission('manage_catalog');
		}

		protected function productGuard()
		{
			return $this->guardPermission('manage_products');
		}

		protected function productPromotionGuard()
		{
			if (($error = $this->csrfGuard()) !== null) { return $error; }
			if (!Permission::check('manage_products') || !Permission::check('manage_orders')) {
				return $this->error('Недостаточно прав для управления акциями', array(), 403);
			}

			return null;
		}

		protected function savedViewGuard()
		{
			if (($error = $this->csrfGuard()) !== null) { return $error; }
			if (!Permission::check('view_products')) { return $this->error('Недостаточно прав', array(), 403); }
			return null;
		}

		protected function savedViewDefinition($scope)
		{
			$definitions = array(
				'products' => array('scope' => 'catalog_products', 'fields' => $this->savedViewFields('products')),
				'quality' => array('scope' => 'catalog_quality', 'fields' => $this->savedViewFields('quality')),
			);

			return isset($definitions[$scope]) ? $definitions[$scope] : null;
		}

		protected function savedViewFields($scope)
		{
			return $scope === 'quality'
				? array('q', 'issue', 'state', 'per_page')
				: array('q', 'state', 'issue', 'payment', 'per_page');
		}

		protected function quickEditData()
		{
			$filters = array(
				'q' => Request::getStr('q', ''), 'rubric_id' => Request::getInt('rubric_id', 0),
				'category_id' => Request::getInt('category_id', 0), 'state' => Request::getStr('state', ''),
				'incomplete' => Request::getBool('incomplete', false), 'limit' => Request::getInt('limit', 25),
				'page' => Request::getInt('page', 1), 'profile' => Request::getStr('profile', ''),
			);
			$filters = Hooks::filter('catalog.quick_edit.filters', $filters);
			if (!is_array($filters)) { $filters = array(); }
			$profiles = QuickEditorProfiles::all(Auth::id());
			$activeProfile = QuickEditorProfiles::active(isset($filters['profile']) ? $filters['profile'] : '', Auth::id());
			$columns = QuickEditorColumns::normalize(isset($activeProfile['columns']) ? $activeProfile['columns'] : array());
			$filters['profile'] = (string) $activeProfile['id'];
			return array('result' => QuickEditor::items($filters, $columns), 'filters' => $filters,
				'rubrics' => QuickEditor::rubrics(), 'categories' => QuickEditor::categories(),
				'can_manage' => Permission::check('manage_products'),
				'quick_profiles' => $profiles,
				'quick_profile' => array_merge($activeProfile, array('columns' => $columns)),
				'quick_column_groups' => QuickEditorColumns::groups(),
				'quick_profiles_available' => QuickEditorProfiles::available());
		}

		protected function assets()
		{
			AdminAssets::addStyle($this->base() . '/modules/Catalog/assets/catalog.css', 50);
			AdminAssets::addStyle($this->base() . '/modules/Catalog/assets/catalog-drawer.css', 51);
			AdminAssets::addScript($this->base() . '/modules/Catalog/assets/catalog.js', 50);
			if ($this->productsAvailable()) {
				if (is_file(BASEPATH . '/modules/products/admin/assets/products.css')) {
					AdminAssets::addStyle('/modules/products/admin/assets/products.css', 52);
				}

				if (is_file(BASEPATH . '/modules/products/admin/assets/products.js')) {
					AdminAssets::addScript('/modules/products/admin/assets/products.js', 51);
				}
			}
		}

		protected function productsAvailable()
		{
			$module = ModuleManager::get('products');
			return is_array($module)
				&& !empty($module['installed'])
				&& !empty($module['enabled'])
				&& is_file(BASEPATH . '/modules/products/app/module.php')
				&& is_file(BASEPATH . '/modules/products/admin/module.php');
		}

		protected function attributeAssets()
		{
			$this->assets();
			AdminAssets::addStyle('/modules/products/admin/assets/attributes.css', 54);
			AdminAssets::addStyle('/modules/products/admin/assets/attributes-responsive.css', 55);
			AdminAssets::addScript('/modules/products/admin/assets/attributes.js', 54);
		}

		protected function shippingAssets()
		{
			$this->assets();
			AdminAssets::addStyle('/modules/products/admin/assets/shipping.css', 54);
			AdminAssets::addScript('/modules/products/admin/assets/shipping.js', 54);
		}

		protected function relationAssets()
		{
			$this->assets();
			AdminAssets::addStyle('/modules/products/admin/assets/product-relations.css', 54);
			AdminAssets::addScript('/modules/products/admin/assets/product-relations.js', 54);
		}

		protected function collectionAssets()
		{
			$this->assets();
			AdminAssets::addStyle('/modules/products/admin/assets/product-collections.css', 54);
			AdminAssets::addScript('/modules/products/admin/assets/product-collections.js', 54);
		}

		protected function cardTemplateAssets()
		{
			$this->assets();
			CodeEditor::useCodeMirror('htmlmixed');
			CodeEditor::useCodeMirror('text/css');
			AdminAssets::addStyle('/modules/products/admin/assets/card-templates.css', 56);
			AdminAssets::addScript('/modules/products/admin/assets/card-templates.js', 56);
		}

		protected function publicTemplateAssets()
		{
			$this->assets();
			CodeEditor::useCodeMirror('htmlmixed');
			AdminAssets::addStyle(ADMINX_BASE . '/modules/Themes/assets/view-overrides.css', 56);
			AdminAssets::addScript(ADMINX_BASE . '/modules/Themes/assets/view-overrides.js', 56);
		}

		protected function publicTemplateGuard($requireThemePermission = true)
		{
			if (($error = $this->productGuard()) !== null) { return $error; }
			if ($requireThemePermission && !Permission::check('manage_themes')) {
				return $this->error('Для изменения публичной разметки требуется право управления темами', array(), 403);
			}

			return null;
		}

		protected function audit($action, $targetId = null, array $meta = array())
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(), 'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'catalog', 'target_id' => $targetId, 'meta' => $meta,
			));
		}

		protected function ids($value)
		{
			$out = array();
			foreach (preg_split('/[^0-9]+/', (string) $value) ?: array() as $id) {
				if ((int) $id > 0) { $out[] = (int) $id; }
			}

			return array_values(array_unique($out));
		}
	}
