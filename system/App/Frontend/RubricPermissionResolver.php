<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/RubricPermissionResolver.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;
	use DB;

	/** Resolves rubric permissions without relying on session side effects. */
	class RubricPermissionResolver
	{
		public function resolve($rubricId, $userGroupId, $embeddedPermissions = null)
		{
			$serialized = trim((string) $embeddedPermissions);

			if ($serialized === '') {
				$serialized = (string) DB::query(
					'SELECT rubric_permission FROM %b WHERE rubric_id = %i AND user_group_id = %i LIMIT 1',
					ContentTables::table('rubric_permissions'),
					(int) $rubricId,
					(int) $userGroupId
				)->getValue();
			}

			$permissions = array();
			foreach (explode('|', $serialized) as $permission) {
				$permission = trim($permission);
				if ($permission !== '' && preg_match('/^[a-z0-9_-]+$/i', $permission)) {
					$permissions[$permission] = true;
				}
			}

			return array_keys($permissions);
		}

		public function allows(array $permissions, $permission)
		{
			return in_array('alles', $permissions, true)
				|| in_array((string) $permission, $permissions, true);
		}
	}
