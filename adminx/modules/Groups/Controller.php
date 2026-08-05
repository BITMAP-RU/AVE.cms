<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Groups/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Groups;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\AdminAssets;
	use App\Common\AuditLog;
	use App\Common\Auth;
	use App\Common\Controller as BaseController;
	use App\Common\Permission;
	use App\Helpers\Request;

	/**
	 * Роли и права (RBAC). Список ролей — server-render; создание/правка и назначение
	 * прав — ajax из drawer по контракту success/error. Роль `admin` защищена
	 * (полный доступ, не редактируется/не удаляется).
	 */
	class Controller extends BaseController
	{
		/** GET /roles */
		public function index(array $params = [])
		{
			AdminAssets::addStyle($this->base() . '/modules/Groups/assets/groups.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Groups/assets/groups.js', 50);

			return $this->render('@groups/list.twig', [
				'roles'       => Model::all(),
				'permissions' => Model::allPermissionsGrouped(),
				'permission_module_labels' => Model::permissionModuleLabels(),
				'stats'       => Model::stats(),
				'can_manage'  => Permission::check('manage_roles'),
			]);
		}

		/** GET /roles/simulator — read-only explanation of effective access. */
		public function simulator(array $params = [])
		{
			AdminAssets::addStyle($this->base() . '/modules/Groups/assets/groups.css', 50);
			AdminAssets::addScript($this->base() . '/modules/Groups/assets/groups.js', 50);

			$options = PermissionSimulator::options();
			$role = Request::getStr('role', '');
			$user = Request::getInt('user', 0);
			try {
				$result = PermissionSimulator::simulate($role, $user);
			} catch (\Throwable $e) {
				$result = PermissionSimulator::simulate('admin', 0);
			}

			return $this->render('@groups/simulator.twig', [
				'title' => 'Симулятор прав',
				'options' => $options,
				'result' => $result,
			]);
		}

		/** GET /roles/{id} — роль + её права (JSON, для формы). */
		public function show(array $params = [])
		{
			$role = Model::find($params['id'] ?? 0);
			if (!$role) {
				return $this->error('Роль не найдена', [], 404);
			}

			return $this->success('', ['data' => [
				'id'          => (int) $role->id,
				'code'        => $role->code,
				'name'        => $role->name,
				'is_system'   => (int) $role->is_system,
				'permissions' => Model::permissionCodes($role->id),
			]]);
		}

		/** POST /roles — создать роль. */
		public function store(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$code = strtolower(trim(Request::postStr('code')));
			$name = trim(Request::postStr('name'));

			$errors = [];
			if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
				$errors['code'] = 'Код: латиница/цифры/подчёркивание, с буквы.';
			} elseif (Model::codeExists($code)) {
				$errors['code'] = 'Роль с таким кодом уже есть.';
			}

			if ($name === '') {
				$errors['name'] = 'Укажите название.';
			}

			if ($errors) {
				return $this->error('Проверьте поля формы', $errors);
			}

			$id = Model::create($code, $name);
			Model::setPermissions($id, $this->postedPerms());

			return $this->success('Роль создана', ['redirect' => $this->base() . '/roles']);
		}

		/** POST /roles/{id}/copy — новая роль с тем же набором прав. */
		public function copy(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$role = Model::find($params['id'] ?? 0);
			if (!$role) {
				return $this->error('Роль не найдена', [], 404);
			}

			//-- Полный доступ admin держится фолбэком ядра, а не строками прав:
			//-- копия получилась бы пустой, поэтому честно отказываем.
			if ((string) $role->code === 'admin') {
				return $this->error('Роль «admin» копировать нельзя: её полный доступ не хранится списком прав', [], 422);
			}

			$code = strtolower(trim(Request::postStr('code')));
			if ($code === '') { $code = Model::freeCode((string) $role->code); }
			$name = trim(Request::postStr('name'));
			if ($name === '') { $name = mb_substr((string) $role->name . ' — копия', 0, 190, 'UTF-8'); }

			$errors = [];
			if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
				$errors['code'] = 'Код: латиница/цифры/подчёркивание, с буквы.';
			} elseif (Model::codeExists($code)) {
				$errors['code'] = 'Роль с таким кодом уже есть.';
			}

			if ($errors) {
				return $this->error('Проверьте поля формы', $errors);
			}

			try {
				$newId = Model::copy((int) $role->id, $code, $name);
			} catch (\Throwable $e) {
				return $this->error($e->getMessage(), [], 422);
			}

			AuditLog::record('role.copied', [
				'actor_id' => Auth::id(),
				'target_type' => 'role',
				'target_id' => (int) $newId,
				'meta' => ['source_id' => (int) $role->id, 'source_code' => (string) $role->code, 'code' => $code],
			]);
			return $this->success('Роль скопирована', ['redirect' => $this->base() . '/roles']);
		}

		/** POST /roles/{id} — обновить название и права. */
		public function update(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$role = Model::find($params['id'] ?? 0);
			if (!$role) {
				return $this->error('Роль не найдена', [], 404);
			}

			$name = trim(Request::postStr('name'));
			if ($name === '') {
				return $this->error('Проверьте поля формы', ['name' => 'Укажите название.']);
			}

			Model::update($role->id, $name);

			//-- admin — всегда полный доступ (fallback ядра), права не редактируем.
			if ($role->code !== 'admin') {
				Model::setPermissions($role->id, $this->postedPerms());
			}

			return $this->success('Изменения сохранены', ['redirect' => $this->base() . '/roles']);
		}

		/** POST /roles/{id}/delete — удалить роль. */
		public function destroy(array $params = [])
		{
			if (($resp = $this->guard()) !== null) {
				return $resp;
			}

			$role = Model::find($params['id'] ?? 0);
			if (!$role) {
				return $this->error('Роль не найдена', [], 404);
			}

			if ((int) $role->is_system === 1) {
				return $this->error('Системную роль нельзя удалить', [], 422);
			}

			$used = Model::usersCount($role->code);
			if ($used > 0) {
				return $this->error('Роль назначена пользователям (' . $used . '), сначала переназначьте их', [], 422);
			}

			Model::delete($role->id);
			return $this->success('Роль удалена', ['redirect' => $this->base() . '/roles']);
		}

		// ------------------------------------------------------------------ //

		protected function guard()
		{
			return $this->guardPermission('manage_roles');
		}

		/** Отмеченные права из формы (perms[]). */
		protected function postedPerms()
		{
			$perms = Request::post('perms', []);
			return is_array($perms) ? $perms : [];
		}
	}
