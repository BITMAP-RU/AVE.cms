<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ProductShipping.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Owns product shipping profiles and physical cargo places. */
	class ProductShipping
	{
		public static function profilesTable() { return CatalogTables::table('commerce_product_shipping_profiles'); }
		public static function packagesTable() { return CatalogTables::table('commerce_product_packages'); }

		public static function profile($productId)
		{
			$productId = (int) $productId;
			$profile = DB::query('SELECT * FROM ' . self::profilesTable() . ' WHERE product_id=%i LIMIT 1', $productId)->getAssoc();
			$packages = DB::query('SELECT * FROM ' . self::packagesTable() . ' WHERE product_id=%i ORDER BY position,id', $productId)->getAll() ?: array();
			$summary = self::summary($packages);
			return array(
				'product_id' => $productId, 'shipping_enabled' => $profile ? (int) $profile['shipping_enabled'] : 0,
				'has_profile' => (bool) $profile,
				'packages' => $packages, 'summary' => $summary,
				'complete' => !empty($packages) && $summary['incomplete'] === 0,
				'legacy' => empty($packages) ? self::legacy($productId) : array(),
			);
		}

		public static function save($productId, $enabled, array $packages)
		{
			$productId = (int) $productId;
			if ($productId <= 0 || !DB::query('SELECT product_id FROM ' . CatalogTables::table('catalog_product_index') . ' WHERE product_id=%i LIMIT 1', $productId)->getValue()) {
				throw new \InvalidArgumentException('Товар не найден');
			}

			$normalized = array();
			foreach ($packages as $index => $package) {
				if (!is_array($package)) { continue; }
				$row = array(
					'title' => trim(isset($package['title']) ? (string) $package['title'] : ''),
					'quantity' => max(1, min(999, isset($package['quantity']) ? (int) $package['quantity'] : 1)),
					'weight_kg' => self::number(isset($package['weight_kg']) ? $package['weight_kg'] : 0, 100000),
					'length_cm' => self::number(isset($package['length_cm']) ? $package['length_cm'] : 0, 10000),
					'width_cm' => self::number(isset($package['width_cm']) ? $package['width_cm'] : 0, 10000),
					'height_cm' => self::number(isset($package['height_cm']) ? $package['height_cm'] : 0, 10000),
					'position' => (int) $index,
				);
				if ($row['title'] === '') { $row['title'] = 'Коробка ' . (count($normalized) + 1); }
				$normalized[] = $row;
			}

			if (count($normalized) > 50) { throw new \InvalidArgumentException('У товара может быть не более 50 грузовых мест'); }
			$now = time(); DB::startTransaction();
			try {
				DB::query('INSERT INTO ' . self::profilesTable() . ' (product_id,shipping_enabled,created_at,updated_at) VALUES (%i,%i,%i,%i)'
					. ' ON DUPLICATE KEY UPDATE shipping_enabled=VALUES(shipping_enabled),updated_at=VALUES(updated_at)', $productId, $enabled ? 1 : 0, $now, $now);
				DB::Delete(self::packagesTable(), 'product_id=%i', $productId);
				foreach ($normalized as $row) { DB::Insert(self::packagesTable(), array_merge(array('product_id'=>$productId,'created_at'=>$now,'updated_at'=>$now), $row)); }
				DB::commit();
			} catch (\Throwable $e) { DB::rollback(); throw $e; }
			return self::profile($productId);
		}

		public static function packagesForQuantity($productId, $productQuantity)
		{
			$profile = self::profile($productId); $out = array();
			$packages = $profile['packages'];
			if (!$profile['has_profile'] && !$packages) { $fallback = self::legacyPackage($profile['legacy']); if ($fallback) { $packages = array($fallback); } }
			if (($profile['has_profile'] && !$profile['shipping_enabled']) || !$packages || self::summary($packages)['incomplete'] > 0) { return $out; }
			foreach ($packages as $package) {
				$package['quantity'] = (int) $package['quantity'] * max(1, (int) $productQuantity);
				$out[] = $package;
			}

			return $out;
		}

		public static function copy($sourceProductId, array $targetProductIds)
		{
			$source = self::profile((int)$sourceProductId);
			if (!$source['has_profile']) { throw new \RuntimeException('Сначала сохраните профиль упаковки исходного товара'); }
			$packages = array(); foreach ($source['packages'] as $package) { $packages[] = array_intersect_key($package, array_flip(array('title','quantity','weight_kg','length_cm','width_cm','height_cm'))); }
			$done = 0;
			foreach (array_values(array_unique(array_map('intval',$targetProductIds))) as $productId) {
				if ($productId <= 0 || $productId === (int)$sourceProductId) { continue; }
				self::save($productId, (bool)$source['shipping_enabled'], $packages); $done++;
			}

			return $done;
		}

		public static function setEnabled(array $productIds, $enabled)
		{
			$done = 0; $now = time();
			foreach (array_values(array_unique(array_filter(array_map('intval',$productIds)))) as $productId) {
				if (!DB::query('SELECT product_id FROM ' . CatalogTables::table('catalog_product_index') . ' WHERE product_id=%i LIMIT 1',$productId)->getValue()) { continue; }
				DB::query('INSERT INTO ' . self::profilesTable() . ' (product_id,shipping_enabled,created_at,updated_at) VALUES (%i,%i,%i,%i)'
					. ' ON DUPLICATE KEY UPDATE shipping_enabled=VALUES(shipping_enabled),updated_at=VALUES(updated_at)', $productId,$enabled?1:0,$now,$now); $done++;
			}

			return $done;
		}

		public static function summary(array $packages)
		{
			$places = 0; $weight = 0.0; $volume = 0.0; $incomplete = 0;
			foreach ($packages as $package) {
				$quantity = max(1, (int) $package['quantity']); $places += $quantity;
				$weight += (float) $package['weight_kg'] * $quantity;
				$volume += ((float) $package['length_cm'] * (float) $package['width_cm'] * (float) $package['height_cm'] / 1000000) * $quantity;
				if ((float) $package['weight_kg'] <= 0 || (float) $package['length_cm'] <= 0 || (float) $package['width_cm'] <= 0 || (float) $package['height_cm'] <= 0) { $incomplete += $quantity; }
			}

			return array('places'=>$places,'weight_kg'=>round($weight,3),'volume_m3'=>round($volume,4),'incomplete'=>$incomplete);
		}

		protected static function legacy($productId)
		{
			$rows = DB::query('SELECT rf.rubric_field_alias alias,df.field_value,df.field_number_value FROM ' . ContentTables::table('document_fields') . ' df'
				. ' INNER JOIN ' . ContentTables::table('rubric_fields') . ' rf ON rf.Id=df.rubric_field_id'
				. " WHERE df.document_id=%i AND rf.rubric_field_alias IN ('weight','ves','size','razmer')", (int) $productId)->getAll() ?: array();
			$out = array(); foreach ($rows as $row) { $out[(string)$row['alias']] = (float)$row['field_number_value'] > 0 ? (string)$row['field_number_value'] : trim((string)$row['field_value']); }
			return $out;
		}

		protected static function legacyPackage(array $legacy)
		{
			$weight = isset($legacy['weight']) ? $legacy['weight'] : (isset($legacy['ves']) ? $legacy['ves'] : '');
			$size = isset($legacy['size']) ? $legacy['size'] : (isset($legacy['razmer']) ? $legacy['razmer'] : '');
			preg_match_all('/\d+(?:[.,]\d+)?/', (string) $size, $matches);
			if ((float) str_replace(',', '.', (string) $weight) <= 0 || count($matches[0]) !== 3) { return null; }
			$dimensions = array_map(function ($value) { return (float) str_replace(',', '.', $value); }, $matches[0]);
			if (min($dimensions) <= 0) { return null; }
			return array('id'=>0,'product_id'=>0,'title'=>'Старые габариты','quantity'=>1,'weight_kg'=>(float)str_replace(',', '.', (string)$weight),
				'length_cm'=>$dimensions[0],'width_cm'=>$dimensions[1],'height_cm'=>$dimensions[2],'position'=>0,'source'=>'legacy');
		}

		protected static function number($value, $max)
		{
			$value = str_replace(',', '.', trim((string) $value));
			if ($value === '' || !is_numeric($value)) { return 0; }
			return round(max(0, min((float) $max, (float) $value)), 3);
		}
	}
