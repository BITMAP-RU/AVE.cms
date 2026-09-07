<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentRevisionPayload.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Backward-compatible envelope for complete document revisions. */
	class DocumentRevisionPayload
	{
		const FORMAT = 'ave.document-revision';
		const VERSION = 2;

		protected static $documentFields = array(
			'document_title', 'document_alias', 'document_alias_header', 'document_alias_history',
			'document_short_alias', 'document_breadcrumb_title', 'document_excerpt',
			'document_meta_keywords', 'document_meta_description', 'document_meta_robots',
			'document_sitemap_freq', 'document_sitemap_pr', 'document_in_sitemap', 'document_tags', 'document_property',
			'guid', 'document_status', 'document_in_search', 'document_is_technical', 'document_parent', 'rubric_tmpl_id',
			'document_linked_navi_id', 'document_position', 'document_published', 'document_expire',
			'document_author_id',
		);

		public static function create(array $document, array $fields)
		{
			$state = array();
			foreach (self::$documentFields as $key) {
				if (array_key_exists($key, $document)) { $state[$key] = $document[$key]; }
			}

			return array(
				'__format' => self::FORMAT,
				'__version' => self::VERSION,
				'document' => $state,
				'fields' => $fields,
			);
		}

		public static function decodeSerialized($serialized)
		{
			$decoded = @unserialize((string) $serialized, array('allowed_classes' => false));
			$decoded = is_array($decoded) ? $decoded : array();
			$full = isset($decoded['__format']) && $decoded['__format'] === self::FORMAT;
			return array(
				'full' => $full,
				'raw' => $decoded,
				'document' => $full && isset($decoded['document']) && is_array($decoded['document']) ? $decoded['document'] : array(),
				'fields' => $full && isset($decoded['fields']) && is_array($decoded['fields']) ? $decoded['fields'] : $decoded,
			);
		}

		public static function select(array $document, array $fields, array $documentKeys, array $fieldIds)
		{
			$documentLookup = array_fill_keys(array_values(array_unique(array_map('strval', $documentKeys))), true);
			$fieldLookup = array_fill_keys(array_values(array_unique(array_map('intval', $fieldIds))), true);

			return array(
				'document' => array_intersect_key($document, $documentLookup),
				'fields' => array_intersect_key($fields, $fieldLookup),
			);
		}
	}
