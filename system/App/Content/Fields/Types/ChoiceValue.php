<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ChoiceValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;
	use App\Helpers\Json;

	/** Stable key/label choice for new single and multiple fields. */
	class ChoiceValue extends AbstractFieldType
	{
		use OptionFieldSupport;

		public function code()
		{
			return 'choice';
		}

		public function name()
		{
			return 'Выбор из списка';
		}

		public function isChoice()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array_merge(array(
				array(
					'key' => 'mode',
					'type' => 'select',
					'label' => 'Режим выбора',
					'options' => array('single' => 'Один вариант', 'multiple' => 'Несколько вариантов'),
					'default' => 'single',
				),
				array(
					'key' => 'presentation',
					'type' => 'select',
					'label' => 'Вид в редакторе',
					'options' => array('select' => 'Выпадающий список', 'checks' => 'Варианты списком'),
					'default' => 'select',
				),
			), $this->optionSettingsSchema(array(
					'key' => 'options',
					'type' => 'map',
					'label' => 'Ключ → подпись',
					'hint' => 'Стабильный ключ хранится в документе. Подпись можно менять без миграции значений.',
				)), array(
				array(
					'key' => 'separator',
					'type' => 'text',
					'label' => 'Разделитель на сайте',
					'default' => ', ',
					'hint' => 'Используется при выводе нескольких выбранных значений.',
				),
			));
		}

		public function validationSchema()
		{
			return array(
				array('key' => 'required', 'type' => 'bool', 'label' => 'Обязательное'),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			$multiple = $this->multiple($ctx);
			$selected = $multiple ? $this->selection($ctx->value) : array((string) $ctx->value);
			$presentation = (string) $ctx->setting('presentation', 'select');

			if ($presentation === 'checks') {
				$html = '<div class="choice-list">';
				foreach ($map as $value => $label) {
					$html .= '<label class="choice-item"><input type="' . ($multiple ? 'checkbox' : 'radio') . '" name="'
						. $this->attr($ctx->inputName()) . ($multiple ? '[]' : '') . '" value="' . $this->attr($value) . '"'
						. (in_array((string) $value, $selected, true) ? ' checked' : '') . '><span>' . $this->e($label) . '</span></label>';
				}

				return $html . '</div>';
			}

			$html = '<select class="select" name="' . $this->attr($ctx->inputName()) . ($multiple ? '[]' : '') . '"'
				. ($multiple ? ' multiple size="6"' : '') . '>';
			if (!$multiple) {
				$html .= '<option value="">— не выбрано —</option>';
			}

			foreach ($map as $value => $label) {
				$html .= '<option value="' . $this->attr($value) . '"'
					. (in_array((string) $value, $selected, true) ? ' selected' : '') . '>' . $this->e($label) . '</option>';
			}

			return $html . '</select>';
		}

		public function renderView(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			if (!$this->multiple($ctx)) {
				$value = trim((string) $ctx->value);
				if ($value === '') {
					return '';
				}

				if (array_key_exists($value, $map)) {
					return $this->e($map[$value]);
				}

				return (string) $ctx->setting('option_source', 'local') === 'directory' ? $this->e($value) : '';
			}

			$labels = array();
			foreach ($this->selection($ctx->value) as $value) {
				if (array_key_exists($value, $map)) {
					$labels[] = $this->e($map[$value]);
				} elseif ((string) $ctx->setting('option_source', 'local') === 'directory') {
					$labels[] = $this->e($value);
				}
			}

			$separator = (string) $ctx->setting('separator', ', ');
			return implode($this->e($separator), $labels);
		}

		public function save(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			$selected = $this->selection($ctx->value);
			foreach ($selected as $value) {
				if (!array_key_exists($value, $map)) {
					throw new \RuntimeException('Выбран недопустимый вариант');
				}
			}

			if ($this->multiple($ctx)) {
				return empty($selected) ? '' : Json::encode(array_values($selected), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}

			return isset($selected[0]) ? (string) $selected[0] : '';
		}

		public function valid(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			foreach ($this->selection($ctx->value) as $value) {
				if (!array_key_exists($value, $map)) {
					return false;
				}
			}

			return true;
		}

		protected function multiple(FieldContext $ctx)
		{
			return (string) $ctx->setting('mode', 'single') === 'multiple';
		}

		protected function selection($value)
		{
			if (is_array($value) && array_key_exists('selected', $value)) {
				$value = $value['selected'];
			}

			$selected = $this->selectedList($value);
			return array_values(array_unique(array_map('strval', $selected)));
		}
	}
