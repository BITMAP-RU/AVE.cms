<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/Feeds/FeedPresets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\Feeds;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\CatalogTables;
	use DB;

	class FeedPresets
	{
		protected static $seeded = false;

		public static function seed()
		{
			if (self::$seeded) { return; }
			self::$seeded = true;
			foreach (self::definitions() as $definition) { self::create($definition[0], $definition[1], $definition[2], isset($definition[3]) ? $definition[3] : 'yml_catalog'); }
		}

		public static function ensureCategory($categoryId)
		{
			$categoryId = (int) $categoryId;
			if ($categoryId < 1 || !self::categoryExists($categoryId)) { return null; }
			$alias = 'legacy-category-' . $categoryId;
			self::create($alias, 'Legacy: категория ' . $categoryId, array($categoryId));
			return $alias;
		}

		public static function aliasForPath($path)
		{
			$path = ltrim((string) $path, '/'); $paths = self::paths();
			return isset($paths[$path]) ? $paths[$path] : null;
		}

		public static function paths()
		{
			return array(
				'export.xml'=>'legacy-catalog-all','offers.xml'=>'legacy-offers-all','yandex_2025.xml'=>'legacy-yandex-2025',
				'sbermega_2025.xml'=>'legacy-sbermega-2025','sbermegaall_2025.xml'=>'legacy-sbermegaall-2025',
				'yml/yml_homebeds.xml'=>'legacy-offers-all','yml/yml_wcbeds_2025.xml'=>'legacy-wcbeds-65',
				'yml/yml_homebeds_2025.xml'=>'legacy-homebeds-66','yml/yml_kidsbeds_2025.xml'=>'legacy-kidsbeds-67',
				'yml/yml_medbeds_2025.xml'=>'legacy-medbeds-64','yml/yml_reanimation_2025.xml'=>'legacy-reanimation-69',
				'yml/yml_birth_2025.xml'=>'legacy-birth-68','yml/yml_beds.xml'=>'legacy-beds-97',
				'yml/massazhnye-stoly-skladnye.yml'=>'legacy-category-12','yml/massazhnye-stoly-stacionarnye.yml'=>'legacy-category-93',
				'yml/massazhnye-stoly-s-elektroprivodom.yml'=>'legacy-category-92','yml/massazhnoe-oborudovanie.yml'=>'legacy-massage',
				'yml/kosmetologicheskie-kresla.yml'=>'legacy-cosmetology','yml/kosmetologicheskie.yml'=>'legacy-category-87',
				'yml/pedikyurnye.yml'=>'legacy-category-88','yml/krovati-obcshebolnichnye.yml'=>'legacy-medbeds-64',
				'yml/krovati-s-tualetnym-ustrojstvom.yml'=>'legacy-wcbeds-65','yml/krovati-dlya-domashnego-ispolzovaniya.yml'=>'legacy-homebeds-66',
				'yml/detskie-medicinskie-krovati.yml'=>'legacy-kidsbeds-67','yml/reanimacionnye-krovati.yml'=>'legacy-reanimation-69',
				'yml/medicinskie-krovati-mehanicheskie.yml'=>'legacy-category-95','yml/medicinskie-krovati-s-elektroprivodom.yml'=>'legacy-category-96',
				'yml/medicinskie-krovati.yml'=>'legacy-medical-beds','yml/krovati-medicinskie.yml'=>'legacy-medical-beds',
				'yml/stoliki-reanimacionnye-anesteziologicheskie.yml'=>'legacy-category-47'
			);
		}

		protected static function definitions()
		{
			return array(
				array('legacy-catalog-all','Legacy: весь каталог',array()),
				array('legacy-offers-all','Legacy: цены и предложения',array(),'yml_offers'),
				array('legacy-yandex-2025','Legacy: Яндекс 2025',array()),
				array('legacy-sbermega-2025','Legacy: СберМега 2025',array()),
				array('legacy-sbermegaall-2025','Legacy: СберМега, весь каталог 2025',array()),
				array('legacy-beds-97','Legacy: медицинские кровати',array(97)),
				array('legacy-medbeds-64','Legacy: медицинские кровати, категория 64',array(64)),
				array('legacy-wcbeds-65','Legacy: кровати с туалетом',array(65)),
				array('legacy-homebeds-66','Legacy: домашние кровати',array(66)),
				array('legacy-kidsbeds-67','Legacy: детские кровати',array(67)),
				array('legacy-birth-68','Legacy: родовые кровати',array(68)),
				array('legacy-reanimation-69','Legacy: реанимационные кровати',array(69)),
				array('legacy-medical-beds','Legacy: все медицинские кровати',array(1,67,68,69,95,96)),
				array('legacy-cosmetology','Legacy: косметология',array(87,88)),
				array('legacy-massage','Legacy: массаж',array(99,12,92)),
				array('legacy-category-47','Legacy: категория 47',array(47)),
				array('legacy-category-12','Legacy: категория 12',array(12)),
				array('legacy-category-65','Legacy: категория 65',array(65)),
				array('legacy-category-87','Legacy: категория 87',array(87)),
				array('legacy-category-88','Legacy: категория 88',array(88)),
				array('legacy-category-92','Legacy: категория 92',array(92)),
				array('legacy-category-93','Legacy: категория 93',array(93)),
				array('legacy-category-95','Legacy: категория 95',array(95)),
				array('legacy-category-96','Legacy: категория 96',array(96))
			);
		}

		protected static function create($alias, $name, array $categories, $profile = 'yml_catalog')
		{
			if (DB::query('SELECT id FROM ' . Schema::table('definitions') . ' WHERE alias=%s LIMIT 1', $alias)->getValue()) { return; }
			$host = defined('HOST') && HOST ? rtrim(HOST, '/') : 'https://med-mos.ru';
			$mapping = array('name'=>'index:title','url'=>'document:url','price'=>'index:price','oldprice'=>'index:old_price','vendorCode'=>'index:article','vendor'=>'static:Мед-Мос','description'=>'field:17','pictures'=>'index:images');
			$catalogFieldId=(int)DB::query("SELECT field_id FROM ".CatalogTables::table('module_catalog_settings')." WHERE rubric_id=6 AND purpose='commerce' ORDER BY id LIMIT 1")->getValue();
			$now = time();
			DB::Insert(Schema::table('definitions'), array(
				'name'=>$name,'alias'=>$alias,'status'=>1,'profile'=>$profile,'rubric_id'=>6,'catalog_field_id'=>$catalogFieldId,'site_name'=>'Мед-Мос',
				'company_name'=>'Мед-Мос','base_url'=>$host,'currency'=>'RUB','category_mode'=>'tree','include_descendants'=>0,
				'min_price'=>0,'cache_ttl'=>3600,'access_token'=>'','mappings_json'=>json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'params_json'=>'[]','conditions_json'=>'[]','created_at'=>$now,'updated_at'=>$now
			));
			$feedId = (int) DB::insertId();
			foreach (array_unique(array_map('intval', $categories)) as $categoryId) {
				if ($categoryId > 0 && self::categoryExists($categoryId)) { DB::Insert(Schema::table('categories'), array('feed_id'=>$feedId,'catalog_item_id'=>$categoryId,'mode'=>'include')); }
			}
		}

		protected static function categoryExists($categoryId)
		{
			return (bool) DB::query('SELECT id FROM ' . CatalogTables::table('module_catalog_items') . ' WHERE id=%i LIMIT 1', (int) $categoryId)->getValue();
		}
	}
