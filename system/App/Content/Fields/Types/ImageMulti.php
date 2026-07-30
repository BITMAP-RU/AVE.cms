<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ImageMulti.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\SerialFieldType;
	use App\Content\Fields\DocumentMediaFieldTrait;
	use App\Content\Fields\DocumentMediaFieldType;
	use App\Content\Fields\FieldContext;
	use App\Content\Fields\MediaFieldValue;

	/**
	 * Несколько изображений (AVE image_multi). Legacy value = serialize([путь|описание]).
	 *
	 * Панель управления дополняет тип галереей, загрузкой, медиапикером и DnD-сортировкой.
	 */
	class ImageMulti extends SerialFieldType implements DocumentMediaFieldType
	{
		use DocumentMediaFieldTrait;

		public function code()
		{
			return 'image_multi';
		}

		public function name()
		{
			return 'Изображения (галерея)';
		}

		public function isFile()
		{
			return true;
		}

		public function renderEdit(FieldContext $ctx)
		{
			$lines = array();
			foreach (MediaFieldValue::imageMulti($ctx->value) as $item) {
				$lines[] = $item['url'] . ($item['description'] !== '' ? '|' . $item['description'] : '');
			}

			return '<textarea class="textarea mono" rows="6" placeholder="/uploads/image.jpg|Описание — по изображению на строку" name="'
				. $this->attr($ctx->inputName()) . '">' . $this->e(implode("\n", $lines)) . '</textarea>';
		}

		public function save(FieldContext $ctx)
		{
			$items = array();
			if (is_array($ctx->value)) {
				foreach ($ctx->value as $item) {
					if (is_array($item)) {
						$url = isset($item['url']) ? trim((string) $item['url']) : '';
						$description = isset($item['description']) ? trim((string) $item['description']) : '';
						if ($url !== '') {
							$items[] = array('url' => $url, 'description' => $description);
						}
					} else {
						$image = MediaFieldValue::imageSingle((string) $item);
						if ($image['url'] !== '') {
							$items[] = $image;
						}
					}
				}
			} else {
				foreach (preg_split('/\r\n|\r|\n/', (string) $ctx->value) as $line) {
					$image = MediaFieldValue::imageSingle($line);
					if ($image['url'] !== '') {
						$items[] = $image;
					}
				}
			}

			return $this->encodeJson($items);
		}

		public function renderView(FieldContext $ctx)
		{
			$items = MediaFieldValue::imageMulti($ctx->value);
			if (empty($items)) {
				return '';
			}

			$html = '<div style="display:flex;gap:8px;flex-wrap:wrap">';
			foreach ($items as $item) {
				$html .= '<figure style="margin:0;max-width:120px">'
					. '<img src="' . $this->attr($item['url']) . '" alt="' . $this->attr($item['description']) . '" style="max-width:120px;border-radius:6px">'
					. ($item['description'] !== '' ? '<figcaption>' . $this->e($item['description']) . '</figcaption>' : '')
					. '</figure>';
			}

			return $html . '</div>';
		}
	}
