<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/ChoiceFieldEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	class ChoiceFieldEditor extends AbstractFieldEditor
	{
		public function parseValue($type, $value)
		{
			$type = (string) $type;
			$value = (string) $value;

			if ($type === 'choice') {
				$items = $this->structuredScalars($value);
				if ($items === null && strpos($value, ',') !== false) {
					$items = explode(',', $value);
				}

				return $items === null
					? array('raw' => $value, 'selected' => $value)
					: array('raw' => $value, 'selected' => $this->cleanItems($items));
			}

			if (in_array($type, array('multi_select', 'checkbox_multi', 'multi_checkbox'), true)) {
				$items = $this->structuredScalars($value);
				if ($items === null) {
					$items = $type === 'multi_select'
						? array()
						: $this->pipeItems($value);
				}

				return array('raw' => $value, 'selected' => $this->cleanItems($items));
			}

			return array('raw' => $value, 'selected' => $value);
		}

		public function serializeValue($type, $value)
		{
			$type = (string) $type;
			if (!is_array($value)) {
				return (string) $value;
			}

			if ($type === 'choice') {
				$selected = isset($value['selected']) ? $value['selected'] : array();
				if (is_array($selected)) {
					$selected = $this->cleanItems($selected);
					return !empty($selected) ? Json::encode(array_values($selected), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
				}

				return trim((string) $selected);
			}

			if ($type === 'multi_select') {
				$selected = isset($value['selected']) && is_array($value['selected']) ? $value['selected'] : array();
				$selected = $this->cleanItems($selected);
				return !empty($selected) ? serialize($selected) : '';
			}

			if (in_array($type, array('checkbox_multi', 'multi_checkbox'), true)) {
				$selected = isset($value['selected']) && is_array($value['selected']) ? $value['selected'] : array();
				$selected = $this->cleanItems($selected);
				return !empty($selected) ? '|' . implode('|', $selected) . '|' : '';
			}

			return isset($value['selected']) ? (string) $value['selected'] : '';
		}

		public function normalizeDefault($type, $value)
		{
			if ((string) $type === 'choice') {
				return trim((string) $value);
			}

			return $this->commaList($value);
		}

		public function renderEdit(array $field)
		{
			$kind = isset($field['editor_kind']) ? (string) $field['editor_kind'] : 'choice';
			$parsed = $this->value($field);
			$options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
			$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
			$presentation = isset($settings['presentation']) ? (string) $settings['presentation'] : 'select';

			if ((string) $field['rubric_field_type'] === 'choice') {
				$kind = isset($settings['mode']) && (string) $settings['mode'] === 'multiple' ? 'choice_multi' : 'choice';
			}

			if ($kind === 'choice_multi') {
				$selected = is_array($parsed) ? array_map('strval', $parsed) : array();
				if ($presentation === 'select') {
					$html = '<input type="hidden" name="' . $this->e($this->fname($field, '[choice_present]')) . '" value="1">'
						. '<select class="select" multiple size="6" name="' . $this->e($this->fname($field, '[selected][]')) . '">';
					foreach ($options as $option) {
						$ov = isset($option['value']) ? (string) $option['value'] : '';
						$ol = isset($option['label']) ? (string) $option['label'] : $ov;
						$html .= '<option value="' . $this->e($ov) . '"' . (in_array($ov, $selected, true) ? ' selected' : '') . '>' . $this->e($ol) . '</option>';
					}

					return $html . '</select>';
				}

				$html = '<input type="hidden" name="' . $this->e($this->fname($field, '[choice_present]')) . '" value="1">'
					. '<div class="documents-choice-grid">';
				foreach ($options as $option) {
					$ov = isset($option['value']) ? (string) $option['value'] : '';
					$ol = isset($option['label']) ? (string) $option['label'] : $ov;
					$html .= '<label class="documents-choice-option"><input type="checkbox" name="'
						. $this->e($this->fname($field, '[selected][]')) . '" value="' . $this->e($ov) . '"'
						. (in_array($ov, $selected, true) ? ' checked' : '') . '><span>' . $this->e($ol) . '</span></label>';
				}

				return $html . (empty($options) ? '<span class="text-secondary text-sm">Варианты не настроены.</span>' : '') . '</div>';
			}

			$current = is_array($parsed) ? '' : (string) $parsed;
			if ((string) $field['rubric_field_type'] === 'choice' && $presentation === 'checks') {
				$html = '<div class="documents-choice-grid">';
				foreach ($options as $option) {
					$ov = isset($option['value']) ? (string) $option['value'] : '';
					$ol = isset($option['label']) ? (string) $option['label'] : $ov;
					$html .= '<label class="documents-choice-option"><input type="radio" name="'
						. $this->e($this->fname($field, '[selected]')) . '" value="' . $this->e($ov) . '"'
						. ($current === $ov ? ' checked' : '') . '><span>' . $this->e($ol) . '</span></label>';
				}

				return $html . (empty($options) ? '<span class="text-secondary text-sm">Варианты не настроены.</span>' : '') . '</div>';
			}

			$html = '<select class="select" name="' . $this->e($this->fname($field, '[selected]')) . '">'
				. '<option value="">Не выбрано</option>';
			foreach ($options as $option) {
				$ov = isset($option['value']) ? (string) $option['value'] : '';
				$ol = isset($option['label']) ? (string) $option['label'] : $ov;
				$html .= '<option value="' . $this->e($ov) . '"' . ($current === $ov ? ' selected' : '') . '>' . $this->e($ol) . '</option>';
			}

			return $html . '</select>';
		}

		protected function definitions()
		{
			return array(
				'choice' => array(
					'title' => 'Выбор из списка',
					'icon' => 'list-check',
					'summary' => 'Одиночный или множественный выбор со стабильными ключами и JSON-настройками.',
					'default_label' => 'Выбранные ключи по умолчанию',
					'default_hint' => 'Для множественного режима перечислите ключи через запятую.',
					'default_kind' => 'text',
					'entity_fields' => array('selected[]'),
				),
				'drop_down' => array(
					'title' => 'Выпадающий список',
					'icon' => 'list',
					'summary' => 'Один вариант из списка. Варианты задаются строками.',
					'default_label' => 'Варианты',
					'default_hint' => 'Один вариант на строку.',
					'default_kind' => 'lines',
					'entity_fields' => array('selected'),
				),
				'drop_down_key' => array(
					'title' => 'Список ключ-значение',
					'icon' => 'list-details',
					'summary' => 'Список, где слева хранится ключ, справа показывается подпись.',
					'default_label' => 'Варианты',
					'default_hint' => 'Формат строки: key|Название. Один вариант на строку.',
					'default_kind' => 'key_value',
					'entity_fields' => array('selected'),
				),
				'multi_select' => array(
					'title' => 'Множественный выбор',
					'icon' => 'select',
					'summary' => 'Несколько вариантов из списка, варианты задаются в настройке поля.',
					'default_label' => 'Варианты',
					'default_hint' => 'Один вариант на строку.',
					'default_kind' => 'lines',
					'entity_fields' => array('selected[]'),
				),
				'checkbox_multi' => array(
					'title' => 'Несколько флажков',
					'icon' => 'checks',
					'summary' => 'Несколько значений из заданного набора.',
					'default_label' => 'Варианты',
					'default_hint' => 'Один вариант на строку. Для ключей используйте key|Название.',
					'default_kind' => 'key_value',
					'entity_fields' => array('selected[]'),
				),
				'multi_checkbox' => array(
					'title' => 'Мультивыбор',
					'icon' => 'checks',
					'summary' => 'Несколько отмеченных вариантов.',
					'default_label' => 'Варианты',
					'default_hint' => 'Один вариант на строку.',
					'default_kind' => 'lines',
					'entity_fields' => array('selected[]'),
				),
			);
		}

		protected function pipeItems($value)
		{
			return $this->cleanItems(explode('|', (string) $value));
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
