<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/DocumentRelationFieldTrait.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\ContentTables;

	/** Общая логика связей с документами (doc_from_rub*, analoque). Legacy-схема. */
	trait DocumentRelationFieldTrait
	{
		/** Публичный вывод: список ссылок на связанные документы. */
		protected function renderDocumentLinks(array $ids)
		{
			$docs = $this->documentsByIds($ids);
			if (empty($docs)) {
				return '';
			}

			$html = '<ul>';
			foreach ($ids as $id) {
				$id = (int) $id;
				if (!isset($docs[$id])) {
					continue;
				}

				$doc = $docs[$id];
				$alias = ltrim((string) $doc->document_alias, '/');
				$html .= '<li><a href="/' . $this->attr($alias) . '">' . $this->e($doc->document_title) . '</a></li>';
			}

			return $html . '</ul>';
		}

		/** id => объект документа (Id, document_title, document_alias). */
		protected function documentsByIds(array $ids)
		{
			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
			if (empty($ids)) {
				return array();
			}

			$res = DB::query(
				'SELECT Id, document_title, document_alias FROM ' . ContentTables::table('documents')
				. ' WHERE Id IN (' . implode(',', $ids) . ") AND document_deleted = '0'"
			);
			$out = array();
			while ($doc = $res->getObject()) {
				$out[(int) $doc->Id] = $doc;
			}

			return $out;
		}
	}
