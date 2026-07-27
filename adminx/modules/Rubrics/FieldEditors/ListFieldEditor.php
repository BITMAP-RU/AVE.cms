<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Rubrics/FieldEditors/ListFieldEditor.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Rubrics\FieldEditors;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Json;

	class ListFieldEditor extends AbstractFieldEditor
	{
		public function parseValue($type, $value)
		{
			$type = (string) $type;
			if ($type === 'packages') {
				return array(
					'raw' => (string) $value,
					'items' => \App\Content\Fields\Types\PackagesValue::parseItems($value),
				);
			}

			$items = $this->decodeListValue($value);

			$out = array();
			foreach ($items as $item) {
				if (is_array($item)) {
					if ($type === 'multi_list_single') {
						$single = isset($item['value']) ? (string) $item['value'] : (isset($item['param']) ? (string) $item['param'] : '');
						if ($single !== '') {
							$out[] = array('value' => $single);
						}

						continue;
					}

					if ($type === 'multi_list_triple') {
						$param = isset($item['param']) ? (string) $item['param'] : (isset($item['title']) ? (string) $item['title'] : '');
						if ($param !== '') {
							$out[] = array(
								'param' => $param,
								'value' => isset($item['value']) ? (string) $item['value'] : (isset($item['url']) ? (string) $item['url'] : ''),
								'value2' => isset($item['value2']) ? (string) $item['value2'] : (isset($item['description']) ? (string) $item['description'] : ''),
							);
						}

						continue;
					}

					$param = isset($item['param']) ? (string) $item['param'] : (isset($item['title']) ? (string) $item['title'] : '');
					if ($param !== '') {
						$out[] = array(
							'param' => $param,
							'value' => isset($item['value']) ? (string) $item['value'] : (isset($item['url']) ? (string) $item['url'] : ''),
						);
					}

					continue;
				}

				$parts = explode('|', (string) $item);
				if ($type === 'multi_list_single') {
					$single = isset($parts[0]) ? trim((string) $parts[0]) : '';
					if ($single !== '') {
						$out[] = array('value' => $single);
					}
				} elseif ($type === 'multi_list_triple') {
					$param = isset($parts[0]) ? trim((string) $parts[0]) : '';
					if ($param !== '') {
						$out[] = array(
							'param' => $param,
							'value' => isset($parts[1]) ? (string) $parts[1] : '',
							'value2' => isset($parts[2]) ? (string) $parts[2] : '',
						);
					}
				} else {
					$param = isset($parts[0]) ? trim((string) $parts[0]) : '';
					if ($param !== '') {
						$out[] = array(
							'param' => $param,
							'value' => isset($parts[1]) ? implode('|', array_slice($parts, 1)) : '',
						);
					}
				}
			}

			return array('raw' => (string) $value, 'items' => $out);
		}

		public function serializeValue($type, $value)
		{
			$type = (string) $type;
			if (!is_array($value)) {
				return (string) $value;
			}

			$items = isset($value['items']) && is_array($value['items']) ? $value['items'] : array();
			if ($type === 'packages') {
				return \App\Content\Fields\Types\PackagesValue::encodeItems(array('items' => $items));
			}

			$out = array();
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}

				if ($type === 'multi_list_single') {
					$single = trim(isset($item['value']) ? (string) $item['value'] : '');
					if ($single !== '') {
						$out[] = $single;
					}
				} elseif ($type === 'multi_list_triple') {
					$param = trim(isset($item['param']) ? (string) $item['param'] : '');
					$val1 = trim(isset($item['value']) ? (string) $item['value'] : '');
					$val2 = trim(isset($item['value2']) ? (string) $item['value2'] : '');
					if ($param !== '') {
						$out[] = $param . '|' . $val1 . '|' . $val2;
					}
				} else {
					$param = trim(isset($item['param']) ? (string) $item['param'] : '');
					$val = trim(isset($item['value']) ? (string) $item['value'] : '');
					if ($param !== '') {
						$out[] = $param . ($val !== '' ? '|' . $val : '');
					}
				}
			}

			return !empty($out) ? serialize($out) : '';
		}

		protected function decodeListValue($value)
		{
			$value = trim((string) $value);
			if ($value === '') {
				return array();
			}

			$serialized = @unserialize($value, array('allowed_classes' => false));
			if (is_array($serialized)) {
				return $serialized;
			}

			if ($value[0] === '[' || $value[0] === '{') {
				try {
					$json = Json::decode($value);
				} catch (\RuntimeException $e) {
					$json = null;
				}

				if (is_array($json)) {
					if (isset($json['items']) && is_array($json['items'])) {
						return $json['items'];
					}

					if (isset($json[0])) {
						return $json;
					}

					return array($json);
				}
			}

			$lines = preg_split('/\r\n|\r|\n/', $value);
			if (count($lines) <= 1 && strpos($value, ',') !== false) {
				$lines = preg_split('/,/', $value);
			}

			$out = array();
			foreach ($lines ?: array() as $line) {
				$line = trim((string) $line);
				if ($line !== '') {
					$out[] = $line;
				}
			}

			return $out;
		}

		public function normalizeDefault($type, $value)
		{
			if ((string) $type === 'packages') {
				return \App\Content\Fields\Types\PackagesValue::encodeItems($value);
			}

			return $this->commaList($value);
		}

		public function renderEdit(array $field)
		{
			$kind = isset($field['editor_kind']) ? (string) $field['editor_kind'] : 'list_pair';
			$type = isset($field['rubric_field_type']) ? (string) $field['rubric_field_type'] : '';
			$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
			$parsed = $this->value($field);
			$items = isset($parsed['items']) && is_array($parsed['items']) ? $parsed['items'] : array();

			$rows = '';
			foreach ($items as $idx => $item) {
				$item = is_array($item) ? $item : array();
				$rows .= '<div class="documents-value-row documents-value-row-' . $this->e($kind) . '" data-document-value-row>'
					. '<div class="documents-value-row-tools">'
					. '<button class="btn btn-ghost btn-icon btn-sm documents-drag-handle" type="button" data-doc-drag draggable="true" data-tooltip="Перетащить" aria-label="Перетащить"><i class="ti ti-grip-vertical"></i></button>'
					. '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-document-value-up data-tooltip="Выше" aria-label="Выше"><i class="ti ti-arrow-up"></i></button>'
					. '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-document-value-down data-tooltip="Ниже" aria-label="Ниже"><i class="ti ti-arrow-down"></i></button>'
					. '</div>'
					. '<div class="documents-value-row-fields">' . $this->rowFields($field, $kind, $type, (int) $idx, $item) . '</div>'
					. '<button class="btn btn-ghost btn-icon btn-sm documents-value-remove" type="button" data-document-value-remove data-tooltip="Удалить" aria-label="Удалить"><i class="ti ti-trash"></i></button>'
					. '</div>';
			}

			$dimensionUnit = isset($settings['dimension_unit']) ? (string) $settings['dimension_unit'] : 'cm';
			$weightUnit = isset($settings['weight_unit']) ? (string) $settings['weight_unit'] : 'kg';
			return '<div class="documents-value-list" data-document-value-list data-field-id="' . (int) $field['Id']
				. '" data-list-kind="' . $this->e($kind) . '" data-field-type="' . $this->e($type)
				. '" data-dimension-unit="' . $this->e($this->dimensionUnitLabel($dimensionUnit))
				. '" data-weight-unit="' . $this->e($this->weightUnitLabel($weightUnit)) . '">'
				. '<input type="hidden" name="' . $this->e($this->fname($field, '[list_present]')) . '" value="1">'
				. '<div class="documents-value-list-actions">'
				. '<button class="btn btn-secondary btn-sm" type="button" data-document-value-add><i class="ti ti-plus"></i>Добавить</button>'
				. '<button class="btn btn-ghost btn-sm" type="button" data-document-value-clear><i class="ti ti-trash"></i>Очистить</button>'
				. '</div>'
				. '<div class="documents-value-list-items" data-document-value-items>' . $rows . '</div>'
				. '<div class="documents-value-empty"' . (!empty($items) ? ' hidden' : '') . ' data-document-value-empty>Нет добавленных строк.</div>'
				. '</div>';
		}

		protected function rowFields(array $field, $kind, $type, $idx, array $item)
		{
			$base = $this->fname($field, '[items][' . $idx . ']');
			$in = function ($sub, $key, $val, $ph) use ($base) {
				return '<input class="input" type="text" name="' . $this->e($base . $sub) . '" data-value-key="' . $key
					. '" value="' . $this->e($val) . '" placeholder="' . $this->e($ph) . '">';
			};

			if ($kind === 'packages') {
				$settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();
				$dimensionUnit = $this->dimensionUnitLabel(isset($settings['dimension_unit']) ? $settings['dimension_unit'] : 'cm');
				$weightUnit = $this->weightUnitLabel(isset($settings['weight_unit']) ? $settings['weight_unit'] : 'kg');
				return $in('[length]', 'length', isset($item['length']) ? $item['length'] : '', 'Длина, ' . $dimensionUnit)
					. $in('[width]', 'width', isset($item['width']) ? $item['width'] : '', 'Ширина, ' . $dimensionUnit)
					. $in('[height]', 'height', isset($item['height']) ? $item['height'] : '', 'Высота, ' . $dimensionUnit)
					. $in('[weight]', 'weight', isset($item['weight']) ? $item['weight'] : '', 'Вес, ' . $weightUnit);
			}

			if ($kind === 'list_single') {
				return $in('[value]', 'value', isset($item['value']) ? $item['value'] : '', 'Значение');
			}

			if ($kind === 'list_triple') {
				$isTriple = $type === 'multi_list_triple';
				return $in('[param]', 'param', isset($item['param']) ? $item['param'] : '', $isTriple ? 'Колонка 1' : 'Заголовок')
					. $in('[value]', 'value', isset($item['value']) ? $item['value'] : '', $isTriple ? 'Колонка 2' : 'Значение')
					. $in('[value2]', 'value2', isset($item['value2']) ? $item['value2'] : '', $isTriple ? 'Колонка 3' : 'Дополнительно');
			}

			$paramPh = $type === 'multi_links' ? 'Название' : ($type === 'teasers' ? 'Заголовок' : 'Параметр');
			$valuePh = $type === 'multi_links' ? 'URL' : ($type === 'teasers' ? 'ID тизера / ссылка' : 'Значение');
			return $in('[param]', 'param', isset($item['param']) ? $item['param'] : '', $paramPh)
				. $in('[value]', 'value', isset($item['value']) ? $item['value'] : '', $valuePh);
		}

		protected function dimensionUnitLabel($unit)
		{
			$units = array('mm' => 'мм', 'cm' => 'см', 'm' => 'м');
			$unit = (string) $unit;
			return isset($units[$unit]) ? $units[$unit] : $units['cm'];
		}

		protected function weightUnitLabel($unit)
		{
			return (string) $unit === 'g' ? 'г' : 'кг';
		}

		protected function definitions()
		{
			return array(
				'packages' => array(
					'title' => 'Упаковки товара',
					'icon' => 'packages',
					'summary' => 'Сортируемый список коробок с габаритами и весом для расчёта доставки.',
					'default_label' => 'Упаковки по умолчанию',
					'default_hint' => 'Одна упаковка на строку: длина|ширина|высота|вес.',
					'default_kind' => 'packages',
					'entity_fields' => array('items[].length', 'items[].width', 'items[].height', 'items[].weight'),
				),
				'multi_list' => array(
					'title' => 'Список записей',
					'icon' => 'list-details',
					'summary' => 'Повторяемый список записей, legacy хранит значения сериализованным массивом.',
					'default_label' => 'Строки по умолчанию',
					'default_hint' => 'Одна запись на строку. Для нескольких колонок используйте разделитель |.',
					'default_kind' => 'key_value',
					'entity_fields' => array('items[].param', 'items[].value'),
				),
				'multi_list_single' => array(
					'title' => 'Одноколоночный список',
					'icon' => 'list',
					'summary' => 'Повторяемый список из одного значения в строке.',
					'default_label' => 'Строки по умолчанию',
					'default_hint' => 'Одна запись на строку.',
					'default_kind' => 'lines',
					'entity_fields' => array('items[].value'),
				),
				'multi_list_triple' => array(
					'title' => 'Трёхколоночный список',
					'icon' => 'columns-3',
					'summary' => 'Повторяемый список из трёх частей, legacy использует разделитель |.',
					'default_label' => 'Строки по умолчанию',
					'default_hint' => 'Формат строки: колонка1|колонка2|колонка3.',
					'default_kind' => 'pipe_list',
					'entity_fields' => array('items[].param', 'items[].value', 'items[].value2'),
				),
				'multi_links' => array(
					'title' => 'Список ссылок',
					'icon' => 'link-plus',
					'summary' => 'Повторяемые ссылки с подписью.',
					'default_label' => 'Ссылки по умолчанию',
					'default_hint' => 'Формат строки: URL|Название.',
					'default_kind' => 'key_value',
					'entity_fields' => array('items[].param', 'items[].value'),
				),
			);
		}
	}
