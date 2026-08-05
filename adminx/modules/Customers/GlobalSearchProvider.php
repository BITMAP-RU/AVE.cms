<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Customers/GlobalSearchProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Customers;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\PublicUserTables;
	use DB;

	class GlobalSearchProvider
	{
		public static function search($query, $limit = 8)
		{
			$query = trim((string) $query); if ($query === '') { return array(); }
			$sql = 'SELECT Id,email,firstname,lastname,user_name,phone,company,status FROM ' . PublicUserTables::table('users')
				. ' WHERE deleted!=%s AND (email LIKE %ss OR firstname LIKE %ss OR lastname LIKE %ss OR user_name LIKE %ss OR phone LIKE %ss OR company LIKE %ss';
			$args = array('1', $query, $query, $query, $query, $query, $query);
			if (ctype_digit($query)) { $sql .= ' OR Id=%i'; $args[] = (int) $query; }
			$sql .= ') ORDER BY status DESC,Id DESC LIMIT ' . max(1, min(12, (int) $limit));
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll() ?: array();
			$out = array();
			foreach ($rows as $row) {
				$title = trim($row['firstname'] . ' ' . $row['lastname']); if ($title === '') { $title = $row['user_name'] ?: ($row['email'] ?: $row['phone']); }
				$subtitle = '#' . (int) $row['Id']; if ($row['email']) { $subtitle .= ' · ' . $row['email']; } if ((string) $row['status'] !== '1') { $subtitle .= ' · отключён'; }
				$out[] = array('type' => 'customer', 'group' => 'Пользователи сайта', 'title' => $title, 'subtitle' => $subtitle, 'url' => '/system/customers?tab=customers&q=' . rawurlencode((string) ($row['email'] ?: $row['user_name'])), 'icon' => 'ti ti-user-heart', 'score' => 50);
			}

			return $out;
		}
	}
