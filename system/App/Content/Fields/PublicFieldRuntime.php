<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/PublicFieldRuntime.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Frontend\Media\ThumbnailUrl;
	use App\Common\Lifecycle;

	/**
	 * Native public field runtime.
	 *
	 * Значения читаются в legacy-формате (пайп/serialize) — renderView типов их
	 * понимает, поэтому вывод не требует миграции данных.
	 */
	class PublicFieldRuntime
	{
		public static function configure($mode)
		{
		}

		public static function mode()
		{
			return 'native';
		}

		/**
		 * Отрендерить значение поля для публичной страницы (режим AVE 'doc').
		 * Возвращает HTML нативного обработчика поля.
		 */
		public static function renderDoc($type, $value, array $fieldRow = array(), $templateId = null)
		{
			$type = (string) $type;
			if (!FieldRegistry::has($type)) {
				return '';
			}

			$rendering = self::rendering($type, 'document', $value, $fieldRow, $templateId);
			if ($rendering->cancelled()) { return (string) $rendering->result(); }
			$value = $rendering->value('value', $value);
			$fieldRow = $rendering->value('field', $fieldRow);

			// Паритет с legacy: непустой per-field шаблон (rubric_field_template) с
			// tpl_field_empty=0 применяется как есть — [tag:parametr:N] заменяется
			// на N-ю часть значения по разделителю «|». Иначе — дефолтный renderView.
			$tpl = isset($fieldRow['rubric_field_template']) ? trim((string) $fieldRow['rubric_field_template']) : '';
			$tplEmpty = !empty($fieldRow['tpl_field_empty']);
			if ($tpl !== '' && !$tplEmpty) {
				return self::rendered($type, 'document', $value, $fieldRow, $templateId, self::applyTemplate($tpl, $value, $fieldRow), 'database_template');
			}

			$fileTemplate = PublicFieldTemplateResolver::render($type, 'doc', $value, $fieldRow, $templateId);
			if ($fileTemplate !== null) {
				return self::rendered($type, 'document', $value, $fieldRow, $templateId, $fileTemplate, 'file_template');
			}

			$definition = $fieldRow;
			$definition['rubric_field_type'] = $type;
			$ctx = new FieldContext($value, $definition, 'view');

			return self::rendered($type, 'document', $value, $fieldRow, $templateId, (string) FieldRegistry::get($type)->renderView($ctx), 'field_type');
		}

		/** Render a field inside a legacy request/teaser template. */
		public static function renderRequest($type, $value, array $fieldRow = array(), $templateId = null)
		{
			$type = (string) $type;
			if (!FieldRegistry::has($type)) { return ''; }
			$rendering = self::rendering($type, 'request', $value, $fieldRow, $templateId);
			if ($rendering->cancelled()) { return (string) $rendering->result(); }
			$value = $rendering->value('value', $value);
			$fieldRow = $rendering->value('field', $fieldRow);

			$tpl = isset($fieldRow['rubric_field_template_request']) ? trim((string) $fieldRow['rubric_field_template_request']) : '';
			$tplEmpty = !empty($fieldRow['tpl_req_empty']);
			if ($tpl !== '' && !$tplEmpty) {
				return self::rendered($type, 'request', $value, $fieldRow, $templateId, self::applyTemplate($tpl, $value, $fieldRow), 'database_template');
			}

			$fileTemplate = PublicFieldTemplateResolver::render($type, 'req', $value, $fieldRow, $templateId);
			if ($fileTemplate !== null) {
				return self::rendered($type, 'request', $value, $fieldRow, $templateId, $fileTemplate, 'file_template');
			}

			$definition = $fieldRow;
			$definition['rubric_field_type'] = $type;
			$ctx = new FieldContext($value, $definition, 'filter');
			return self::rendered($type, 'request', $value, $fieldRow, $templateId, self::renderRequestDefault($type, $ctx), 'field_type');
		}

		protected static function rendering($type, $mode, $value, array $fieldRow, $templateId)
		{
			return Lifecycle::event('content.field.rendering', 'field', 'rendering', isset($fieldRow['Id']) ? (int) $fieldRow['Id'] : (isset($fieldRow['rubric_field_id']) ? (int) $fieldRow['rubric_field_id'] : 0), array(
				'type' => (string) $type,
				'mode' => (string) $mode,
				'value' => $value,
				'field' => $fieldRow,
				'template_id' => $templateId,
			), null, array(), 'public_field_runtime');
		}

		protected static function rendered($type, $mode, $value, array $fieldRow, $templateId, $output, $renderer)
		{
			$event = Lifecycle::event('content.field.rendered', 'field', 'rendered', isset($fieldRow['Id']) ? (int) $fieldRow['Id'] : (isset($fieldRow['rubric_field_id']) ? (int) $fieldRow['rubric_field_id'] : 0), array(
				'type' => (string) $type,
				'mode' => (string) $mode,
				'value' => $value,
				'field' => $fieldRow,
				'template_id' => $templateId,
				'renderer' => (string) $renderer,
			), (string) $output, array(), 'public_field_runtime');
			return (string) $event->result();
		}

		protected static function renderRequestDefault($type, FieldContext $ctx)
		{
			$plugin = FieldRegistry::get($type);
			$fieldId = $ctx->fieldId();
			// Legacy request mode emitted media collections only through an
			// explicit per-field request template. Generic document galleries
			// must not be injected into product cards and other request items.
			if (in_array($type, array('image_multi', 'image_mega'), true)) {
				return '';
			}

			if ($type === 'image_single') {
				$image = MediaFieldValue::imageSingle($ctx->value);
				if ($image['url'] === '') { return ''; }
				$url = htmlspecialchars($image['url'], ENT_QUOTES, 'UTF-8');
				$alt = htmlspecialchars($image['description'], ENT_QUOTES, 'UTF-8');
				if ($fieldId === 159) {
					$webp = htmlspecialchars(preg_replace('/\.[^.\/]+$/', '.webp', $image['url']), ENT_QUOTES, 'UTF-8');
					return "<picture>\n\t<source data-srcset=\"" . $webp . "\" type=\"image/webp\">\n\t<img class=\"index_main_slider_slide_img lazyload --lazy\" src=\"/uploads/slider/left_default.png\" data-src=\"" . $url . "\" alt=\"" . $alt . "\" title=\"" . $alt . "\">\n</picture>";
				}

				$class = $fieldId === 14 ? ' class="--lazy"' : ($fieldId === 142 ? ' class="where_image_img"' : '');
				return '<img' . $class . ' src="' . $url . '" alt="' . $alt . '" title="' . $alt . '">';
			}

			$html = (string) $plugin->renderFilter($ctx);
			if (in_array($type, array('teasers', 'analoque', 'doc_from_rub_all', 'doc_from_rub_check', 'doc_from_rub_multi', 'doc_from_rub_search'), true)) {
				return str_replace('<ul class="doc-relation-list">', '<ul>', $html);
			}

			return $html;
		}

		/** Legacy per-field шаблон: [tag:parametr:N] → N-я часть значения по «|». */
		protected static function applyTemplate($tpl, $value, array $fieldRow = array())
		{
			$parts = FieldValueCodec::templateParts($value);
			$empty = trim((string) $value) === '' || empty($parts);
			// Условные блоки с необязательной веткой [tag:else].
			$tpl = preg_replace_callback(
				'/\[tag:if_(not)?empty\](.*?)\[\/tag:if_\1empty\]/is',
				function ($m) use ($empty) {
					$cond = !empty($m[1]) ? !$empty : $empty;
					$branches = preg_split('/\[tag:else\]/i', $m[2], 2);
					return $cond ? $branches[0] : (isset($branches[1]) ? $branches[1] : '');
				},
				(string) $tpl
			);
			// Метаданные поля: название, алиас, значение по умолчанию.
			$tpl = str_ireplace(
				array('[tag:label]', '[tag:alias]', '[tag:default]'),
				array(
					(string) (isset($fieldRow['rubric_field_title']) ? $fieldRow['rubric_field_title'] : ''),
					(string) (isset($fieldRow['rubric_field_alias']) ? $fieldRow['rubric_field_alias'] : ''),
					(string) (isset($fieldRow['rubric_field_default']) ? $fieldRow['rubric_field_default'] : ''),
				),
				$tpl
			);
			// Значение поля целиком (аналог legacy {$field_value}).
			$whole = is_array($value) ? implode(' ', $parts) : (string) $value;
			$tpl = str_ireplace('[tag:value]', $whole, $tpl);
			// N-я часть значения (разделитель «|», с нуля).
			$tpl = preg_replace_callback(
				'/\[tag:parametr:(\d+)\]/i',
				function ($m) use ($parts) {
					$i = (int) $m[1];
					return isset($parts[$i]) ? $parts[$i] : '';
				},
				$tpl
			);
			$tpl = preg_replace_callback('/\[tag:([rcf]\d+x\d+r?):(.+?)]/', array(ThumbnailUrl::class, 'fromTag'), $tpl);
			// Прямой <img> из пути: [tag:img:/uploads/x.jpg] (можно вложить [tag:value]).
			$tpl = preg_replace_callback(
				'/\[tag:img:([^\]]+)\]/i',
				function ($m) {
					$src = trim($m[1]);
					return $src !== '' ? '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="">' : '';
				},
				$tpl
			);
			return $tpl;
		}
	}
