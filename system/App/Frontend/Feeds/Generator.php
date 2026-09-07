<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/Generator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldValueCodec;
	use App\Content\Fields\HtmlSanitizer;
	use DB;

	class Generator
	{
		protected $repository;
		public function __construct() { $this->repository = new Repository(); }

		public function generate(array $feed, $limit = 0, $record = true)
		{
			$started = microtime(true); $skipped = 0;
			try {
				$products = $this->repository->products($feed, $limit);
				$categories = $feed['profile'] === 'yml_offers' || $feed['profile'] === 'price' ? array() : $this->repository->outputCategories($feed);
				$writer = new \XMLWriter(); $writer->openMemory(); $writer->setIndent(true); $writer->startDocument('1.0', 'UTF-8');
				$writer->startElement('yml_catalog'); $writer->writeAttribute('date', date('Y-m-d H:i'));
				$writer->startElement('shop'); $writer->writeElement('name', $feed['site_name']); $writer->writeElement('company', $feed['company_name']); $writer->writeElement('url', rtrim($feed['base_url'], '/'));
				$writer->startElement('currencies'); $writer->startElement('currency'); $writer->writeAttribute('id', $feed['currency']); $writer->writeAttribute('rate', '1'); $writer->endElement(); $writer->endElement();
				if ($categories) { $writer->startElement('categories'); foreach ($categories as $category) { $writer->startElement('category'); $writer->writeAttribute('id', (string) $category['id']); if (!empty($category['parent_id'])) { $writer->writeAttribute('parentId', (string) $category['parent_id']); } $writer->text($category['name']); $writer->endElement(); } $writer->endElement(); }
				$writer->startElement('offers'); $written = 0;
				foreach ($products as $product) { if (!$this->writeOffer($writer, $feed, $product)) { $skipped++; continue; } $written++; }
				$writer->endElement(); $writer->endElement(); $writer->endElement(); $writer->endDocument(); $xml = $writer->outputMemory();
				if ($record) { $this->record($feed['id'], 'success', $written, $skipped, strlen($xml), $started, ''); }
				return array('xml' => $xml, 'offers' => $written, 'skipped' => $skipped, 'products' => $products);
			} catch (\Throwable $e) {
				if ($record) { $this->record($feed['id'], 'error', 0, $skipped, 0, $started, $e->getMessage()); }
				throw $e;
			}
		}

		protected function writeOffer(\XMLWriter $writer, array $feed, array $product)
		{
			$map = $feed['mappings']; $price = $this->value(isset($map['price']) ? $map['price'] : 'index:price', $product, $feed);
			if ((float) $price > 0 && (float) $price < (float) $feed['min_price']) { return false; }
			$writer->startElement('offer'); $writer->writeAttribute('id', (string) $product['product_id']); $writer->writeAttribute('available', (float) $product['stock'] > 0 ? 'true' : 'false');
			$this->element($writer, 'url', $this->value(isset($map['url']) ? $map['url'] : 'document:url', $product, $feed));
			$this->element($writer, 'price', $price); $old = $this->value(isset($map['oldprice']) ? $map['oldprice'] : 'index:old_price', $product, $feed); if ((float) $old > 0) { $this->element($writer, 'oldprice', $old); }
			$this->element($writer, 'currencyId', $feed['currency']);
			if ($feed['profile'] !== 'yml_offers' && $feed['profile'] !== 'price' && !empty($product['feed_category_id'])) { $this->element($writer, 'categoryId', $product['feed_category_id']); }
			if ($feed['profile'] !== 'price') {
				foreach ($this->pictures($this->value(isset($map['pictures']) ? $map['pictures'] : 'index:images', $product, $feed)) as $picture) { $this->element($writer, 'picture', $this->absolute($picture, $feed)); }
				$this->element($writer, 'name', $this->value(isset($map['name']) ? $map['name'] : 'index:title', $product, $feed));
				$vendor = $this->value(isset($map['vendor']) ? $map['vendor'] : 'static:' . $feed['site_name'], $product, $feed); if ($vendor !== '') { $this->element($writer, 'vendor', $vendor); }
				$this->element($writer, 'vendorCode', $this->value(isset($map['vendorCode']) ? $map['vendorCode'] : 'index:article', $product, $feed));
				$description = $this->value(isset($map['description']) ? $map['description'] : '', $product, $feed); if ($description !== '') { $writer->startElement('description'); $writer->writeCdata($this->description($description)); $writer->endElement(); }
				foreach ($feed['params'] as $param) {
					$attributeId = isset($param['attribute_id']) ? (int) $param['attribute_id'] : 0;
					$fieldId = isset($param['field_id']) ? (int) $param['field_id'] : 0;
					if ($attributeId > 0) {
						$value = isset($product['attributes'][$attributeId])
							? $this->clean($product['attributes'][$attributeId])
							: '';
					} else {
						$value = $fieldId && isset($product['fields'][$fieldId])
							? $this->clean($product['fields'][$fieldId])
							: '';
					}

					if ($value === '' && !empty($param['skip_empty'])) { continue; }
					$writer->startElement('param');
					$writer->writeAttribute('name', isset($param['name']) ? $param['name'] : 'Характеристика');
					if (!empty($param['unit'])) { $writer->writeAttribute('unit', $param['unit']); }
					$writer->text($value);
					$writer->endElement();
				}
			} else { $this->element($writer, 'name', $this->value(isset($map['name']) ? $map['name'] : 'index:title', $product, $feed)); $this->element($writer, 'vendorCode', $this->value(isset($map['vendorCode']) ? $map['vendorCode'] : 'index:article', $product, $feed)); }
			$writer->endElement(); return true;
		}

		protected function value($source, array $product, array $feed)
		{
			$source = (string) $source;
			if (strpos($source, 'static:') === 0) { return substr($source, 7); }
			if (strpos($source, 'field:') === 0) { $id = (int) substr($source, 6); return isset($product['fields'][$id]) ? $product['fields'][$id] : ''; }
			if ($source === 'document:url') { return rtrim($feed['base_url'], '/') . '/' . ltrim($product['alias'], '/'); }
			if ($source === 'index:images') { return $product['images_json']; }
			if (strpos($source, 'index:') === 0) { $key = substr($source, 6); return isset($product[$key]) ? $product[$key] : ''; }
			return '';
		}

		protected function pictures($value)
		{
			$decoded = FieldValueCodec::decodeStructured($value, null);
			if (!is_array($decoded)) { $decoded = $value !== '' ? array($value) : array(); }
			$result = array(); array_walk_recursive($decoded, function ($item) use (&$result) { $item = explode('|', (string) $item, 2)[0]; if ($item !== '') { $result[] = $item; } });
			return array_slice(array_values(array_unique($result)), 0, 10);
		}

		protected function absolute($path, array $feed) { return preg_match('#^https?://#i', (string) $path) ? $path : rtrim($feed['base_url'], '/') . '/' . ltrim($path, '/'); }
		protected function description($value) { return trim(HtmlSanitizer::clean((string) $value)); }
		protected function clean($value) { return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')); }
		protected function element(\XMLWriter $writer, $name, $value) { if ((string) $value === '') { return; } $writer->writeElement($name, (string) $value); }
		protected function record($feedId, $status, $offers, $skipped, $bytes, $started, $message) { DB::Insert(Schema::table('runs'), array('feed_id'=>(int)$feedId,'status'=>$status,'offers_count'=>(int)$offers,'skipped_count'=>(int)$skipped,'bytes'=>(int)$bytes,'duration_ms'=>(int)round((microtime(true)-$started)*1000),'message'=>(string)$message,'created_at'=>time())); }
	}
