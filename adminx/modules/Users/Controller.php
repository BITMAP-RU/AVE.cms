<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Users/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Users;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Adminx\Support\BulkActionExecutor;
	use App\Adminx\Support\Roles;
	use App\Helpers\Request;

	/**
	 * CRUD пользователей. Список — server-render (таблица AdminKit), создание/правка —
	 * ajax из drawer-формы по единому контракту success/error (§3).
	 */
	class Controller extends BaseController
	{
		/** GET /users */
		public function index(array $params = [])
		{
			AdminAssets::addStyle($this->base() . '/modules/Users/assets/users.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Users/assets/users.js', 50);

			$search = Request::getStr('q');
			$role   = Request::getStr('role');

			return $this->render('@users/list.twig', [
				'users'      => Model::all($search, $role),
				'roles'      => Roles::map(),
				'stats'      => Model::stats(),
				'q'          => $search,
				'role'       => $role,
				'can_manage' => Permission::check('manage_users'),
				'current_id' => Auth::id(),
			]);
		}

		/** GET /users/{id} — данные пользователя (JSON, для формы правки). */
		public function show(array $params = [])
		{
			$user = Model::find($params['id'] ?? 0);
			if (!$user) {
				return $this->error('Пользователь не найден', [], 404);
			}

			return $this->success('', ['data' => [
				'id'        => (int) $user->id,
				'name'      => $user->name,
				'email'     => $user->email,
				'login'     => $user->login,
				'phone'     => $user->phone,
				'role'      => $user->role,
				'is_active' => (int) $user->is_active,
				'created_at' => $user->created_at,
				'updated_at' => $user->updated_at,
			]]);
		}

		/** POST /users — создать. */
		public function store(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$data   = $this->input();
			$errors = $this->validate($data, 0, true);
			if ($errors) {
				return $this->error('Проверьте поля формы', $errors);
			}

			$id = Model::create($data);
			return $this->success('Пользователь создан', ['redirect' => $this->base() . '/users']);
		}

		/** POST /users/{id} — обновить. */
		public function update(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			$user = Model::find($id);
			if (!$user) {
				return $this->error('Пользователь не найден', [], 404);
			}

			$data = $this->input();
			if ($id === Auth::id()) {
				$data['role'] = (string) $user->role;
				$data['is_active'] = true;
			}

			$errors = $this->validate($data, $id, false);
			if ($errors) {
				return $this->error('Проверьте поля формы', $errors);
			}

			Model::update($id, $data);
			return $this->success('Изменения сохранены', ['redirect' => $this->base() . '/users']);
		}

		/** POST /users/{id}/toggle — вкл/выкл активность. */
		public function toggle(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Пользователь не найден', [], 404);
			}

			if ($id === Auth::id()) {
				return $this->error('Нельзя отключить собственную учётную запись', [], 422);
			}

			$active = Model::toggle($id);
			return $this->success($active ? 'Пользователь включён' : 'Пользователь отключён', [
				'data' => ['is_active' => $active],
			]);
		}

		/** POST /users/{id}/delete — удалить. */
		public function destroy(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$id = (int) ($params['id'] ?? 0);
			if (!Model::find($id)) {
				return $this->error('Пользователь не найден', [], 404);
			}

			if ($id === Auth::id()) {
				return $this->error('Нельзя удалить самого себя', [], 422);
			}

			Model::delete($id);
			return $this->success('Пользователь удалён', ['redirect' => $this->base() . '/users']);
		}

		public function bulk(array $params = array())
		{
			if (($response = $this->guard()) !== null) {
				return $response;
			}

			$action = Request::postStr('action', '');
			$currentId = (int) Auth::id();
			$handler = function ($id) use ($action, $currentId) {
				if ((int) $id === $currentId || !Model::find($id)) {
					return false;
				}

				Model::setActive($id, $action === 'activate');
				return true;
			};

			try {
				$result = BulkActionExecutor::execute($action, Request::post('ids', array()), array(
					'activate' => $handler,
					'deactivate' => $handler,
				), 200);
			} catch (\InvalidArgumentException $e) {
				return $this->error($e->getMessage(), array(), 422);
			}

			AuditLog::record('user.bulk_status_updated', array(
				'actor_id' => Auth::id(),
				'target_type' => 'user',
				'meta' => array(
					'action' => $action,
					'requested' => $result['requested'],
					'done' => $result['done'],
					'skipped' => $result['skipped'],
				),
			));

			return $this->success('Состояние пользователей изменено', array('data' => $result));
		}

		// ------------------------------------------------------------------ //

		/** CSRF + право на изменение. */
		protected function guard()
		{
			return $this->guardPermission('manage_users');
		}

		protected function input()
		{
			return [
				'name'      => trim(Request::postStr('name')),
				'email'     => trim(Request::postStr('email')),
				'login'     => trim(Request::postStr('login')),
				'phone'     => trim(Request::postStr('phone')),
				'role'      => Request::postStr('role'),
				'password'  => (string) Request::post('password', ''),
				'is_active' => Request::postBool('is_active', false),
			];
		}

		/** @return array field => message */
		protected function validate(array $data, $id, $isCreate)
		{
			$errors = [];

			if ($data['name'] === '') {
				$errors['name'] = 'Укажите имя.';
			}

			if ($data['email'] === '') {
				$errors['email'] = 'Укажите email.';
			} elseif (!preg_match('/^[^@\s]+@[^@\s]+$/', $data['email'])) {
				//-- Допускаем локальные адреса вида user@local (демо/дев), которые
				//-- FILTER_VALIDATE_EMAIL отвергает из-за отсутствия TLD.
				$errors['email'] = 'Некорректный email.';
			} elseif (Model::emailExists($data['email'], (int) $id)) {
				$errors['email'] = 'Этот email уже занят.';
			}

			if ($data['login'] !== '' && Model::loginExists($data['login'], (int) $id)) {
				$errors['login'] = 'Этот логин уже занят.';
			}

			if (!in_array($data['role'], Roles::codes(), true)) {
				$errors['role'] = 'Выберите роль.';
			}

			if ($isCreate && strlen($data['password']) < 6) {
				$errors['password'] = 'Пароль не короче 6 символов.';
			} elseif (!$isCreate && $data['password'] !== '' && strlen($data['password']) < 6) {
				$errors['password'] = 'Пароль не короче 6 символов.';
			}

			return $errors;
		}
	}
