<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/PaymentPrograms.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\BasketTables;
	use App\Common\FileCacheInvalidator;
	use App\Helpers\Hooks;

	/** Product eligibility and runtime availability for alternative payment programs. */
	class PaymentPrograms
	{
		public static function programsTable() { return CatalogTables::table('commerce_payment_programs'); }
		public static function productsTable() { return CatalogTables::table('commerce_product_payment_programs'); }

		public static function all()
		{
			return DB::query('SELECT * FROM ' . self::programsTable() . ' ORDER BY title,code')->getAll() ?: array();
		}

		public static function setProduct($productId, $code, $enabled)
		{
			$productId = (int) $productId;
			$code = self::code($code);
			if ($productId <= 0 || !self::exists($code)) {
				throw new \InvalidArgumentException('Товар или платёжная программа не найдены');
			}

			$now = time();
			DB::query(
				'INSERT INTO ' . self::productsTable() . ' (product_id,program_code,enabled,settings_json,created_at,updated_at)'
				. ' VALUES (%i,%s,%i,%s,%i,%i) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=VALUES(updated_at)',
				$productId, $code, $enabled ? 1 : 0, '{}', $now, $now
			);
			self::syncDocumentField($productId, $code, $enabled);
			FileCacheInvalidator::document($productId);
			return (bool) $enabled;
		}

		public static function productEnabled($productId, $code)
		{
			$value = DB::query(
				'SELECT enabled FROM ' . self::productsTable() . ' WHERE product_id=%i AND program_code=%s LIMIT 1',
				(int) $productId, self::code($code)
			)->getValue();
			if ($value !== null && $value !== false) { return (int) $value === 1; }
			return self::documentFieldEnabled((int) $productId, self::code($code));
		}

		public static function productInformationCode($productId, $code)
		{
			$code = self::code($code);
			$field = self::documentProgramField((int) $productId, $code . '_code');
			if (!$field) { return ''; }
			$row = DB::query('SELECT df.field_value,dft.field_value more FROM ' . ContentTables::table('document_fields') . ' df'
				. ' LEFT JOIN ' . ContentTables::table('document_fields_text') . ' dft ON dft.document_id=df.document_id AND dft.rubric_field_id=df.rubric_field_id'
				. ' WHERE df.document_id=%i AND df.rubric_field_id=%i LIMIT 1', (int) $productId, (int) $field['Id'])->getAssoc();
			return $row ? trim((string) $row['field_value'] . (string) $row['more']) : '';
		}

		/** Import a document checkbox into the canonical program table when present. */
		public static function syncFromDocumentField($productId, $code, $enabled)
		{
			$productId = (int) $productId;
			$code = self::code($code);
			if ($productId <= 0 || !self::exists($code)) { return false; }
			$now = time();
			DB::query(
				'INSERT INTO ' . self::productsTable() . ' (product_id,program_code,enabled,settings_json,created_at,updated_at)'
				. ' VALUES (%i,%s,%i,%s,%i,%i) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=VALUES(updated_at)',
				$productId, $code, $enabled ? 1 : 0, '{}', $now, $now
			);
			return (bool) $enabled;
		}

		public static function setActive($code, $active)
		{
			$code = self::code($code);
			if (!self::exists($code)) { throw new \InvalidArgumentException('Платёжная программа не найдена'); }
			DB::Update(self::programsTable(), array('status' => $active ? 1 : 0, 'updated_at' => time()), 'code=%s', $code);
			DB::Update(BasketTables::table('module_basket_payment'), array('payment_active' => $active ? 1 : 0), 'payment_program=%s', $code);
			return (bool) $active;
		}

		public static function active($code)
		{
			try { return (int) DB::query('SELECT status FROM ' . self::programsTable() . ' WHERE code=%s LIMIT 1', self::code($code))->getValue() === 1; }
			catch (\Throwable $e) { return false; }
		}

		public static function eligibleForProducts(array $productIds, $activeOnly = true)
		{
			$ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
			if (!$ids) { return array(); }
			$sql = 'SELECT p.code,p.title,p.status FROM ' . self::programsTable() . ' p WHERE '
				. ($activeOnly ? 'p.status=1 AND ' : '')
				. '(SELECT COUNT(DISTINCT x.product_id) FROM ' . self::productsTable() . ' x'
				. ' WHERE x.program_code=p.code AND x.enabled=1 AND x.product_id IN (' . implode(',', $ids) . '))=%i'
				. ' ORDER BY p.title,p.code';
			try { return DB::query($sql, count($ids))->getAll() ?: array(); }
			catch (\Throwable $e) { return array(); }
		}

		protected static function exists($code)
		{
			return (int) DB::query('SELECT id FROM ' . self::programsTable() . ' WHERE code=%s LIMIT 1', $code)->getValue() > 0;
		}

		protected static function syncDocumentField($productId, $code, $enabled)
		{
			$field = self::documentProgramField((int) $productId, $code);
			if (!$field) { return; }
			$data = array('field_value' => $enabled ? '1' : '0', 'field_number_value' => $enabled ? 1 : 0);
			$id = (int) DB::query('SELECT Id FROM ' . ContentTables::table('document_fields')
				. ' WHERE document_id=%i AND rubric_field_id=%i LIMIT 1', (int) $productId, (int) $field['Id'])->getValue();
			if ($id > 0) { DB::Update(ContentTables::table('document_fields'), $data, 'Id=%i', $id); }
			else {
				$data['document_id'] = (int) $productId; $data['rubric_field_id'] = (int) $field['Id']; $data['document_in_search'] = 0;
				DB::Insert(ContentTables::table('document_fields'), $data);
			}
		}

		protected static function documentFieldEnabled($productId, $code)
		{
			$field = self::documentProgramField((int) $productId, $code);
			if (!$field) { return false; }
			return (int) DB::query('SELECT 1 FROM ' . ContentTables::table('document_fields')
				. " WHERE document_id=%i AND rubric_field_id=%i AND (field_number_value>0 OR field_value='1') LIMIT 1",
				(int) $productId, (int) $field['Id'])->getValue() === 1;
		}

		protected static function documentProgramField($productId, $code)
		{
			$context = Hooks::filter('content.payment_program.field_alias', array(
				'code' => (string) $code,
				'alias' => (string) $code,
			));
			$fieldAlias = is_array($context) && isset($context['alias'])
				? trim((string) $context['alias'])
				: (string) $code;
			if ($fieldAlias === '') { return false; }
			return DB::query('SELECT rf.Id FROM ' . ContentTables::table('rubric_fields') . ' rf'
				. ' INNER JOIN ' . ContentTables::table('documents') . ' d ON d.rubric_id=rf.rubric_id'
				. ' WHERE d.Id=%i AND rf.rubric_field_alias=%s LIMIT 1', (int) $productId, (string) $fieldAlias)->getAssoc() ?: false;
		}

		protected static function code($code)
		{
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код платёжной программы');
			}

			return $code;
		}
	}
