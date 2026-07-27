<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiSelect.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;

	/** Множественный выбор списком (AVE multi_select) — сериализованный массив значений. */
	class MultiSelect extends MultiCheckbox
	{
		public function code()
		{
			return 'multi_select';
		}

		public function name()
		{
			return 'Множественный список';
		}

		public function settingsSchema()
		{
			return array(
				array('key' => 'options', 'type' => 'list', 'label' => 'Варианты'),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$map = $this->optionMap($ctx);
			if (empty($map)) {
				return parent::renderEdit($ctx);
			}

			$selected = $this->selectedList($ctx->value);
			$html = '<select class="select" multiple size="6" name="' . $this->attr($ctx->inputName()) . '[]">';
			foreach ($map as $value => $label) {
				if ($label === '') {
					continue;
				}

				$sel = (in_array((string) $value, $selected, true) || in_array((string) $label, $selected, true)) ? ' selected' : '';
				$html .= '<option value="' . $this->attr($value) . '"' . $sel . '>' . $this->e($label) . '</option>';
			}

			return $html . '</select>';
		}
	}
