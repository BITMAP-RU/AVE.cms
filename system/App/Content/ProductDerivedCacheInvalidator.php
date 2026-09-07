<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/ProductDerivedCacheInvalidator.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\Cache;
	use App\Common\CacheKey;
	use App\Common\FileCacheInvalidator;
	use App\Frontend\Feeds\Service as FeedService;
	use DB;

	/** Cache effects of specialized product writers; never mutates source rows or indexes. */
	class ProductDerivedCacheInvalidator
	{
		public static function invalidate(array $productIds = array(), array $sectionIds = array(), array $groupIds = array())
		{
			$productIds = self::ids($productIds);
			$sectionIds = self::ids($sectionIds);
			$groupIds = self::ids($groupIds);
			DB::afterCommit(function () use ($productIds, $sectionIds, $groupIds) {
				foreach ($productIds as $id) { FileCacheInvalidator::document($id); }
				if (!$productIds) { FileCacheInvalidator::publicPresentation(); }
				Cache::forgetTag(CacheKey::tag('catalog-admin-stats'));
				foreach ($sectionIds as $id) { Cache::forgetTag(CacheKey::tag('catalog-category', $id)); }
				foreach ($groupIds as $id) { Cache::forgetTag(CacheKey::tag('catalog-group', $id)); }
				foreach (array('App\\Modules\\Products\\ProductCardRepository', 'App\\Modules\\Products\\VariantRepository') as $repository) {
					if (class_exists($repository)) { $repository::reset(); }
				}

				(new FeedService())->clearAll();
			});
		}

		protected static function ids(array $ids)
		{
			return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; })));
		}
	}
