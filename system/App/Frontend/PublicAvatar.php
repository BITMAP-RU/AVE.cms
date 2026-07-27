<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicAvatar.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\PublicUserTables;
	use App\Frontend\Media\ThumbnailUrl;
	use DB;

	/** Resolves public user avatars and their generated thumbnails. */
	class PublicAvatar
	{
		protected static $users = array();

		public static function url($userId = null, $size = 58, $prefix = '')
		{
			$userId = $userId === null
				? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0)
				: (int) $userId;
			$user = self::user($userId);
			if (!$user) {
				return '';
			}

			$relative = ABS_PATH . UPLOAD_DIR . '/avatars/' . (string) $prefix . md5((string) $user['user_name']);
			$source = '';
			foreach (array('jpg', 'png', 'gif', 'webp') as $extension) {
				if (is_file(BASEPATH . $relative . '.' . $extension)) {
					$source = $relative . '.' . $extension;
					break;
				}
			}

			if ($source === '') {
				$source = (string) DB::query(
					'SELECT default_avatar FROM %b WHERE user_group = %i LIMIT 1',
					PublicUserTables::table('user_groups'),
					(int) $user['user_group']
				)->getValue();
			}

			if ($source === '') {
				return '';
			}

			$thumbnail = ThumbnailUrl::make(array(
				'link' => $source,
				'size' => 'c' . max(1, (int) $size) . 'x' . max(1, (int) $size),
			));
			return $thumbnail !== false ? (string) $thumbnail : $source;
		}

		protected static function user($userId)
		{
			if ($userId <= 0) {
				return null;
			}

			if (!array_key_exists($userId, self::$users)) {
				$row = DB::query(
					'SELECT user_name, user_group FROM %b WHERE Id = %i LIMIT 1',
					PublicUserTables::table('users'),
					$userId
				)->getAssoc();
				self::$users[$userId] = $row ? (array) $row : null;
			}

			return self::$users[$userId];
		}
	}
