<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Directories/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Directories;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Directories\DirectoryRepository;

	/** Admin adapter over the content-domain directory repository. */
	class Model
	{
		public static function all($query = '')
		{
			$rows = DirectoryRepository::all($query);
			$usage = DirectoryRepository::usageMap();
			foreach ($rows as &$row) {
				$row['usage_count'] = isset($usage[$row['id']]) ? (int) $usage[$row['id']] : 0;
			}

			unset($row);
			return $rows;
		}

		public static function stats()
		{
			$rows = DirectoryRepository::all();
			$stats = array('total' => count($rows), 'active' => 0, 'values' => 0);
			foreach ($rows as $row) {
				if ($row['is_active']) {
					$stats['active']++;
				}

				$stats['values'] += (int) $row['items_count'];
			}

			return $stats;
		}

		public static function schemaReady()
		{
			return DirectoryRepository::schemaReady();
		}
	}
