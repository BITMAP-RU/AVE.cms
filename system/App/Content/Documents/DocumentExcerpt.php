<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentExcerpt.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Resolves the editorial document excerpt without coupling it to field types. */
	class DocumentExcerpt
	{
		public static function explicit($document)
		{
			$value = self::value($document, 'document_excerpt');
			if ($value === '') {
				$value = self::value($document, 'document_teaser');
			}

			return trim($value);
		}

		public static function resolve($document)
		{
			$excerpt = self::explicit($document);
			return $excerpt !== '' ? $excerpt : trim(self::value($document, 'document_meta_description'));
		}

		protected static function value($document, $key)
		{
			if (is_array($document) && isset($document[$key])) {
				return (string) $document[$key];
			}

			if (is_object($document) && isset($document->{$key})) {
				return (string) $document->{$key};
			}

			return '';
		}
	}
