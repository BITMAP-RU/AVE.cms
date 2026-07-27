<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Blocks/migrations/012_merge_visual_blocks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\DatabaseSchema;
	use App\Common\SystemTables;
	use App\Content\ContentTables;

	$source = ContentTables::table('blocks');
	$target = ContentTables::table('sysblocks');
	if (!DatabaseSchema::tableExists($source) || !DatabaseSchema::tableExists($target)) {
		return;
	}

	$uniqueAlias = function ($preferred, $legacyId) use ($target) {
		$preferred = trim((string) $preferred);
		$base = $preferred !== '' ? $preferred : 'block-' . (int) $legacyId;
		$base = trim(substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), 0, 20), '-_');
		if ($base === '' || is_numeric($base)) {
			$base = 'block-' . (int) $legacyId;
		}

		$alias = $base;
		$index = 2;
		while (DB::query('SELECT id FROM `' . $target . '` WHERE sysblock_alias = %s LIMIT 1', $alias)->getValue()) {
			$suffix = '-' . $index++;
			$alias = substr($base, 0, 20 - strlen($suffix)) . $suffix;
		}

		return $alias;
	};

	$rows = DB::query('SELECT * FROM `' . $source . '` ORDER BY id ASC')->getAll();
	$migrated = array();
	foreach ($rows as $row) {
		$alias = $uniqueAlias(isset($row['block_alias']) ? $row['block_alias'] : '', (int) $row['id']);
		DB::Insert($target, array(
			'sysblock_group_id' => 0,
			'sysblock_name' => isset($row['block_name']) ? (string) $row['block_name'] : ('Блок #' . (int) $row['id']),
			'sysblock_description' => isset($row['block_description']) ? (string) $row['block_description'] : '',
			'sysblock_alias' => $alias,
			'sysblock_text' => isset($row['block_text']) ? (string) $row['block_text'] : '',
			'sysblock_active' => !empty($row['block_active']) ? '1' : '0',
			'sysblock_eval' => '0',
			'sysblock_external' => '0',
			'sysblock_ajax' => '0',
			'sysblock_visual' => '1',
			'sysblock_editor' => 'rich',
			'sysblock_author_id' => !empty($row['block_author_id']) ? (int) $row['block_author_id'] : 1,
			'sysblock_created' => !empty($row['block_created']) ? (int) $row['block_created'] : time(),
		));
		$migrated[(int) $row['id']] = array('id' => (int) DB::insertId(), 'alias' => $alias);
	}

	$oldRevisions = ContentTables::table('visual_block_revisions');
	$newRevisions = ContentTables::table('sysblock_revisions');
	if (DatabaseSchema::tableExists($oldRevisions) && DatabaseSchema::tableExists($newRevisions)) {
		$revisions = DB::query('SELECT * FROM `' . $oldRevisions . '` ORDER BY id ASC')->getAll();
		foreach ($revisions as $revision) {
			$oldId = isset($revision['block_id']) ? (int) $revision['block_id'] : 0;
			if (!isset($migrated[$oldId])) {
				continue;
			}

			$old = json_decode(isset($revision['snapshot_json']) ? (string) $revision['snapshot_json'] : '{}', true);
			$old = is_array($old) ? $old : array();
			$snapshot = array(
				'id' => $migrated[$oldId]['id'],
				'sysblock_group_id' => 0,
				'sysblock_name' => isset($old['block_name']) ? (string) $old['block_name'] : '',
				'sysblock_description' => isset($old['block_description']) ? (string) $old['block_description'] : '',
				'sysblock_alias' => $migrated[$oldId]['alias'],
				'sysblock_text' => isset($old['block_text']) ? (string) $old['block_text'] : '',
				'sysblock_active' => !empty($old['block_active']) ? '1' : '0',
				'sysblock_eval' => '0',
				'sysblock_external' => '0',
				'sysblock_ajax' => '0',
				'sysblock_visual' => '1',
				'sysblock_editor' => 'rich',
				'sysblock_author_id' => !empty($old['block_author_id']) ? (int) $old['block_author_id'] : 1,
				'sysblock_created' => !empty($old['block_created']) ? (int) $old['block_created'] : 0,
			);
			$json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			DB::Insert($newRevisions, array(
				'block_id' => $migrated[$oldId]['id'],
				'action' => isset($revision['action']) ? (string) $revision['action'] : 'import',
				'snapshot_hash' => sha1($json),
				'text_hash' => sha1($snapshot['sysblock_text']),
				'snapshot_json' => $json,
				'comment' => isset($revision['comment']) ? (string) $revision['comment'] : 'Перенос обычного блока',
				'author_id' => isset($revision['author_id']) ? (int) $revision['author_id'] : 0,
				'author_name' => isset($revision['author_name']) ? (string) $revision['author_name'] : '',
				'source_revision_id' => 0,
				'created_at' => isset($revision['created_at']) ? (int) $revision['created_at'] : time(),
			));
		}
	}

	$permissions = SystemTables::table('permissions');
	$rolePermissions = SystemTables::table('role_permissions');
	if (DatabaseSchema::tableExists($rolePermissions)) {
		DB::query(
			'DELETE FROM `' . $rolePermissions . '` WHERE permission_code IN (%s, %s)',
			'view_visual_blocks',
			'manage_visual_blocks'
		);
	}

	if (DatabaseSchema::tableExists($permissions)) {
		DB::query(
			'DELETE FROM `' . $permissions . '` WHERE code IN (%s, %s)',
			'view_visual_blocks',
			'manage_visual_blocks'
		);
	}

	$modules = SystemTables::table('modules');
	if (DatabaseSchema::tableExists($modules)) {
		DB::Delete($modules, 'code = %s', 'visual_blocks');
	}

	if (DatabaseSchema::tableExists($oldRevisions)) {
		DB::query('DROP TABLE `' . $oldRevisions . '`');
	}

	DB::query('DROP TABLE `' . $source . '`');
