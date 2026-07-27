<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiCheckbox.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;

	/** Множественный выбор (AVE multi_checkbox) — сериализованный массив значений. */
	class MultiCheckbox extends MultiList
	{
		use OptionFieldSupport;

		public function code()
		{
			return 'multi_checkbox';
		}

		public function name()
		{
			return 'Множественный выбор';
		}

		public function isChoice()
		{
			return true;
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'options', 'type' => 'map', 'label' => 'Ключ → подпись'),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			if (empty($map)) {
				return parent::renderEdit($ctx);
			}

			$selected = $this->selectedList($ctx->value);
			$html = '<div class="choice-list">';
			foreach ($map as $value => $label) {
				if ($label === '') {
					continue;
				}

				$checked = in_array((string) $value, $selected, true) || in_array((string) $label, $selected, true);
				$html .= '<label class="choice-item"><input type="checkbox" name="' . $this->attr($ctx->inputName())
					. '[]" value="' . $this->attr($value) . '"' . ($checked ? ' checked' : '') . '><span>'
					. $this->e($label) . '</span></label>';
			}

			return $html . '</div>';
		}

		public function save(FieldContext $ctx)
		{
			return $this->encodeJson($this->selectedList($ctx->value));
		}

		public function renderView(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			$selected = $this->selectedList($ctx->value);
			if (empty($selected)) {
				return '';
			}

			$out = array();
			foreach ($selected as $value) {
				$out[] = $this->e($this->optionLabel($map, $value));
			}

			return implode(', ', $out);
		}
	}
