<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/TokenRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\PublicUserTables;

	class TokenRepository
	{
		public function issue($userId, $purpose, $channel, $ttl)
		{
			$raw = bin2hex(random_bytes(32));
			DB::query('UPDATE `' . PublicUserTables::table('auth_tokens') . '` SET used_at=%i WHERE user_id=%i AND purpose=%s AND used_at=0', time(), (int) $userId, (string) $purpose);
			DB::Insert(PublicUserTables::table('auth_tokens'), array(
				'user_id' => (int) $userId,
				'purpose' => (string) $purpose,
				'channel' => (string) $channel,
				'token_hash' => hash('sha256', $raw),
				'expires_at' => time() + max(300, (int) $ttl),
				'used_at' => 0,
				'created_at' => time(),
			));
			return $raw;
		}

		public function consume($raw, $purpose)
		{
			$hash = hash('sha256', (string) $raw);
			$row = DB::query('SELECT * FROM `' . PublicUserTables::table('auth_tokens') . '` WHERE token_hash=%s AND purpose=%s AND used_at=0 AND expires_at>=%i LIMIT 1', $hash, (string) $purpose, time())->getAssoc();
			if (!$row) {
				return null;
			}

			DB::query('UPDATE `' . PublicUserTables::table('auth_tokens') . '` SET used_at=%i WHERE id=%i AND used_at=0', time(), (int) $row['id']);
			return (array) $row;
		}
	}
