<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldSettingsForm.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Helpers\Str;
	use App\Helpers\Json;
	use App\Content\Directories\DirectoryRepository;

	/**
	 * Форма настроек поля из его settingsSchema() — конструктор рубрики.
	 *
	 * render() строит HTML-контролы (имена rubric_field_settings[<key>]),
	 * collect() симметрично нормализует POST обратно в массив настроек для JSON.
	 * Дескриптор схемы: ['key','type','label','options'?,'default'?,'multiple'?].
	 * Поддержаны type: text|textarea|int|number|bool|select|list|map|rubric|directory.
	 */
	class FieldSettingsForm
	{
		/** Есть ли у типа настраиваемые параметры/правила. */
		public static function has($type)
		{
			return !empty(self::schema($type)) || !empty(self::validationSchemaOf($type));
		}

		public static function render($type, array $settings = array())
		{
			$schema = self::schema($type);
			$validation = self::validationSchemaOf($type);
			if (empty($schema) && empty($validation)) {
				return '';
			}

			$html = '';
			if (!empty($schema)) {
				$html .= '<div class="form-grid ax-field-settings" data-field-settings>';
				foreach ($schema as $desc) {
					$html .= self::field($desc, $settings);
				}

				$html .= '</div>';
			}

			if (!empty($validation)) {
				$html .= '<div class="ax-field-validation-head">Валидация</div>'
					. '<div class="form-grid ax-field-settings" data-field-validation>';
				foreach ($validation as $desc) {
					$html .= self::field($desc, $settings);
				}

				$html .= '</div>';
			}

			return $html;
		}

		protected static function field(array $desc, array $settings)
		{
			$key = isset($desc['key']) ? (string) $desc['key'] : '';
			if ($key === '') {
				return '';
			}

			$value = array_key_exists($key, $settings) ? $settings[$key] : (isset($desc['default']) ? $desc['default'] : '');
			// Числовые правила (мин/макс, длина, количество) — половина ряда, встают
			// парами в одну линию; булев тумблер и текст — на всю ширину.
			$col = (isset($desc['type']) && in_array($desc['type'], array('int', 'number'), true)) ? 'col-6' : 'col-12';
			$hint = (isset($desc['hint']) && $desc['hint'] !== '') ? '<span class="field-hint">' . self::e($desc['hint']) . '</span>' : '';
			$tag = isset($desc['type']) && $desc['type'] === 'map' ? 'div' : 'label';
			return '<' . $tag . ' class="field ' . $col . '" data-field-setting-key="' . self::attr($key) . '"><span class="field-label">' . self::e(isset($desc['label']) ? $desc['label'] : $key) . '</span>'
				. self::control($desc, $key, $value) . $hint . '</' . $tag . '>';
		}

		/** POST rubric_field_settings[...] + тип → нормализованный массив настроек. */
		public static function collect($type, $raw)
		{
			$raw = is_array($raw) ? $raw : array();
			$descriptors = array_merge(self::schema($type), self::validationSchemaOf($type));
			$out = array();
			foreach ($descriptors as $desc) {
				$key = isset($desc['key']) ? (string) $desc['key'] : '';
				if ($key === '') {
					continue;
				}

				$kind = isset($desc['type']) ? (string) $desc['type'] : 'text';
				$value = array_key_exists($key, $raw) ? $raw[$key] : null;
				$out[$key] = self::normalize($kind, $value);
			}

			// Пустые необязательные правила не храним: значения по умолчанию
			// определяет schema самого native-типа.
			foreach ($out as $k => $v) {
				if ($v === '' || $v === array() || $v === null || $v === false) {
					unset($out[$k]);
				}
			}

			if (isset($out['option_source']) && $out['option_source'] === 'directory'
				&& empty($out['directory_id'])) {
				$out['option_source'] = 'local';
			}

			return $out;
		}

		/** Ключи настроек и правил, которыми владеет конкретный тип поля. */
		public static function keys($type)
		{
			$keys = array();
			foreach (array_merge(self::schema($type), self::validationSchemaOf($type)) as $desc) {
				$key = isset($desc['key']) ? (string) $desc['key'] : '';
				if ($key !== '') {
					$keys[] = $key;
				}
			}

			return array_values(array_unique($keys));
		}

		/** Готовый JSON для колонки rubric_field_settings (или '' если пусто). */
		public static function toJson($type, $raw)
		{
			$settings = self::collect($type, $raw);
			return empty($settings) ? '' : Json::encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		protected static function schema($type)
		{
			$field = FieldRegistry::get((string) $type);
			return $field ? (array) $field->settingsSchema() : array();
		}

		protected static function validationSchemaOf($type)
		{
			$field = FieldRegistry::get((string) $type);
			return $field ? (array) $field->validationSchema() : array();
		}

		protected static function control(array $desc, $key, $value)
		{
			$kind = isset($desc['type']) ? (string) $desc['type'] : 'text';
			$name = 'rubric_field_settings[' . self::attr($key) . ']';

			switch ($kind) {
				case 'textarea':
					return '<textarea class="textarea mono" rows="' . max(3, (int) (isset($desc['rows']) ? $desc['rows'] : 5)) . '" name="' . $name . '">'
						. self::e(is_scalar($value) ? $value : '') . '</textarea>';
				case 'int':
				case 'number':
					return '<input class="input" type="number" name="' . $name . '" value="' . self::attr(is_scalar($value) ? $value : '') . '">';
				case 'bool':
					$checked = (!empty($value) && $value !== '0') ? ' checked' : '';
					return '<input type="hidden" name="' . $name . '" value="0">'
						. '<label class="switch"><input type="checkbox" name="' . $name . '" value="1"' . $checked . '><span class="switch-track"></span></label>';
				case 'select':
					$options = isset($desc['options']) && is_array($desc['options']) ? $desc['options'] : array();
					$html = '<select class="select" name="' . $name . '">';
					foreach ($options as $optKey => $optVal) {
						$optValue = is_int($optKey) ? $optVal : $optKey;
						$sel = ((string) $optValue === (string) $value) ? ' selected' : '';
						$html .= '<option value="' . self::attr($optValue) . '"' . $sel . '>' . self::e($optVal) . '</option>';
					}

					return $html . '</select>';
				case 'list':
					$lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
					return '<textarea class="textarea" rows="4" placeholder="один вариант на строку" name="' . $name . '">'
						. self::e(implode("\n", (array) $lines)) . '</textarea>';
				case 'map':
					$rows = self::mapRows($value);
					$lines = array();
					$html = '<div class="ax-settings-map" data-settings-map>'
						. '<div class="ax-settings-map-head" aria-hidden="true"><span>Ключ</span><span>Подпись</span><span></span></div>'
						. '<div class="ax-settings-map-rows" data-settings-map-rows>';
					foreach ($rows as $row) {
						$lines[] = $row['key'] . '=' . $row['label'];
						$html .= self::mapRowControl($row['key'], $row['label']);
					}

					if (empty($rows)) {
						$html .= self::mapRowControl('', '');
					}

					return $html . '</div>'
						. '<textarea class="ax-settings-map-storage" name="' . $name . '" data-settings-map-storage aria-hidden="true" tabindex="-1">'
						. self::e(implode("\n", $lines)) . '</textarea>'
						. '<button class="btn btn-secondary btn-sm ax-settings-map-add" type="button" data-settings-map-add>'
						. '<i class="ti ti-plus"></i>Добавить значение</button></div>';
				case 'rubric':
					$val = is_array($value) ? implode(',', $value) : (string) $value;
					return '<input class="input mono" type="text" placeholder="ID рубрики (через запятую)" name="' . $name . '" value="' . self::attr($val) . '">';
				case 'directory':
					try {
						$options = DirectoryRepository::choices(true);
					} catch (\Throwable $e) {
						$options = array();
					}

					$html = '<select class="select" name="' . $name . '"><option value="">— выберите справочник —</option>';
					foreach ($options as $directoryId => $directoryName) {
						$sel = ((string) $directoryId === (string) $value) ? ' selected' : '';
						$html .= '<option value="' . self::attr($directoryId) . '"' . $sel . '>' . self::e($directoryName) . '</option>';
					}

					return $html . '</select>';
				default:
					return '<input class="input" type="text" name="' . $name . '" value="' . self::attr(is_scalar($value) ? $value : '') . '">';
			}
		}

		protected static function mapRows($value)
		{
			$rows = array();
			if (is_array($value)) {
				foreach ($value as $key => $label) {
					if (is_array($label)) {
						$mapKey = isset($label['value']) ? $label['value'] : (isset($label['key']) ? $label['key'] : $key);
						$mapLabel = isset($label['label']) ? $label['label'] : (isset($label['title']) ? $label['title'] : '');
					} else {
						$mapKey = $key;
						$mapLabel = $label;
					}

					if ((string) $mapKey !== '' || (string) $mapLabel !== '') {
						$rows[] = array('key' => (string) $mapKey, 'label' => (string) $mapLabel);
					}
				}

				return $rows;
			}

			foreach (preg_split('/\r\n|\r|\n/', (string) $value) as $line) {
				$line = trim((string) $line);
				if ($line === '') {
					continue;
				}

				if (strpos($line, '=') !== false) {
					list($key, $label) = explode('=', $line, 2);
				} else {
					$key = $line;
					$label = $line;
				}

				$rows[] = array('key' => trim((string) $key), 'label' => trim((string) $label));
			}

			return $rows;
		}

		protected static function mapRowControl($key, $label)
		{
			$displayLabel = preg_replace('/\|\s*$/u', '', (string) $label);
			return '<div class="ax-settings-map-row" data-settings-map-row>'
				. '<input class="input mono" type="text" value="' . self::attr($key) . '" placeholder="Например, 0" data-settings-map-key>'
				. '<input class="input" type="text" value="' . self::attr($displayLabel) . '" placeholder="Название значения" data-settings-map-label>'
				. '<button class="btn btn-ghost btn-icon btn-sm ax-settings-map-remove" type="button" data-settings-map-remove data-tooltip-left="Удалить" aria-label="Удалить значение"><i class="ti ti-trash"></i></button>'
				. '</div>';
		}

		protected static function normalize($kind, $value)
		{
			switch ($kind) {
				case 'int':
				case 'number':
					return ($value === '' || $value === null) ? '' : (0 + $value);
				case 'directory':
					return max(0, (int) $value);
				case 'bool':
					return (!empty($value) && $value !== '0');
				case 'list':
					$lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
					$out = array();
					foreach ((array) $lines as $line) {
						$line = trim((string) $line);
						if ($line !== '') {
							$out[] = $line;
						}
					}

					return array_values(array_unique($out));
				case 'map':
					$lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
					$out = array();
					foreach ((array) $lines as $index => $line) {
						if (is_array($line)) {
							$key = isset($line['value']) ? $line['value'] : (isset($line['key']) ? $line['key'] : $index);
							$label = isset($line['label']) ? $line['label'] : (isset($line['title']) ? $line['title'] : '');
							if ((string) $key !== '' && (string) $label !== '') {
								$out[] = array('value' => (string) $key, 'label' => trim((string) $label));
							}

							continue;
						}

						$line = trim((string) $line);
						if ($line === '') {
							continue;
						}

						if (strpos($line, '=') !== false) {
							list($k, $label) = explode('=', $line, 2);
							$k = trim($k);
							$label = trim($label);
						} else {
							$k = $line;
							$label = $line;
						}

						if ($k !== '' && $label !== '') {
							$out[] = array('value' => $k, 'label' => $label);
						}
					}

					return $out;
				case 'rubric':
					$ids = array();
					foreach (is_array($value) ? $value : explode(',', (string) $value) as $r) {
						$r = (int) trim((string) $r);
						if ($r > 0) {
							$ids[] = $r;
						}
					}

					return array_values(array_unique($ids));
				default:
					return trim((string) $value);
			}
		}

		protected static function e($value)
		{
			return Str::escape((string) $value);
		}

		protected static function attr($value)
		{
			return Str::escape((string) $value);
		}
	}
