<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ImageMega.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;
	use App\Content\Fields\MediaFieldValue;

	/** Галерея «мега» (AVE image_mega). Legacy value = serialize([путь|title|description|link]). */
	class ImageMega extends ImageMulti
	{
		public function code()
		{
			return 'image_mega';
		}

		public function name()
		{
			return 'Изображения (мега)';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$lines = array();
			foreach (MediaFieldValue::imageMega($ctx->value) as $item) {
				$lines[] = $item['url'] . '|' . $item['title'] . '|' . $item['description'] . ($item['link'] !== '' ? '|' . $item['link'] : '');
			}

			return '<textarea class="textarea mono" rows="7" placeholder="/uploads/image.jpg|Заголовок|Описание|Ссылка — по изображению на строку" name="'
				. $this->attr($ctx->inputName()) . '">' . $this->e(implode("\n", $lines)) . '</textarea>';
		}

		public function save(FieldContext $ctx)
		{
			$items = array();
			if (is_array($ctx->value)) {
				foreach ($ctx->value as $item) {
					if (is_array($item)) {
						$url = isset($item['url']) ? trim((string) $item['url']) : '';
						if ($url !== '') {
							$items[] = array(
								'url' => $url,
								'title' => isset($item['title']) ? trim((string) $item['title']) : '',
								'description' => isset($item['description']) ? trim((string) $item['description']) : '',
								'link' => isset($item['link']) ? trim((string) $item['link']) : '',
							);
						}
					} else {
						foreach (MediaFieldValue::imageMega((string) $item) as $image) {
							$items[] = $image;
						}
					}
				}
			} else {
				foreach (preg_split('/\r\n|\r|\n/', (string) $ctx->value) as $line) {
					foreach (MediaFieldValue::imageMega($line) as $image) {
						$items[] = $image;
					}
				}
			}

			return $this->encodeJson($items);
		}

		public function renderView(FieldContext $ctx)
		{
			$items = MediaFieldValue::imageMega($ctx->value);
			if (empty($items)) {
				return '';
			}

			$html = '<div style="display:flex;gap:8px;flex-wrap:wrap">';
			foreach ($items as $item) {
				$img = '<img src="' . $this->attr($item['url']) . '" alt="' . $this->attr($item['title']) . '" style="max-width:120px;border-radius:6px">';
				if ($item['link'] !== '') {
					$img = '<a href="' . $this->attr($item['link']) . '">' . $img . '</a>';
				}

				$html .= '<figure style="margin:0;max-width:160px">' . $img
					. ($item['title'] !== '' ? '<figcaption><b>' . $this->e($item['title']) . '</b></figcaption>' : '')
					. ($item['description'] !== '' ? '<div>' . $this->e($item['description']) . '</div>' : '')
					. '</figure>';
			}

			return $html . '</div>';
		}
	}
