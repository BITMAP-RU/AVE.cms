<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/MultiLinks.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\SerialFieldType;
	use App\Content\Fields\FieldContext;

	/** Набор ссылок (AVE multi_links) — сериализованный массив. */
	class MultiLinks extends SerialFieldType
	{
		public function code()
		{
			return 'multi_links';
		}

		public function name()
		{
			return 'Ссылки';
		}

		public function renderEdit(FieldContext $ctx)
		{
			$items = $this->linesFromValue($ctx->value);
			return '<textarea class="textarea" rows="4" placeholder="по ссылке на строку" name="'
				. $this->attr($ctx->inputName()) . '">' . $this->e(implode("\n", $items)) . '</textarea>';
		}

		public function save(FieldContext $ctx)
		{
			return $this->encodeJson($this->linesFromValue($ctx->value));
		}

		public function renderView(FieldContext $ctx)
		{
			$items = $this->decode($ctx->value);
			if (empty($items)) {
				return '';
			}

			$html = '<ul class="field-links">';
			foreach ($items as $item) {
				if (is_array($item)) {
					$label = isset($item['param']) ? (string) $item['param'] : (isset($item[0]) ? (string) $item[0] : '');
					$url = isset($item['value']) ? (string) $item['value'] : (isset($item[1]) ? (string) $item[1] : '');
				} else {
					$parts = explode('|', (string) $item, 2);
					$label = isset($parts[0]) ? trim($parts[0]) : '';
					$url = isset($parts[1]) ? trim($parts[1]) : '';
				}

				if ($label === '' && $url === '') {
					continue;
				}

				$caption = $label !== '' ? $label : $url;
				$html .= '<li>' . ($url !== ''
					? '<a href="' . $this->attr($url) . '">' . $this->e($caption) . '</a>'
					: $this->e($caption)) . '</li>';
			}

			return $html . '</ul>';
		}
	}
