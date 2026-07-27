<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/migrations/009_expand_clean_starter_rubric.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\ContentTables;

	return function (array $context) {
		$rubrics = ContentTables::table('rubrics');
		$fields = ContentTables::table('rubric_fields');
		$documents = ContentTables::table('documents');
		$values = ContentTables::table('document_fields');
		$textValues = ContentTables::table('document_fields_text');
		$starter = \DB::query('SELECT Id, document_title FROM ' . $documents . ' WHERE Id=1 AND rubric_id=1 LIMIT 1')->getAssoc();
		$fieldRows = \DB::query('SELECT * FROM ' . $fields . ' WHERE rubric_id=1 ORDER BY rubric_field_position, Id')->getAll();
		$queries = 2;
		if (!is_array($starter) || strpos((string) $starter['document_title'], 'AVE.cms') !== 0 || count($fieldRows) !== 1) {
			return $queries;
		}

		$source = $fieldRows[0];
		$sourceId = (int) $source['Id'];
		$layout = json_encode(array('width' => 'full'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$contentLayout = json_encode(array('width' => 'full', 'height' => 300), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		\DB::Update($fields, array(
			'rubric_field_alias' => 'title', 'rubric_field_title' => 'Заголовок страницы',
			'rubric_field_type' => 'single_line', 'rubric_field_position' => 1,
			'rubric_field_settings' => $layout,
			'rubric_field_description' => 'Заголовок внутри содержимого страницы',
		), 'Id=%i', $sourceId);
		\DB::Insert($fields, array(
			'rubric_id' => 1, 'rubric_field_group' => null, 'rubric_field_alias' => 'content',
			'rubric_field_title' => 'Содержимое страницы', 'rubric_field_type' => 'richtext',
			'rubric_field_numeric' => 0, 'rubric_field_position' => 2, 'rubric_field_default' => '',
			'rubric_field_settings' => $contentLayout, 'rubric_field_search' => 1,
			'rubric_field_template' => '', 'rubric_field_template_request' => '',
			'rubric_field_description' => 'Основной текст страницы с визуальным редактором',
		));
		$contentId = (int) \DB::insertId();
		$queries += 2;

		foreach (array(1, 2) as $documentId) {
			$document = \DB::query('SELECT document_title, document_in_search FROM ' . $documents . ' WHERE Id=%i AND rubric_id=1 LIMIT 1', $documentId)->getAssoc();
			if (!$document) { continue; }
			$oldText = \DB::query('SELECT field_value FROM ' . $textValues . ' WHERE document_id=%i AND rubric_field_id=%i LIMIT 1', $documentId, $sourceId)->getValue();
			\DB::Update($values, array(
				'field_value' => html_entity_decode((string) $document['document_title'], ENT_QUOTES, 'UTF-8'),
			), 'document_id=%i AND rubric_field_id=%i', $documentId, $sourceId);
			\DB::Insert($values, array(
				'rubric_field_id' => $contentId, 'document_id' => $documentId,
				'field_number_value' => 0, 'field_value' => '',
				'document_in_search' => (int) $document['document_in_search'],
			));
			$valueId = (int) \DB::insertId();
			\DB::Insert($textValues, array(
				'Id' => $valueId, 'rubric_field_id' => $contentId, 'document_id' => $documentId,
				'field_value' => (string) $oldText,
			));
			\DB::Delete($textValues, 'document_id=%i AND rubric_field_id=%i', $documentId, $sourceId);
			$queries += 5;
		}

		\DB::Update($rubrics, array(
			'rubric_template' => '<main><h1>[tag:fld:title]</h1><div class="page-content">[tag:fld:content]</div></main>',
			'rubric_changed' => time(), 'rubric_changed_fields' => time(),
		), 'Id=1');
		return $queries + 1;
	};
