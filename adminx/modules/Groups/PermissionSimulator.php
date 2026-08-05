<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Groups/PermissionSimulator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Groups;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Language;
	use App\Common\Navigation;
	use App\Common\Permission;
	use App\Common\PublicAuthSettings;
	use App\Common\SystemTables;
	use App\Content\ContentTables;
	use App\Content\PublicUserTables;
	use App\Frontend\RubricPermissionResolver;
	use DB;

	/** Read-only explanation of effective panel and public-content permissions. */
	class PermissionSimulator
	{
		public static function options()
		{
			return array(
				'roles' => DB::query(
					'SELECT id, code, name, is_system FROM ' . SystemTables::table('roles')
						. ' ORDER BY is_system DESC, name ASC'
				)->getAll() ?: array(),
				'users' => DB::query(
					'SELECT id, name, email, role, is_active FROM ' . SystemTables::table('users')
						. ' ORDER BY name ASC, id ASC'
				)->getAll() ?: array(),
			);
		}

		public static function simulate($roleCode, $userId)
		{
			$subject = self::subject($roleCode, $userId);
			$permissionCodes = Permission::forRole($subject['role_code']);
			$all = $subject['role_code'] === 'admin';
			$permissionMap = array_fill_keys($permissionCodes, true);
			if ($all) {
				$permissionMap['all_permissions'] = true;
			}

			$can = function ($code) use ($all, $permissionMap) {
				return $all || isset($permissionMap[(string) $code]);
			};

			$sections = array();
			foreach (Navigation::all() as $item) {
				$visible = isset($item['visible']) ? $item['visible'] : true;
				$stateVisible = is_callable($visible) ? (bool) call_user_func($visible) : (bool) $visible;
				$permission = isset($item['permission']) ? (string) $item['permission'] : '';
				$allowed = $stateVisible && ($permission === '' || $can($permission));
				$sections[] = array(
					'code' => (string) $item['code'],
					'label' => Language::translateSource((string) $item['label']),
					'group' => Language::translateSource((string) $item['group']),
					'url' => (string) $item['url'],
					'icon' => (string) $item['icon'],
					'permission' => $permission,
					'allowed' => $allowed,
					'reason' => !$stateVisible
						? 'Раздел скрыт текущим состоянием системы'
						: self::permissionReason($permission, $allowed, $all),
				);
			}

			$actions = array();
			$allowedActions = 0;
			$moduleLabels = Model::permissionModuleLabels();
			foreach (Model::allPermissionsGrouped() as $module => $permissions) {
				$rows = array();
				$allowedInModule = 0;
				foreach ($permissions as $permission) {
					$allowed = $can($permission['code']);
					if ($allowed) {
						$allowedActions++;
						$allowedInModule++;
					}

					$permission['allowed'] = $allowed;
					$permission['reason'] = self::permissionReason($permission['code'], $allowed, $all);
					$rows[] = $permission;
				}

				$actions[] = array(
					'module' => $module,
					'label' => isset($moduleLabels[$module])
						? $moduleLabels[$module]
						: $module,
					'allowed_count' => $allowedInModule,
					'items' => $rows,
				);
			}

			$rubrics = self::rubrics($subject['public_group_id'], $can);
			$documents = self::documents($rubrics, $can);
			$allowedSections = count(array_filter($sections, function ($item) {
				return !empty($item['allowed']);
			}));
			$readableRubrics = count(array_filter($rubrics, function ($item) {
				return !empty($item['public_read']);
			}));

			return array(
				'subject' => $subject,
				'permissions' => array_values($permissionCodes),
				'sections' => $sections,
				'actions' => $actions,
				'rubrics' => $rubrics,
				'documents' => $documents,
				'summary' => array(
					'sections_allowed' => $allowedSections,
					'sections_total' => count($sections),
					'actions_allowed' => $allowedActions,
					'actions_total' => array_sum(array_map(function ($group) {
						return count($group['items']);
					}, $actions)),
					'rubrics_readable' => $readableRubrics,
					'rubrics_total' => count($rubrics),
					'documents_visible' => count(array_filter($documents, function ($item) {
						return !empty($item['public_read']);
					})),
				),
			);
		}

		protected static function subject($roleCode, $userId)
		{
			$userId = (int) $userId;
			$user = null;
			if ($userId > 0) {
				$user = DB::query(
					'SELECT u.id, u.name, u.email, u.role, u.is_active, u.legacy_id,'
						. ' pu.user_group, pug.user_group_name'
						. ' FROM ' . SystemTables::table('users') . ' u'
						. ' LEFT JOIN ' . PublicUserTables::table('users') . ' pu ON pu.Id = u.legacy_id'
						. ' LEFT JOIN ' . PublicUserTables::table('user_groups') . ' pug ON pug.user_group = pu.user_group'
						. ' WHERE u.id = %i LIMIT 1',
					$userId
				)->getAssoc();
			}

			if ($user) {
				$roleCode = (string) $user['role'];
			} else {
				$roleCode = trim((string) $roleCode);
				if ($roleCode === '') {
					$roleCode = 'admin';
				}
			}

			$role = DB::query(
				'SELECT id, code, name, is_system FROM ' . SystemTables::table('roles')
					. ' WHERE code = %s LIMIT 1',
				$roleCode
			)->getAssoc();
			if (!$role) {
				throw new \InvalidArgumentException('Выбранная роль не найдена');
			}

			$publicGroupId = $user && !empty($user['user_group'])
				? (int) $user['user_group']
				: self::defaultPublicGroup($roleCode);
			$publicGroupName = $user && !empty($user['user_group_name'])
				? (string) $user['user_group_name']
				: (string) DB::query(
					'SELECT user_group_name FROM ' . PublicUserTables::table('user_groups')
						. ' WHERE user_group = %i LIMIT 1',
					$publicGroupId
				)->getValue();

			return array(
				'type' => $user ? 'user' : 'role',
				'user_id' => $user ? (int) $user['id'] : 0,
				'user_name' => $user ? (string) $user['name'] : '',
				'user_email' => $user ? (string) $user['email'] : '',
				'user_active' => $user ? (bool) $user['is_active'] : true,
				'role_id' => (int) $role['id'],
				'role_code' => (string) $role['code'],
				'role_name' => (string) $role['name'],
				'public_group_id' => $publicGroupId,
				'public_group_name' => $publicGroupName !== '' ? $publicGroupName : 'Группа #' . $publicGroupId,
			);
		}

		protected static function rubrics($publicGroupId, callable $can)
		{
			$rows = DB::query(
				'SELECT r.Id, r.rubric_title, r.rubric_alias, r.rubric_purpose,'
					. ' rp.rubric_permission, COUNT(d.Id) AS document_count'
					. ' FROM ' . ContentTables::table('rubrics') . ' r'
					. ' LEFT JOIN ' . ContentTables::table('rubric_permissions') . ' rp'
					. ' ON rp.rubric_id = r.Id AND rp.user_group_id = %i'
					. ' LEFT JOIN ' . ContentTables::table('documents') . ' d'
					. ' ON d.rubric_id = r.Id AND d.document_deleted = %s'
					. ' GROUP BY r.Id, r.rubric_title, r.rubric_alias, r.rubric_purpose, rp.rubric_permission'
					. ' ORDER BY r.rubric_position ASC, r.rubric_title ASC',
				(int) $publicGroupId,
				'0'
			)->getAll() ?: array();

			$resolver = new RubricPermissionResolver();
			$result = array();
			foreach ($rows as $row) {
				$row = (array) $row;
				$permissions = self::parseRubricPermissions($row['rubric_permission']);
				$row['permissions'] = $permissions;
				$row['panel_view'] = $can('view_rubrics');
				$row['panel_manage'] = $can('manage_rubrics');
				$row['documents_panel_view'] = $can('view_documents');
				$row['documents_panel_manage'] = $can('manage_documents');
				$row['public_read'] = $resolver->allows($permissions, 'docread');
				$row['public_create'] = $resolver->allows($permissions, 'new')
					|| $resolver->allows($permissions, 'newnow');
				$row['public_edit'] = $resolver->allows($permissions, 'editall')
					|| $resolver->allows($permissions, 'editown');
				$row['reason'] = $row['public_read']
					? 'Группа имеет право чтения или полный доступ'
					: 'Для публичной группы не назначено docread';
				$result[] = $row;
			}

			return $result;
		}

		protected static function documents(array $rubrics, callable $can)
		{
			$rubricMap = array();
			foreach ($rubrics as $rubric) {
				$rubricMap[(int) $rubric['Id']] = $rubric;
			}

			$rows = DB::query(
				'SELECT d.Id, d.document_title, d.document_alias, d.document_status,'
					. ' d.document_deleted, d.rubric_id, r.rubric_title'
					. ' FROM ' . ContentTables::table('documents') . ' d'
					. ' LEFT JOIN ' . ContentTables::table('rubrics') . ' r ON r.Id = d.rubric_id'
					. ' ORDER BY d.document_changed DESC, d.Id DESC LIMIT 100'
			)->getAll() ?: array();

			$result = array();
			foreach ($rows as $row) {
				$row = (array) $row;
				$rubric = isset($rubricMap[(int) $row['rubric_id']]) ? $rubricMap[(int) $row['rubric_id']] : array();
				$row['panel_view'] = $can('view_documents');
				$row['panel_edit'] = $can('manage_documents');
				$row['public_read'] = !empty($rubric['public_read']) && (string) $row['document_deleted'] !== '1';
				if ((string) $row['document_deleted'] === '1') {
					$row['reason'] = 'Документ удалён и публично отдаёт 404';
				} elseif (!empty($rubric['public_read'])) {
					$row['reason'] = 'Разрешён docread для рубрики';
				} else {
					$row['reason'] = 'Нет docread для рубрики';
				}

				$result[] = $row;
			}

			return $result;
		}

		protected static function permissionReason($code, $allowed, $all)
		{
			if ($code === '') {
				return 'Отдельное право не требуется';
			}

			if ($all) {
				return 'Роль администратора имеет полный доступ';
			}

			return $allowed
				? 'Право ' . $code . ' назначено роли'
				: 'Право ' . $code . ' не назначено роли';
		}

		protected static function defaultPublicGroup($role)
		{
			$settings = PublicAuthSettings::all();
			$registered = isset($settings['default_group']) ? max(1, (int) $settings['default_group']) : 4;
			$map = array('admin' => 1, 'guest' => 2, 'moderator' => 3, 'user' => $registered);
			return isset($map[$role]) ? $map[$role] : $registered;
		}

		protected static function parseRubricPermissions($serialized)
		{
			$result = array();
			foreach (explode('|', (string) $serialized) as $permission) {
				$permission = trim($permission);
				if ($permission !== '' && preg_match('/^[a-z0-9_-]+$/i', $permission)) {
					$result[$permission] = $permission;
				}
			}

			return array_values($result);
		}
	}
