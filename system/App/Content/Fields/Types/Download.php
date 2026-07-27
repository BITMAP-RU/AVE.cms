<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Download.php
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

	/** Файл для скачивания (AVE download). Legacy value = путь|название ссылки. */
	class Download extends AbstractFieldType implements DocumentMediaFieldType
	{
		use DocumentMediaFieldTrait;

		public function code()
		{
			return 'download';
		}

		public function name()
		{
			return 'Файл для скачивания';
		}

		public function isFile()
		{
			return true;
		}

		public function renderEdit(FieldContext $ctx)
		{
			$file = MediaFieldValue::download($ctx->value);
			$name = $this->attr($ctx->inputName());
			return '<div class="documents-media-single" data-document-media-single data-document-picker-type="file">'
				. '<input class="input mono" type="text" placeholder="/uploads/file.pdf" name="' . $name . '[url]" value="'
				. $this->attr($file['url']) . '" data-media-key="url" data-document-media-url>'
				. '<input class="input" type="text" placeholder="Название ссылки" name="' . $name . '[title]" value="'
				. $this->attr($file['title']) . '" data-media-key="title">'
				. '</div>';
		}

		public function save(FieldContext $ctx)
		{
			if (is_array($ctx->value)) {
				$url = isset($ctx->value['url']) ? trim((string) $ctx->value['url']) : '';
				$title = isset($ctx->value['title']) ? trim((string) $ctx->value['title']) : '';
				return $url !== '' ? Json::encode(array('url' => $url, 'title' => $title), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
			}

			$file = MediaFieldValue::download($ctx->value);
			return $file['url'] !== '' ? Json::encode($file, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
		}

		public function renderView(FieldContext $ctx)
		{
			$file = MediaFieldValue::download($ctx->value);
			if ($file['url'] === '') {
				return '';
			}

			$title = $file['title'] !== '' ? $file['title'] : basename($file['url']);
			return '<a href="' . $this->attr($file['url']) . '" download>' . $this->e($title) . '</a>';
		}

		/** Request templates compose their own links, so they need the URL, not a nested anchor. */
		public function renderFilter(FieldContext $ctx)
		{
			$file = MediaFieldValue::download($ctx->value);
			return $file['url'] !== '' ? $this->attr($file['url']) : '';
		}
	}
