<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFromRub.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\ContentTables;
	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;
	use App\Content\Fields\DocumentRelationValue;

	/**
	 * Документ из рубрики (AVE doc_from_rub). value = ID документа.
	 * Настройка: рубрика(и)-источник из settings.rubric.
	 */
	class DocFromRub extends AbstractFieldType
	{
		public function code()
		{
			return 'doc_from_rub';
		}

		public function name()
		{
			return 'Документ из рубрики';
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'rubric', 'type' => 'rubric', 'label' => 'Рубрика-источник', 'multiple' => true),
			);
		}

		/** Список id рубрик-источников из native-настроек. */
		protected function sourceRubrics(FieldContext $ctx)
		{
			$raw = $ctx->setting('rubric', array());
			$ids = array();
			foreach (is_array($raw) ? $raw : explode(',', (string) $raw) as $r) {
				$r = (int) trim((string) $r);
				if ($r > 0) { $ids[] = $r; }
			}

			return array_values(array_unique($ids));
		}

		public function renderEdit(FieldContext $ctx)
		{
			$rubrics = $this->sourceRubrics($ctx);
			$selected = DocumentRelationValue::ids($ctx->value);
			$rubricAttr = !empty($rubrics) ? ' data-document-picker-rubric="' . $this->attr(implode(',', $rubrics)) . '"' : '';
			return '<div class="documents-picker-input"' . $rubricAttr . '>'
				. '<input class="input" type="number" name="' . $this->attr($ctx->inputName('[document_id]')) . '"'
				. ' value="' . $this->attr($selected ? (int) $selected[0] : '') . '" placeholder="ID документа" data-document-relation-id>'
				. '<button class="btn btn-secondary btn-icon btn-sm" type="button" data-document-relation-pick'
				. ' data-tooltip="Выбрать документ" aria-label="Выбрать документ"><i class="ti ti-file-search"></i></button>'
				. '</div>';
		}

		public function renderView(FieldContext $ctx)
		{
			$ids = DocumentRelationValue::ids($ctx->value);
			$id = $ids ? (int) $ids[0] : 0;
			if ($id <= 0) {
				return '';
			}

			$doc = DB::query(
				'SELECT document_title, document_alias FROM ' . ContentTables::table('documents') . " WHERE Id = %i AND document_deleted = '0' LIMIT 1",
				$id
			)->getObject();
			if (!$doc) {
				return '';
			}

			return htmlspecialchars_decode(stripcslashes((string) $doc->document_title), ENT_QUOTES);
		}

		public function save(FieldContext $ctx)
		{
			return DocumentRelationValue::encodeStorage($this->code(), $ctx->value);
		}
	}
