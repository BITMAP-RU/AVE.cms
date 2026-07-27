<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/DocFiles.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\FieldContext;
	use App\Content\Fields\MediaFieldValue;

	/** Файлы для скачивания (AVE doc_files). Legacy value = serialize([путь|название]). */
	class DocFiles extends ImageMulti
	{
		public function code()
		{
			return 'doc_files';
		}

		public function name()
		{
			return 'Файлы для скачивания';
		}

		public function renderView(FieldContext $ctx)
		{
			$items = MediaFieldValue::imageMulti($ctx->value);
			if (empty($items)) {
				return '';
			}

			$html = '<ul class="doc-files">';
			foreach ($items as $item) {
				$title = $item['description'] !== '' ? $item['description'] : basename($item['url']);
				$html .= '<li><a href="' . $this->attr($item['url']) . '" download>' . $this->e($title) . '</a></li>';
			}

			return $html . '</ul>';
		}

		public function save(FieldContext $ctx)
		{
			$items = is_array($ctx->value) ? $ctx->value : $this->decode($ctx->value);
			$out = array();
			foreach ($items as $item) {
				if (!is_array($item)) { continue; }
				$url = isset($item['url']) ? trim((string) $item['url']) : '';
				if ($url === '') { continue; }
				$out[] = array(
					'url' => $url,
					'description' => isset($item['description']) ? (string) $item['description'] : (isset($item['descr']) ? (string) $item['descr'] : ''),
					'name' => isset($item['name']) ? (string) $item['name'] : '',
				);
			}

			return $this->encodeJson($out);
		}
	}
