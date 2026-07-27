<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Auth/Phone/ChallengeRepository.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Auth\Phone;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	class ChallengeRepository
	{
		protected $table;

		public function __construct($table)
		{
			$table = trim((string) $table);
			if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				throw new \InvalidArgumentException('Некорректная таблица SMS-проверок.');
			}

			$this->table = $table;
		}

		public function issue($provider, $phone, $code, $sessionHash, $ip, $consent, $returnUrl, array $options)
		{
			$this->expireOutstanding($provider, $phone, $sessionHash);
			$publicId = bin2hex(random_bytes(16));
			$now = time();
			DB::Insert($this->table, array(
				'public_id' => $publicId,
				'provider' => (string) $provider,
				'phone' => (string) $phone,
				'code_hash' => password_hash((string) $code, PASSWORD_BCRYPT, array('cost' => 10)),
				'session_hash' => (string) $sessionHash,
				'request_ip' => mb_substr((string) $ip, 0, 45),
				'attempts' => 0,
				'max_attempts' => (int) $options['max_attempts'],
				'consent' => !empty($consent) ? 1 : 0,
				'return_url' => mb_substr((string) $returnUrl, 0, 255),
				'expires_at' => $now + (int) $options['ttl'],
				'consumed_at' => 0,
				'created_at' => $now,
			));
			return $publicId;
		}

		public function findActive($publicId, $sessionHash)
		{
			$row = DB::query(
				'SELECT * FROM `' . $this->table . '`'
					. ' WHERE public_id=%s AND session_hash=%s AND consumed_at=0 AND expires_at>=%i LIMIT 1',
				(string) $publicId,
				(string) $sessionHash,
				time()
			)->getAssoc();
			return $row ? (array) $row : null;
		}

		public function registerAttempt(array $challenge)
		{
			DB::query(
				'UPDATE `' . $this->table . '` SET attempts=attempts+1'
					. ' WHERE id=%i AND consumed_at=0 AND expires_at>=%i AND attempts<max_attempts',
				(int) $challenge['id'],
				time()
			);
			return DB::affectedRows() === 1;
		}

		public function consume(array $challenge)
		{
			DB::query(
				'UPDATE `' . $this->table . '` SET consumed_at=%i'
					. ' WHERE id=%i AND consumed_at=0 AND expires_at>=%i',
				time(),
				(int) $challenge['id'],
				time()
			);
			return DB::affectedRows() === 1;
		}

		public function deleteByPublicId($publicId)
		{
			return DB::Delete($this->table, 'public_id=%s', (string) $publicId);
		}

		public function prune()
		{
			return DB::query(
				'DELETE FROM `' . $this->table . '` WHERE expires_at<%i OR (consumed_at>0 AND consumed_at<%i)',
				time() - 86400,
				time() - 86400
			);
		}

		protected function expireOutstanding($provider, $phone, $sessionHash)
		{
			DB::query(
				'UPDATE `' . $this->table . '` SET consumed_at=%i'
					. ' WHERE provider=%s AND phone=%s AND session_hash=%s AND consumed_at=0',
				time(),
				(string) $provider,
				(string) $phone,
				(string) $sessionHash
			);
		}
	}
