<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/DocumentTerms.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;

	/** Maintains the normalized keyword and tag indexes for a document. */
	class DocumentTerms
	{
		public static function sync($documentId, $rubricId, $keywords, $tags)
		{
			self::syncKeywords($documentId, $keywords);
			self::syncTags($documentId, $rubricId, $tags);
		}

		public static function syncKeywords($documentId, $keywords)
		{
			$documentId = (int) $documentId;
			DB::Delete(ContentTables::table('document_keywords'), 'document_id = %i', $documentId);
			foreach (self::normalize($keywords) as $keyword) {
				DB::Insert(ContentTables::table('document_keywords'), array(
					'document_id' => $documentId,
					'keyword' => $keyword,
				));
			}
		}

		public static function syncTags($documentId, $rubricId, $tags)
		{
			$documentId = (int) $documentId;
			DB::Delete(ContentTables::table('document_tags'), 'document_id = %i', $documentId);
			foreach (self::normalize($tags) as $tag) {
				DB::Insert(ContentTables::table('document_tags'), array(
					'rubric_id' => (int) $rubricId,
					'document_id' => $documentId,
					'tag' => $tag,
				));
			}
		}

		public static function normalize($value)
		{
			$out = array();
			$values = is_array($value) ? $value : explode(',', (string) $value);
			foreach ($values as $term) {
				$term = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $term));
				if ($term === '') {
					continue;
				}

				$term = function_exists('mb_substr') ? mb_substr($term, 0, 254, 'UTF-8') : substr($term, 0, 254);
				$out[$term] = $term;
			}

			return array_values($out);
		}
	}
