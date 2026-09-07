<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Documents/DocumentVisibility.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Documents;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;

	/** Shared visibility rule for direct public pages, sitemap, search and audits. */
	class DocumentVisibility
	{
		public static function isTechnical($document)
		{
			if (is_object($document)) { $document = get_object_vars($document); }
			if (!is_array($document)) { return false; }

			return !empty($document['document_is_technical']) || !empty($document['rubric_is_technical']);
		}

		public static function publicSql($documentAlias = 'd', $rubricAlias = '')
		{
			$documentAlias = self::alias($documentAlias, 'd');
			$rubricAlias = $rubricAlias !== '' ? self::alias($rubricAlias, 'r') : '';
			$sql = 'COALESCE(' . $documentAlias . '.document_is_technical,0)=0';
			if ($rubricAlias !== '') {
				return $sql . ' AND COALESCE(' . $rubricAlias . '.rubric_is_technical,0)=0';
			}

			return $sql . ' AND NOT EXISTS (SELECT 1 FROM ' . ContentTables::table('rubrics') . ' visibility_rubric'
				. ' WHERE visibility_rubric.Id=' . $documentAlias . '.rubric_id'
				. ' AND COALESCE(visibility_rubric.rubric_is_technical,0)=1)';
		}

		protected static function alias($value, $fallback)
		{
			return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string) $value) ? (string) $value : (string) $fallback;
		}
	}
