<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/RelationFieldEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	class RelationFieldEditor extends AbstractFieldEditor
	{
		public function parseValue($type, $value)
		{
			$type = (string) $type;
			$value = (string) $value;

			if ($type === 'tags') {
				$items = $this->structuredScalars($value);
				return array('raw' => $value, 'tags' => $items === null ? $this->pipeItems($value) : $this->cleanItems($items));
			}

			if (in_array($type, array('doc_from_rub_check', 'doc_from_rub_search'), true)) {
				$items = $this->structuredScalars($value);
				return array('raw' => $value, 'document_ids' => $items === null ? $this->pipeItems($value) : $this->documentIds($items));
			}

			if (in_array($type, array('analoque', 'teasers'), true)) {
				return array('raw' => $value, 'document_ids' => $this->documentIds($value));
			}

			if ($type === 'doc_from_rub') {
				return array('raw' => $value, 'document_id' => trim($value));
			}

			if ($type === 'catalog') {
				$data = @unserialize($value, array('allowed_classes' => false));
				if (!is_array($data)) {
					$data = array();
				}

				return array(
					'raw' => $value,
					'catalog_ids' => $this->csvItems(isset($data['catalogs']) ? $data['catalogs'] : ''),
					'document_ids' => $this->pipeItems(isset($data['documents']) ? $data['documents'] : ''),
					'names' => $this->csvItems(isset($data['names']) ? $data['names'] : ''),
				);
			}

			return parent::parseValue($type, $value);
		}

		public function serializeValue($type, $value)
		{
			$type = (string) $type;
			if (!is_array($value)) {
				return (string) $value;
			}

			if ($type === 'tags') {
				return $this->wrapPipeArray(isset($value['tags']) ? $value['tags'] : array());
			}

			if (in_array($type, array('doc_from_rub_check', 'doc_from_rub_search'), true)) {
				return $this->wrapPipeArray(isset($value['document_ids']) ? $value['document_ids'] : array());
			}

			if (in_array($type, array('analoque', 'teasers'), true)) {
				$ids = isset($value['document_ids']) && is_array($value['document_ids']) ? $value['document_ids'] : array();
				return implode(',', $this->cleanItems($ids));
			}

			if ($type === 'doc_from_rub') {
				return isset($value['document_id']) ? (string) (int) $value['document_id'] : '';
			}

			if ($type === 'catalog') {
				$catalogIds = isset($value['catalog_ids']) && is_array($value['catalog_ids']) ? $value['catalog_ids'] : array();
				$documentIds = isset($value['document_ids']) && is_array($value['document_ids']) ? $value['document_ids'] : array();
				$names = isset($value['names']) && is_array($value['names']) ? $value['names'] : array();
				$data = array(
					'catalogs' => implode(',', $this->cleanItems($catalogIds)),
					'documents' => $this->wrapPipeArray($documentIds),
				);
				if (!empty($names)) {
					$data['names'] = implode(',', $this->cleanItems($names));
				}

				return serialize($data);
			}

			return parent::serializeValue($type, $value);
		}

		public function normalizeDefault($type, $value)
		{
			$type = (string) $type;
			if (in_array($type, array('tags', 'doc_from_rub_check', 'doc_from_rub_search'), true)) {
				return $this->pipeWrappedList($value);
			}

			if ($type === 'analoque') {
				return $this->commaList($value);
			}

			if ($type === 'catalog') {
				return $this->commaList($value);
			}

			if ($type === 'doc_from_rub') {
				$value = trim((string) $value);
				return $value === '' ? '' : (string) (0 + $value);
			}

			return (string) $value;
		}

		public function renderEdit(array $field)
		{
			$kind = isset($field['editor_kind']) ? (string) $field['editor_kind'] : 'textarea';
			$parsed = $this->value($field);

			if ($kind === 'relation') {
				$type = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
				$isDocFromRub = ($type === 'doc_from_rub');
				$rubricIds = isset($field['relation_rubric_ids']) && is_array($field['relation_rubric_ids'])
					? array_filter(array_map('intval', $field['relation_rubric_ids']))
					: array();
				$rubric = $isDocFromRub ? implode(',', $rubricIds) : '';
				if ($isDocFromRub) {
					// Значение показываем только если у документа реально сохранён выбор,
					// иначе parsed['document_id'] равен id рубрики из default.
					$val = ((int) (isset($field['document_field_id']) ? $field['document_field_id'] : 0) > 0 && is_array($parsed))
						? (string) (int) (isset($parsed['document_id']) ? $parsed['document_id'] : 0)
						: '';
				} else {
					$val = is_array($parsed) ? '' : (string) $parsed;
				}

				$rubricAttr = $rubric !== '' ? ' data-document-picker-rubric="' . $this->e($rubric) . '"' : '';
				$hint = ($isDocFromRub && $rubric !== '')
					? '<span class="field-hint">Только документы из рубрики #' . $this->e($rubric) . '.</span>'
					: '';
				$hasValue = ($val !== '' && (int) $val > 0);
				$selected = isset($field['relation_selected']) && is_array($field['relation_selected']) ? $field['relation_selected'] : array();
				$title = $hasValue
					? (isset($selected['title']) && $selected['title'] !== '' ? (string) $selected['title'] : ('Документ #' . (int) $val))
					: 'Документ не выбран';
				$idLabel = $hasValue ? '#' . (int) $val : 'нажмите, чтобы выбрать';
				return '<div class="documents-relation-single' . ($hasValue ? '' : ' is-empty') . '" data-document-relation-single' . $rubricAttr . '>'
					. '<input type="hidden" name="' . $this->e($this->fname($field, '[document_id]')) . '" value="' . $this->e($hasValue ? (string) (int) $val : '') . '" data-document-relation-id>'
					. '<button type="button" class="documents-relation-choice" data-document-relation-pick data-tooltip="Выбрать документ">'
					. '<i class="ti ti-file-text documents-relation-choice-ic"></i>'
					. '<span class="documents-relation-choice-text"><b data-relation-title>' . $this->e($title) . '</b>'
					. '<small data-relation-idlabel>' . $this->e($idLabel) . '</small></span>'
					. '<i class="ti ti-selector documents-relation-choice-caret"></i>'
					. '</button>'
					. '<button type="button" class="btn btn-ghost btn-icon btn-sm documents-relation-clear" data-document-relation-clear data-tooltip="Очистить" aria-label="Очистить"' . ($hasValue ? '' : ' hidden') . '><i class="ti ti-x"></i></button>'
					. '</div>' . $hint;
			}

			if ($kind === 'relation_list') {
				return $this->renderRelationList($field);
			}

			if (in_array($kind, array('lines', 'key_value', 'pipe_list'), true)) {
				$val = is_array($parsed) ? '' : (string) $parsed;
				$hint = in_array($kind, array('key_value', 'pipe_list'), true)
					? 'Одна строка на запись, части разделяются символом |.'
					: 'Одна строка на значение.';
				return '<textarea class="textarea mono" name="' . $this->e($this->fname($field, '[lines]')) . '" rows="5">' . $this->e($val) . '</textarea>'
					. '<span class="field-hint">' . $hint . '</span>';
			}

			return $this->renderGeneric($field);
		}

		protected function renderRelationList(array $field)
		{
			$type = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
			$rubricIds = isset($field['relation_rubric_ids']) && is_array($field['relation_rubric_ids'])
				? array_values(array_filter(array_map('intval', $field['relation_rubric_ids'])))
				: array();
			$rubric = in_array($type, array('doc_from_rub_check', 'doc_from_rub_search'), true)
				? implode(',', $rubricIds)
				: '';
			$items = isset($field['relation_items']) && is_array($field['relation_items']) ? $field['relation_items'] : array();
			$tokens = '';
			foreach ($items as $item) {
				$id = isset($item['id']) ? (int) $item['id'] : 0;
				if ($id <= 0) { continue; }
				$title = isset($item['title']) ? (string) $item['title'] : ('Документ #' . $id);
				$tokens .= '<span class="documents-relation-token" data-document-relation-token data-relation-id="' . $id . '">'
					. '<span><b>' . $this->e($title) . '</b><small>#' . $id . '</small></span>'
					. '<input type="hidden" name="' . $this->e($this->fname($field, '[document_ids][]')) . '" value="' . $id . '">'
					. '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-document-relation-remove aria-label="Убрать документ" data-tooltip="Убрать"><i class="ti ti-x"></i></button>'
					. '</span>';
			}

			$rubricAttr = $rubric !== '' ? ' data-document-picker-rubric="' . $this->e($rubric) . '"' : '';
			return '<div class="documents-relation-field" data-document-relation-list data-field-id="' . (int) $field['Id'] . '"' . $rubricAttr . '>'
				. '<input type="hidden" name="' . $this->e($this->fname($field, '[relation_present]')) . '" value="1">'
				. '<div class="documents-relation-tokens" data-document-relation-tokens>' . $tokens . '</div>'
				. '<p class="documents-relation-empty"' . (!empty($items) ? ' hidden' : '') . ' data-document-relation-empty>Документы не выбраны.</p>'
				. '<button class="btn btn-secondary btn-sm" type="button" data-document-relation-pick><i class="ti ti-file-plus"></i>Добавить документ</button>'
				. '</div>';
		}

		protected function definitions()
		{
			return array(
				'tags' => array(
					'title' => 'Теги',
					'icon' => 'tags',
					'summary' => 'Набор тегов документа.',
					'default_label' => 'Теги по умолчанию',
					'default_hint' => 'Один тег на строку.',
					'default_kind' => 'lines',
					'entity_fields' => array('tags[]'),
				),
				'doc_from_rub' => array(
					'title' => 'Документ из рубрики',
					'icon' => 'file-export',
					'summary' => 'Связь с одним документом из выбранной рубрики.',
					'default_label' => 'Документ по умолчанию',
					'default_hint' => 'ID документа или пусто.',
					'default_kind' => 'relation',
					'entity_fields' => array('document_id'),
				),
				'doc_from_rub_all' => array(
					'title' => 'Документы из рубрики',
					'icon' => 'files',
					'summary' => 'Вывод всех связанных документов из рубрики.',
					'default_label' => 'Значение по умолчанию',
					'default_hint' => 'Обычно пусто; выбор задаётся настройками legacy-поля.',
					'default_kind' => 'textarea',
					'entity_fields' => array('rubric_id', 'template'),
				),
				'doc_from_rub_check' => array(
					'title' => 'Документы из рубрики с выбором',
					'icon' => 'checks',
					'summary' => 'Несколько связанных документов, legacy хранит выбранные ID через |.',
					'default_label' => 'ID документов по умолчанию',
					'default_hint' => 'Один ID на строку.',
					'default_kind' => 'relation_list',
					'entity_fields' => array('document_ids[]'),
				),
				'doc_from_rub_search' => array(
					'title' => 'Поиск документов из рубрики',
					'icon' => 'file-search',
					'summary' => 'Связанные документы с поиском, legacy хранит выбранные ID через |.',
					'default_label' => 'ID документов по умолчанию',
					'default_hint' => 'Один ID на строку.',
					'default_kind' => 'relation_list',
					'entity_fields' => array('document_ids[]'),
				),
				'catalog' => array(
					'title' => 'Каталог',
					'icon' => 'building-store',
					'summary' => 'Связь с legacy-модулем catalog.',
					'default_label' => 'Каталоги по умолчанию',
					'default_hint' => 'Один ID каталога на строку. Legacy сохранение документов использует сериализованный массив.',
					'default_kind' => 'lines',
					'entity_fields' => array('catalog_ids[]', 'document_ids[]', 'names[]'),
				),
				'analoque' => array(
					'title' => 'Аналоги / вместе с товаром',
					'icon' => 'relation-one-to-many',
					'summary' => 'Список связанных документов. Legacy-данные встречаются как ID через запятую, JSON или serialize.',
					'default_label' => 'ID документов по умолчанию',
					'default_hint' => 'Один ID на строку или через запятую.',
					'default_kind' => 'relation_list',
					'entity_fields' => array('document_ids[]'),
				),
				'teasers' => array(
					'title' => 'Тизеры документов',
					'icon' => 'layout-list',
					'summary' => 'Упорядоченный список документов, которые выводятся как тизеры.',
					'default_label' => 'Документы по умолчанию',
					'default_hint' => 'Один ID документа на строку или через запятую.',
					'default_kind' => 'relation_list',
					'entity_fields' => array('document_ids[]'),
				),
			);
		}

		protected function pipeItems($value)
		{
			return $this->cleanItems(explode('|', (string) $value));
		}

		protected function csvItems($value)
		{
			return $this->cleanItems(explode(',', (string) $value));
		}

		protected function wrapPipeArray(array $items)
		{
			$items = $this->cleanItems($items);
			return !empty($items) ? '|' . implode('|', $items) . '|' : '';
		}

		protected function documentIds($value)
		{
			if (is_array($value)) {
				$items = $this->flatten($value);
				$value = implode(',', $items);
			} else {
				$value = trim((string) $value);
			}

			if ($value === '') {
				return array();
			}

			$items = array($value);
			$decoded = $this->structuredValue($value);
			if (is_array($decoded)) {
				$items = $this->flatten($decoded);
			}

			$ids = array();
			foreach ($items as $item) {
				foreach (preg_split('/[,\s]+/', (string) $item) ?: array() as $part) {
					$part = trim((string) $part);
					if ($part === '') {
						continue;
					}

					if (strpos($part, '|') !== false) {
						$bits = explode('|', $part);
						$part = trim((string) end($bits));
					}

					if (ctype_digit($part) && (int) $part > 0) {
						$ids[] = (string) (int) $part;
					}
				}
			}

			return array_values(array_unique($ids));
		}

		protected function flatten($value)
		{
			$out = array();
			if (is_array($value)) {
				foreach ($value as $item) {
					$out = array_merge($out, $this->flatten($item));
				}

				return $out;
			}

			return array((string) $value);
		}

		protected function cleanItems(array $items)
		{
			$out = array();
			foreach ($items as $item) {
				$item = trim((string) $item);
				if ($item !== '') {
					$out[] = $item;
				}
			}

			return array_values($out);
		}
	}
