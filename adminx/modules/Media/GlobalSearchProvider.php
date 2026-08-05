<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Media/GlobalSearchProvider.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Media;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class GlobalSearchProvider
	{
		public static function search($query, $limit = 8)
		{
			$out = array();
			foreach (MediaSearchIndex::search($query, $limit) as $row) {
				$out[] = array(
					'type' => 'media', 'group' => 'Медиа', 'title' => (string) $row['name'],
					'subtitle' => (string) $row['path'], 'url' => '/media/file?path=' . rawurlencode((string) $row['path']),
					'icon' => !empty($row['is_image']) ? 'ti ti-photo' : 'ti ti-file', 'score' => (float) $row['score'],
				);
			}

			return $out;
		}
	}
