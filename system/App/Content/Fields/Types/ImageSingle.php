<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ImageSingle.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\DocumentMediaFieldTrait;
	use App\Content\Fields\DocumentMediaFieldType;
	use App\Content\Fields\FieldContext;
	use App\Content\Fields\MediaFieldValue;
	use App\Helpers\Json;

	/**
	 * Одно изображение (AVE image_single). Legacy value = путь|описание.
	 *
	 * Богатый media-редактор с превью, загрузкой и пикером подключается в adminx.
	 */
	class ImageSingle extends AbstractFieldType implements DocumentMediaFieldType
	{
		use DocumentMediaFieldTrait;

		public function code()
		{
			return 'image_single';
		}

		public function name()
		{
			return 'Изображение';
		}

		public function isFile()
		{
			return true;
		}

		public function renderEdit(FieldContext $ctx)
		{
			$image = MediaFieldValue::imageSingle($ctx->value);
			$name = $this->attr($ctx->inputName());
			$preview = $image['url'] !== ''
				? '<img src="' . $this->attr($image['url']) . '" alt="" style="max-width:160px;border-radius:6px">'
				: '';
			return '<div class="documents-media-single" data-document-media-single data-document-picker-type="image">'
				. $preview
				. '<input class="input mono" type="text" placeholder="/uploads/image.jpg" name="' . $name . '[url]" value="'
				. $this->attr($image['url']) . '" data-media-key="url" data-document-media-url>'
				. '<input class="input" type="text" placeholder="Описание" name="' . $name . '[description]" value="'
				. $this->attr($image['description']) . '" data-media-key="description">'
				. '</div>';
		}

		public function save(FieldContext $ctx)
		{
			if (is_array($ctx->value)) {
				$url = isset($ctx->value['url']) ? trim((string) $ctx->value['url']) : '';
				$description = isset($ctx->value['description']) ? trim((string) $ctx->value['description']) : '';
				return $url !== '' ? Json::encode(array('url' => $url, 'description' => $description), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
			}

			$image = MediaFieldValue::imageSingle($ctx->value);
			return $image['url'] !== '' ? Json::encode($image, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
		}

		public function renderView(FieldContext $ctx)
		{
			$image = MediaFieldValue::imageSingle($ctx->value);
			if ($image['url'] === '') {
				return '';
			}

			return '<img class="field-image-single" src="' . $this->attr($image['url']) . '" alt="'
				. $this->attr($image['description']) . '" style="display:block;width:100%;max-width:100%;height:auto">';
		}
	}
