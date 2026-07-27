<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Console/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Console;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\CodeEditor;
	use App\Adminx\Support\PhpCode;
	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;
	use App\Helpers\Response;

	class Controller extends BaseController
	{
		public function index(array $params = array())
		{
			if (!Permission::check('view_console')) {
				Response::forbidden();
				return '';
			}

			AdminAssets::addStyle($this->base() . '/modules/Console/assets/console.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Console/assets/console.js', 50);
			CodeEditor::useCodeMirror('application/x-httpd-php');
			return $this->render('@console/index.twig', array(
				'snippets' => Model::all(),
				'can_manage' => Permission::check('manage_console'),
				'can_execute' => Permission::check('execute_console'),
				'runner_enabled' => Runner::enabled(),
			));
		}

		public function show(array $params = array())
		{
			if (!Permission::check('view_console')) {
				return $this->error('Недостаточно прав', array(), 403);
			}

			$item = Model::one(isset($params['id']) ? (int) $params['id'] : 0);
			return $item ? $this->success('', array('data' => $item)) : $this->error('Сниппет не найден', array(), 404);
		}

		public function save(array $params = array())
		{
			if (($error = $this->guard('manage_console')) !== null) { return $error; }
			$id = Request::postInt('id', 0);
			$name = trim(Request::postStr('name', ''));
			$code = Request::postStr('code', '');
			if ($name === '' || trim($code) === '') {
				return $this->error('Укажите название и код', array('name' => $name === '' ? 'Обязательное поле' : '', 'code' => trim($code) === '' ? 'Обязательное поле' : ''));
			}

			if ($id > 0 && !Model::one($id)) {
				return $this->error('Сниппет не найден', array(), 404);
			}

			$id = Model::save($id, $name, $code, Auth::id());
			$this->audit('console.snippet_saved', $id, array('name' => $name));
			return $this->success('Сниппет сохранён', array('data' => array('id' => $id, 'snippets' => Model::all())));
		}

		public function delete(array $params = array())
		{
			if (($error = $this->guard('manage_console')) !== null) { return $error; }
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			if (!Model::one($id) || !Model::delete($id)) {
				return $this->error('Сниппет не найден', array(), 404);
			}

			$this->audit('console.snippet_deleted', $id);
			return $this->success('Сниппет удалён', array('data' => array('snippets' => Model::all())));
		}

		public function lint(array $params = array())
		{
			if (($error = $this->guard('manage_console')) !== null) { return $error; }
			$result = PhpCode::lint(Request::postStr('code', ''));
			return $result['ok']
				? $this->success($result['message'], array('data' => $result))
				: $this->error($result['message'], array('code' => $result['output']), 422);
		}

		public function execute(array $params = array())
		{
			if (($error = $this->guard('execute_console')) !== null) { return $error; }
			$code = Request::postStr('code', '');
			if (trim($code) === '') {
				return $this->error('Введите PHP-код', array('code' => 'Обязательное поле'));
			}

			try {
				$result = Runner::execute($code);
			} catch (\Throwable $e) {
				$this->audit('console.execution_failed', null, array('error' => $e->getMessage(), 'code_hash' => sha1($code)));
				return $this->error($e->getMessage(), array(), 422);
			}

			$this->audit('console.executed', null, array(
				'code_hash' => sha1($code),
				'duration_ms' => $result['duration_ms'],
				'exit_code' => $result['exit_code'],
				'timed_out' => $result['timed_out'],
			));
			if ($result['ok']) {
				return $this->success('Код выполнен', array('data' => $result));
			}

			return $this->json(array(
				'success' => false,
				'message' => $result['timed_out'] ? 'Превышен лимит времени' : 'Процесс завершился с ошибкой',
				'data' => $result,
				'html' => new \stdClass(),
				'redirect' => null,
				'errors' => new \stdClass(),
			), 422);
		}

		protected function guard($permission)
		{
			return $this->guardPermission($permission);
		}

		protected function audit($action, $targetId = null, array $meta = array())
		{
			$user = Auth::user();
			AuditLog::record($action, array(
				'actor_id' => Auth::id(),
				'actor_name' => $user && isset($user['name']) ? (string) $user['name'] : '',
				'target_type' => 'system_console',
				'target_id' => $targetId,
				'meta' => $meta,
			));
		}
	}
