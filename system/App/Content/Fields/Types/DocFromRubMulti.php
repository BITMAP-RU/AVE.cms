<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFromRubMulti.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\SerialFieldType;
	use App\Content\Fields\FieldContext;
	use App\Content\Fields\DocumentRelationFieldTrait;
	use App\Content\Fields\DocumentRelationValue;

	/**
	 * База для множественных связей «документы из рубрики»
	 * (doc_from_rub_check / doc_from_rub_search / doc_from_rub_all).
	 * value = список ID документов; источник — рубрика(и) из settings.rubric.
	 */
	abstract class DocFromRubMulti extends SerialFieldType
	{
		use DocumentRelationFieldTrait;

		/** Список id рубрик-источников из native-настроек. */
		protected function sourceRubrics(FieldContext $ctx)
		{
			$raw = $ctx->setting('rubric', array());
			$ids = array();
			foreach (is_array($raw) ? $raw : explode(',', (string) $raw) as $r) {
				$r = (int) trim((string) $r);
				if ($r > 0) {
					$ids[] = $r;
				}
			}

			return array_values(array_unique($ids));
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'rubric', 'type' => 'rubric', 'label' => 'Рубрика-источник', 'multiple' => true),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$ids = $this->documentIds($ctx->value);
			$rubrics = $this->sourceRubrics($ctx);
			$rubricAttr = !empty($rubrics) ? ' data-document-picker-rubric="' . $this->attr(implode(',', $rubrics)) . '"' : '';
			return '<div class="documents-relation-field" data-document-relation-list data-field-id="' . $ctx->fieldId() . '"' . $rubricAttr . '>'
				. '<textarea class="textarea" rows="3" placeholder="ID документов через запятую" name="' . $this->attr($ctx->inputName()) . '">'
				. $this->e(implode(', ', $ids)) . '</textarea>'
				. '<button class="btn btn-secondary btn-sm" type="button" data-document-relation-pick><i class="ti ti-file-plus"></i>Добавить документ</button>'
				. '</div>';
		}

		public function save(FieldContext $ctx)
		{
			return DocumentRelationValue::encodeStorage($this->code(), $ctx->value);
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->renderDocumentLinks($this->documentIds($ctx->value));
		}

		/** Разбор ID документов из массива/CSV/serialize/JSON/legacy `label|id`. */
		protected function documentIds($value)
		{
			return DocumentRelationValue::ids($value);
		}
	}
