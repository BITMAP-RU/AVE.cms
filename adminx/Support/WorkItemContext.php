<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/WorkItemContext.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\SystemTables;
	use App\Common\Permission;
	use App\Content\ContentTables;
	use App\Content\Documents\DocumentPickerRepository;
	use DB;

	/** Shared assignee and document context for panel work items. */
	class WorkItemContext
	{
		public static function users()
		{
			$rows = DB::query(
				'SELECT id,name,email FROM ' . SystemTables::table('users')
					. ' WHERE is_active=1 ORDER BY name,email,id'
			)->getAll();
			$result = array();
			foreach ($rows ?: array() as $row) {
				$result[] = array(
					'id' => (int) $row['id'],
					'name' => trim((string) $row['name']) !== '' ? (string) $row['name'] : (string) $row['email'],
					'email' => (string) $row['email'],
				);
			}

			return $result;
		}

		public static function assignee($userId)
		{
			$userId = max(0, (int) $userId);
			if ($userId < 1) { return 0; }
			return (int) DB::query(
				'SELECT id FROM ' . SystemTables::table('users') . ' WHERE id=%i AND is_active=1 LIMIT 1',
				$userId
			)->getValue();
		}

		public static function documents($query, $limit = 20)
		{
			if (!Permission::check('view_documents')) { return array(); }
			return (new DocumentPickerRepository())->search($query, array(), $limit);
		}

		public static function target($type, $id)
		{
			if (!Permission::check('view_documents')) { return array('', 0); }
			$type = strtolower(trim((string) $type));
			$id = max(0, (int) $id);
			if ($type !== 'document' || $id < 1) { return array('', 0); }
			$exists = (int) DB::query(
				'SELECT Id FROM ' . ContentTables::table('documents') . ' WHERE Id=%i AND document_deleted!=%s LIMIT 1',
				$id,
				'1'
			)->getValue();
			return $exists > 0 ? array('document', $exists) : array('', 0);
		}

		public static function decorateTargets(array &$items)
		{
			if (!Permission::check('view_documents')) { return; }
			$ids = array();
			foreach ($items as $item) {
				if (isset($item['target_type'], $item['target_id']) && $item['target_type'] === 'document' && (int) $item['target_id'] > 0) {
					$ids[(int) $item['target_id']] = (int) $item['target_id'];
				}
			}

			$documents = array();
			if ($ids) {
				$rows = DB::query(
					'SELECT Id,document_title,document_alias FROM ' . ContentTables::table('documents')
						. ' WHERE Id IN (' . implode(',', $ids) . ') AND document_deleted!=%s',
					'1'
				)->getAll();
				foreach ($rows ?: array() as $row) { $documents[(int) $row['Id']] = $row; }
			}

			foreach ($items as &$item) {
				$item['target_title'] = '';
				$item['target_alias'] = '';
				$item['target_url'] = '';
				$id = isset($item['target_id']) ? (int) $item['target_id'] : 0;
				if (!isset($documents[$id])) { continue; }
				$item['target_title'] = htmlspecialchars_decode((string) $documents[$id]['document_title'], ENT_QUOTES);
				$item['target_alias'] = (string) $documents[$id]['document_alias'];
				$item['target_url'] = rtrim(ADMINX_BASE, '/') . '/documents/' . $id . '/edit';
			}

			unset($item);
		}

		public static function dueAt($value)
		{
			$value = trim((string) $value);
			if ($value === '') { return 0; }
			$timestamp = strtotime($value);
			return $timestamp === false ? 0 : max(0, (int) $timestamp);
		}
	}
