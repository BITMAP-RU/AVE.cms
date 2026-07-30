<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Computed.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	class Computed extends AbstractFieldType
	{
		public function code()
		{
			return 'computed';
		}

		public function name()
		{
			return 'Вычисляемое поле';
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'mode',
					'type' => 'select',
					'label' => 'Режим вычисления',
					'options' => array('text' => 'Текстовый шаблон', 'number' => 'Числовая формула'),
					'default' => 'text',
				),
				array(
					'key' => 'expression',
					'type' => 'textarea',
					'label' => 'Формула',
					'rows' => 5,
					'hint' => 'Текст: {{ price }} руб. Число: price * quantity. Используйте системные имена полей.',
				),
				array('key' => 'precision', 'type' => 'int', 'label' => 'Знаков после запятой', 'default' => 2),
				array('key' => 'fallback', 'type' => 'text', 'label' => 'Значение при ошибке'),
			);
		}

		public function validationSchema()
		{
			return array();
		}

		public function renderEdit(FieldContext $ctx)
		{
			return '<input type="hidden" name="' . $this->attr($ctx->inputName()) . '" value="' . $this->attr($ctx->value) . '">'
				. '<input class="input" type="text" value="' . $this->attr($ctx->value) . '" readonly'
				. ' aria-label="Вычисляемое значение">'
				. '<span class="field-hint">Значение пересчитывается при сохранении документа.</span>';
		}

		public function renderView(FieldContext $ctx)
		{
			return $this->e($ctx->value);
		}
	}
