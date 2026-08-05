<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Content\Introspection\IntrospectionService;
	use App\Helpers\Json;
	use App\Helpers\Request;
	use App\Adminx\Support\CodeEditor;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_public_site')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/PublicSite/assets/public-site.css', 50);
			$query = Request::getStr('q', '');
			$structure = Model::structure($query);

			return $this->render('@public_site/index.twig', array(
				'stats' => Model::stats(),
				'structure' => $structure,
				'diagnostics' => Model::diagnostics($structure),
				'filters' => array('q' => $query),
			));
		}

		public function placements(array $params = array())
		{
			if (!Permission::check('view_public_site')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/PublicSite/assets/public-site.css', 50);
			$filters = array(
				'q' => Request::getStr('q', ''),
				'type' => Request::getStr('type', ''),
				'state' => Request::getStr('state', ''),
				'area' => Request::getStr('area', ''),
				'view' => in_array(Request::getStr('view', 'placements'), array('usage', 'dependencies', 'unused'), true)
					? Request::getStr('view', 'placements') : 'placements',
				'page' => max(1, Request::getInt('page', 1)),
			);

			return $this->render('@public_site/placements.twig', array(
				'placement_map' => Model::placements($filters),
				'filters' => $filters,
				'placement_view' => $filters['view'],
				'rebuilt' => Request::getInt('rebuilt', 0) === 1,
			));
		}

		public function siteMap(array $params = array())
		{
			if (!Permission::check('view_public_site')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/PublicSite/assets/public-site.css', 50);
			return $this->render('@public_site/map.twig', array(
				'site_map' => Model::siteMap(Request::getStr('q', '')),
				'filters' => array('q' => Request::getStr('q', '')),
			));
		}

		public function diagnostics(array $params = array())
		{
			if (!Permission::check('view_public_site')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/PublicSite/assets/public-site.css', 50);
			$target = trim(Request::getStr('target', ''));
			$requestId = max(0, Request::getInt('request_id', 0));
			$documentId = max(0, Request::getInt('document_id', 0));
			$parametersInput = trim(Request::getStr('parameters', ''));
			$page = null;
			$request = null;
			$pageError = '';
			$requestError = '';
			$service = new IntrospectionService();

			if ($target !== '') {
				try {
					$page = $service->page($target);
				} catch (\Throwable $e) {
					$pageError = $e->getMessage();
				}
			}

			if ($requestId > 0 || $documentId > 0 || $parametersInput !== '') {
				try {
					if ($requestId <= 0 || $documentId <= 0) {
						throw new \InvalidArgumentException('Укажите ID запроса и документа');
					}

					$request = $service->request($requestId, $documentId, $this->diagnosticParameters($parametersInput));
				} catch (\Throwable $e) {
					$requestError = $e->getMessage();
				}
			}

			return $this->render('@public_site/diagnostics.twig', array(
				'target' => $target,
				'page_context' => $page,
				'page_error' => $pageError,
				'page_json' => $page ? Json::encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : '',
				'request_id' => $requestId,
				'document_id' => $documentId,
				'parameters_input' => $parametersInput,
				'request_explanation' => $request,
				'request_error' => $requestError,
				'request_json' => $request ? Json::encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : '',
			));
		}

		public function publicTemplates(array $params = array())
		{
			if (!Permission::check('view_public_site')) { return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403); }
			$this->publicTemplateAssets();
			return $this->render('@themes/view-overrides.twig', array(
				'page_title' => 'Шаблоны публичного сайта',
				'page_description' => 'Общие списки материалов и компоненты активной темы.',
				'back_url' => '/public-site', 'back_label' => 'Публичный сайт',
				'route_base' => '/public-site/templates', 'list_title' => 'Сборка страницы',
				'tabs_template' => '@public_site/_tabs.twig', 'public_site_tab' => 'templates',
				'templates' => PublicViewTemplates::all(),
				'selected' => PublicViewTemplates::one(Request::getStr('template', '')),
				'can_manage' => Permission::check('manage_public_presentations') && Permission::check('manage_themes'),
				'can_view_themes' => Permission::check('view_themes'),
			));
		}

		public function lintPublicTemplate(array $params = array())
		{
			if (($error = $this->guardPermission('manage_public_presentations')) !== null) { return $error; }
			$result = PublicViewTemplates::lint(Request::postStr('content', ''));
			return !empty($result['ok']) ? $this->success($result['message'], array('data' => $result)) : $this->error($result['message'], array(), 422);
		}

		public function savePublicTemplate(array $params = array())
		{
			if (($error = $this->templateGuard()) !== null) { return $error; }
			try { $item = PublicViewTemplates::save(isset($params['code']) ? $params['code'] : '', Request::postStr('content', ''), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			AuditLog::record('public_site.template_saved', array('actor_id' => Auth::id(), 'target_type' => 'theme', 'meta' => array('theme' => $item['theme'], 'path' => $item['theme_path'])));
			return $this->success('Публичный шаблон сохранён', array('data' => $item));
		}

		public function deletePublicTemplate(array $params = array())
		{
			if (($error = $this->templateGuard()) !== null) { return $error; }
			try { $item = PublicViewTemplates::deleteOverride(isset($params['code']) ? $params['code'] : '', Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			AuditLog::record('public_site.template_override_deleted', array('actor_id' => Auth::id(), 'target_type' => 'theme', 'meta' => array('theme' => $item['theme'], 'path' => $item['theme_path'])));
			return $this->success('Используется резервный шаблон', array('data' => $item));
		}

		public function rebuildPlacements(array $params = array())
		{
			if (($error = $this->guardPermission('view_public_site')) !== null) {
				return $error;
			}

			Model::placements(array(), true);
			$this->redirect($this->base() . '/public-site/placements?rebuilt=1');
		}

		protected function diagnosticParameters($input)
		{
			$input = trim((string) $input);
			if ($input === '') {
				return array();
			}

			if (substr($input, 0, 1) === '{') {
				$decoded = Json::decode($input);
				if (!is_array($decoded)) {
					throw new \InvalidArgumentException('JSON параметров должен содержать объект');
				}

				return $decoded;
			}

			$result = array();
			parse_str($input, $result);
			return is_array($result) ? $result : array();
		}

		protected function templateGuard()
		{
			if (($error = $this->guardPermission('manage_public_presentations')) !== null) { return $error; }
			return Permission::check('manage_themes') ? null : $this->error('Для изменения публичной разметки требуется право управления темами', array(), 403);
		}

		protected function publicTemplateAssets()
		{
			CodeEditor::useCodeMirror('htmlmixed');
			AdminAssets::addStyle(ADMINX_BASE . '/modules/Themes/assets/view-overrides.css', 56);
			AdminAssets::addScript(ADMINX_BASE . '/modules/Themes/assets/view-overrides.js', 56);
		}
	}
