<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Registrations/Model.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Registrations;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/**
	 * Реестр регистрационных удостоверений Росздравнадзора (РУ) на медизделия.
	 *
	 * Юридически обязателен для продажи медтехники. Мягкая связь с товаром
	 * (document_id nullable) — жёсткий FK не нужен, товар может быть удалён/заменён.
	 */
	class Model
	{
		/** Статусы РУ: code => [label, color]. */
		public static function statuses()
		{
			return array(
				'active'    => array('label' => 'Действует', 'color' => 'green'),
				'suspended' => array('label' => 'Приостановлено', 'color' => 'amber'),
				'expired'   => array('label' => 'Истекло', 'color' => 'gray'),
				'cancelled' => array('label' => 'Аннулировано', 'color' => 'red'),
			);
		}

		/** Классы риска медизделий по номенклатуре Росздравнадзора. */
		public static function riskClasses()
		{
			return array('1', '2a', '2b', '3');
		}

		public static function normalizeStatus($status, $fallback = 'active')
		{
			$status = trim((string) $status);
			return array_key_exists($status, self::statuses()) ? $status : $fallback;
		}

		public static function normalizeRisk($risk)
		{
			$risk = trim((string) $risk);
			return in_array($risk, self::riskClasses(), true) ? $risk : '';
		}

		public static function table()
		{
			return Schema::table('registration_certificates');
		}

		/** Список с фильтрами: status, risk_class, q (номер/владелец/изделие). */
		public static function all(array $filters = array())
		{
			$sql = 'SELECT * FROM ' . self::table() . ' WHERE 1=1';
			$args = array();

			$status = isset($filters['status']) ? self::normalizeStatusFilter($filters['status']) : '';
			if ($status !== '') {
				$sql .= ' AND status = %s';
				$args[] = $status;
			}

			$risk = isset($filters['risk_class']) ? self::normalizeRisk($filters['risk_class']) : '';
			if ($risk !== '') {
				$sql .= ' AND risk_class = %s';
				$args[] = $risk;
			}

			$q = isset($filters['q']) ? trim((string) $filters['q']) : '';
			if ($q !== '') {
				$sql .= ' AND (number LIKE %ss OR holder LIKE %ss OR product_name LIKE %ss)';
				$args[] = $q;
				$args[] = $q;
				$args[] = $q;
			}

			$sql .= ' ORDER BY updated_at DESC, id DESC';
			$rows = call_user_func_array(array('DB', 'query'), array_merge(array($sql), $args))->getAll();

			return array_map(array(self::class, 'normalize'), $rows ?: array());
		}

		public static function summary()
		{
			$row = DB::query(
				'SELECT COUNT(*) AS total,'
					. ' SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS active,'
					. ' SUM(CASE WHEN status <> %s AND status <> %s THEN 1 ELSE 0 END) AS inactive'
					. ' FROM ' . self::table(),
				'active',
				'active',
				'expired'
			)->getAssoc();

			// Действующие РУ со сроком, истекающим в ближайшие 60 дней.
			$expiring = (int) DB::query(
				'SELECT COUNT(*) FROM ' . self::table()
					. ' WHERE status = %s AND perpetual = 0 AND valid_until > 0 AND valid_until < %i',
				'active',
				strtotime('+60 days')
			)->getValue();

			return array(
				'total'    => isset($row['total']) ? (int) $row['total'] : 0,
				'active'   => isset($row['active']) ? (int) $row['active'] : 0,
				'inactive' => isset($row['inactive']) ? (int) $row['inactive'] : 0,
				'expiring' => $expiring,
			);
		}

		public static function one($id)
		{
			$row = DB::query('SELECT * FROM ' . self::table() . ' WHERE id = %i LIMIT 1', (int) $id)->getAssoc();
			return $row ? self::normalize($row) : null;
		}

		public static function create(array $data)
		{
			$fields = self::sanitize($data);
			$now = time();
			$fields['created_at'] = $now;
			$fields['updated_at'] = $now;
			DB::Insert(self::table(), $fields);
			return self::one((int) DB::insertId());
		}

		public static function update($id, array $data)
		{
			if (!self::one($id)) {
				return null;
			}

			$fields = self::sanitize($data);
			$fields['updated_at'] = time();
			DB::Update(self::table(), $fields, 'id = %i', (int) $id);
			return self::one($id);
		}

		public static function delete($id)
		{
			if (!self::one($id)) {
				return false;
			}

			DB::Delete(self::table(), 'id = %i', (int) $id);
			return true;
		}

		protected static function sanitize(array $data)
		{
			$number = trim((string) (isset($data['number']) ? $data['number'] : ''));
			if ($number === '') {
				throw new \RuntimeException('Укажите номер РУ');
			}

			$product = trim((string) (isset($data['product_name']) ? $data['product_name'] : ''));
			if ($product === '') {
				throw new \RuntimeException('Укажите наименование медизделия');
			}

			$perpetual = !empty($data['perpetual']) ? 1 : 0;
			$validUntil = $perpetual ? 0 : self::dateToTs(isset($data['valid_until']) ? $data['valid_until'] : '');

			return array(
				'number'       => mb_substr($number, 0, 120, 'UTF-8'),
				'holder'       => mb_substr(trim((string) (isset($data['holder']) ? $data['holder'] : '')), 0, 255, 'UTF-8'),
				'product_name' => mb_substr($product, 0, 255, 'UTF-8'),
				'risk_class'   => self::normalizeRisk(isset($data['risk_class']) ? $data['risk_class'] : ''),
				'status'       => self::normalizeStatus(isset($data['status']) ? $data['status'] : 'active'),
				'document_id'  => self::nullableId(isset($data['document_id']) ? $data['document_id'] : null),
				'scan_url'     => self::url(isset($data['scan_url']) ? $data['scan_url'] : ''),
				'issued_at'    => self::dateToTs(isset($data['issued_at']) ? $data['issued_at'] : ''),
				'valid_until'  => $validUntil,
				'perpetual'    => $perpetual,
				'note'         => mb_substr(trim((string) (isset($data['note']) ? $data['note'] : '')), 0, 2000, 'UTF-8'),
			);
		}

		public static function normalize($row)
		{
			$row = (array) $row;
			$statuses = self::statuses();
			$status = self::normalizeStatus(isset($row['status']) ? $row['status'] : '');

			$row['id'] = (int) $row['id'];
			$row['number'] = (string) $row['number'];
			$row['holder'] = (string) $row['holder'];
			$row['product_name'] = (string) $row['product_name'];
			$row['risk_class'] = (string) $row['risk_class'];
			$row['status'] = $status;
			$row['status_label'] = $statuses[$status]['label'];
			$row['status_color'] = $statuses[$status]['color'];
			$row['document_id'] = isset($row['document_id']) && $row['document_id'] !== null ? (int) $row['document_id'] : null;
			$row['scan_url'] = (string) $row['scan_url'];
			$row['issued_at'] = (int) $row['issued_at'];
			$row['valid_until'] = (int) $row['valid_until'];
			$row['perpetual'] = (int) $row['perpetual'] === 1;
			$row['issued_label'] = self::tsLabel($row['issued_at']);
			$row['issued_input'] = self::tsInput($row['issued_at']);
			$row['valid_input'] = self::tsInput($row['valid_until']);
			$row['valid_label'] = $row['perpetual'] ? 'бессрочно' : self::tsLabel($row['valid_until']);
			$row['is_expiring'] = !$row['perpetual'] && $row['valid_until'] > 0
				&& $row['valid_until'] < strtotime('+60 days') && $status === 'active';
			$row['note'] = (string) $row['note'];
			return $row;
		}

		protected static function normalizeStatusFilter($status)
		{
			$status = trim((string) $status);
			return array_key_exists($status, self::statuses()) ? $status : '';
		}

		protected static function nullableId($value)
		{
			$value = (int) $value;
			return $value > 0 ? $value : null;
		}

		protected static function url($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}

			// Разрешаем только http(s) и локальные пути; прочее отбрасываем.
			if (preg_match('#^https?://#i', $value) || strpos($value, '/') === 0) {
				return mb_substr($value, 0, 500, 'UTF-8');
			}

			return '';
		}

		protected static function dateToTs($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return 0;
			}

			$ts = strtotime($value);
			return $ts === false ? 0 : $ts;
		}

		protected static function tsLabel($ts)
		{
			$ts = (int) $ts;
			return $ts > 0 ? date('d.m.Y', $ts) : '';
		}

		protected static function tsInput($ts)
		{
			$ts = (int) $ts;
			return $ts > 0 ? date('Y-m-d', $ts) : '';
		}
	}
