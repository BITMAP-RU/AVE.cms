<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/PublicSite/PresentationsController.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\PublicSite;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Content\Presentation\PresentationAssignmentRepository;
	use App\Content\Presentation\PresentationDataRegistry;
	use App\Content\Presentation\PresentationPreview;
	use App\Content\Presentation\PresentationRepository;
	use App\Content\Presentation\PresentationRevisionRepository;
	use App\Content\Presentation\PresentationSyntax;
	use App\Helpers\Request;

	class PresentationsController extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_public_site')) {
				return $this->renderStatus('@adminx/404.twig', array('title' => 'Недостаточно прав'), 403);
			}

			AdminAssets::addStyle($this->base() . '/modules/PublicSite/assets/presentations.css', 51);
			AdminAssets::addScript($this->base() . '/modules/PublicSite/assets/presentations.js', 51);
			CodeEditor::useCodeMirror('htmlmixed');
			CodeEditor::useCodeMirror('text/css');
			$query = Request::getStr('q', '');
			$kind = Request::getStr('kind', '');
			$state = Request::getStr('state', '');
			return $this->render('@public_site/presentations.twig', array(
				'available' => PresentationRepository::available(),
				'presentations' => PresentationRepository::all($query, $kind, $state),
				'stats' => PresentationRepository::stats(),
				'kinds' => PresentationRepository::kinds(),
				'filters' => array('q' => $query, 'kind' => $kind, 'state' => $state),
				'data_groups' => PresentationDataRegistry::groups(true),
				'documents' => PresentationModel::documents(),
				'legacy_sources' => PresentationModel::legacySources(),
				'assignment_targets' => PresentationModel::assignmentTargets(),
				'can_manage' => Permission::check('manage_public_presentations'),
				'defaults' => $this->defaults(),
			));
		}

		public function show(array $params = array())
		{
			if (!Permission::check('view_public_site')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = PresentationRepository::one(isset($params['id']) ? (int) $params['id'] : 0);
			if (!$item) { return $this->error('Представление не найдено', array(), 404); }
			$item['assignments'] = PresentationModel::assignments($item['id']);
			return $this->success('', array('data' => $item));
		}

		public function store(array $params = array())
		{
			return $this->persist(0);
		}

		public function update(array $params = array())
		{
			return $this->persist(isset($params['id']) ? (int) $params['id'] : 0);
		}

		public function lint(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$result = PresentationSyntax::checkMany($this->markup(Request::postAll()));
			return $result['ok']
				? $this->success($result['message'], array('data' => $result))
				: $this->error($result['message'], array(), 422);
		}

		public function preview(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try {
				$result = PresentationPreview::render(Request::postInt('preview_document_id', 0), Request::postAll());
				$css = Request::postStr('draft_css', '');
				$html = '<!doctype html><html><head><meta charset="utf-8">'
					. '<meta name="viewport" content="width=device-width,initial-scale=1">'
					. '<style>html,body{margin:0;min-height:100%;background:#f5f7fa;color:#172033;font:14px/1.5 Arial,sans-serif}'
					. 'body{padding:24px}*{box-sizing:border-box}a{color:#2563eb}</style>'
					. ($css !== '' ? '<style>' . $css . '</style>' : '')
					. '</head><body><main>' . $result['markup'] . '</main></body></html>';
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			return $this->success('Предпросмотр обновлён', array('data' => array(
				'html' => $html,
				'json' => $result['json'],
				'document' => array(
					'id' => isset($result['data']['item']['id']) ? (int) $result['data']['item']['id'] : 0,
					'title' => isset($result['data']['item']['title']) ? (string) $result['data']['item']['title'] : '',
				),
			)));
		}

		public function diagnose(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try {
				$result = PresentationModel::diagnose(
					isset($params['id']) ? (int) $params['id'] : 0,
					Request::postAll()
				);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$warnings = isset($result['summary']['warning']) ? (int) $result['summary']['warning'] : 0;
			return $this->success(
				$result['ready'] ? ($warnings ? 'Ошибок нет, есть предупреждения' : 'Представление готово') : 'Найдены ошибки',
				array('data' => $result)
			);
		}

		public function publish(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $item = PresentationRepository::publish($id, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('public_site.presentation_published', $id, array('version' => $item['version']));
			return $this->success('Представление опубликовано', array('data' => $item));
		}

		public function copy(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $id = PresentationRepository::copy(isset($params['id']) ? (int) $params['id'] : 0, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('public_site.presentation_copied', $id, array());
			return $this->success('Копия создана', array('data' => array('id' => $id)));
		}

		public function delete(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			try { $deleted = PresentationRepository::delete($id); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			if (!$deleted) { return $this->error('Представление не найдено', array(), 404); }
			$this->audit('public_site.presentation_deleted', $id, array());
			return $this->success('Представление удалено');
		}

		public function revisions(array $params = array())
		{
			if (!Permission::check('view_public_site')) { return $this->error('Недостаточно прав', array(), 403); }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!PresentationRepository::one($id)) { return $this->error('Представление не найдено', array(), 404); }
			return $this->success('', array('data' => array('revisions' => PresentationRevisionRepository::all($id))));
		}

		public function revision(array $params = array())
		{
			if (!Permission::check('view_public_site')) { return $this->error('Недостаточно прав', array(), 403); }
			$item = PresentationRevisionRepository::one(isset($params['revision']) ? (int) $params['revision'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Ревизия не найдена', array(), 404);
		}

		public function restoreRevision(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $id = PresentationRevisionRepository::restore(isset($params['revision']) ? (int) $params['revision'] : 0, Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit('public_site.presentation_revision_restored', $id, array());
			return $this->success('Ревизия восстановлена в черновик', array('data' => array('id' => $id)));
		}

		public function deleteRevision(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = PresentationRevisionRepository::delete(isset($params['revision']) ? (int) $params['revision'] : 0);
			return $id ? $this->success('Ревизия удалена') : $this->error('Ревизия не найдена', array(), 404);
		}

		public function deleteRevisions(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!PresentationRepository::one($id)) { return $this->error('Представление не найдено', array(), 404); }
			return $this->success('Ревизии удалены', array('data' => array(
				'count' => PresentationRevisionRepository::deleteAll($id),
			)));
		}

		public function saveAssignment(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			$presentationId = isset($params['id']) ? (int) $params['id'] : 0;
			try {
				$input = PresentationModel::validateAssignment(Request::postAll());
				$id = PresentationAssignmentRepository::save($presentationId, $input, Auth::id());
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('public_site.presentation_assignment_saved', $presentationId, array('assignment_id' => $id));
			return $this->success('Назначение сохранено', array('data' => array(
				'assignments' => PresentationModel::assignments($presentationId),
			)));
		}

		public function deleteAssignment(array $params = array())
		{
			if (($error = $this->guard()) !== null) { return $error; }
			PresentationAssignmentRepository::delete(isset($params['assignment']) ? (int) $params['assignment'] : 0);
			return $this->success('Назначение удалено');
		}

		protected function persist($id)
		{
			if (($error = $this->guard()) !== null) { return $error; }
			try { $savedId = PresentationRepository::save((int) $id, Request::postAll(), Auth::id()); }
			catch (\Throwable $e) { return $this->error($e->getMessage(), array(), 422); }
			$this->audit($id > 0 ? 'public_site.presentation_updated' : 'public_site.presentation_created', $savedId, array());
			return $this->success($id > 0 ? 'Черновик сохранён' : 'Представление создано', array(
				'data' => array('id' => $savedId),
			));
		}

		protected function guard()
		{
			return $this->guardPermission('manage_public_presentations');
		}

		protected function markup(array $input)
		{
			return array(
				'Элемент' => isset($input['draft_item_markup']) ? (string) $input['draft_item_markup'] : '',
				'Обёртка' => isset($input['draft_wrapper_markup']) ? (string) $input['draft_wrapper_markup'] : '',
				'Пустой результат' => isset($input['draft_empty_markup']) ? (string) $input['draft_empty_markup'] : '',
			);
		}

		protected function defaults()
		{
			return array(
				'item' => '<article class="content-card">' . "\n"
					. '  <h2><a href="{{ item.url }}">{{ item.title }}</a></h2>' . "\n"
					. '  {% if item.excerpt %}<p>{{ item.excerpt }}</p>{% endif %}' . "\n"
					. '</article>',
				'wrapper' => '<div class="content-list">' . "\n" . '  {{ content|raw }}' . "\n" . '</div>',
				'empty' => '<div class="empty-state">Материалы не найдены.</div>',
			);
		}

		protected function audit($action, $targetId, array $meta)
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'presentation',
				'target_id' => (int) $targetId,
				'meta' => $meta,
			));
		}
	}
