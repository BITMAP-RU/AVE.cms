<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/Link.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;
	use App\Helpers\Json;

	/** Ссылка (AVE link). Legacy value = url|подпись. */
	class Link extends AbstractFieldType
	{
		public function code()
		{
			return 'link';
		}

		public function name()
		{
			return 'Ссылка';
		}

		protected function parse($value)
		{
			if (is_array($value)) {
				return array(
					'url' => isset($value['url']) ? trim((string) $value['url']) : '',
					'title' => isset($value['title']) ? trim((string) $value['title']) : '',
				);
			}

			$value = trim((string) $value);
			if ($value !== '' && ($value[0] === '{' || $value[0] === '[')) {
				$j = Json::toArray($value);
				if (!empty($j)) {
					return array(
						'url' => isset($j['url']) ? (string) $j['url'] : '',
						'title' => isset($j['title']) ? (string) $j['title'] : '',
					);
				}
			}

			$parts = explode('|', $value);
			return array(
				'url' => isset($parts[0]) ? trim($parts[0]) : '',
				'title' => isset($parts[1]) ? trim(implode('|', array_slice($parts, 1))) : '',
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$link = $this->parse($ctx->value);
			$name = $this->attr($ctx->inputName());
			return '<div class="documents-link-field">'
				. '<input class="input mono" type="text" placeholder="https://…" name="' . $name . '[url]" value="' . $this->attr($link['url']) . '">'
				. '<input class="input" type="text" placeholder="Подпись" name="' . $name . '[title]" value="' . $this->attr($link['title']) . '">'
				. '</div>';
		}

		public function save(FieldContext $ctx)
		{
			$link = $this->parse($ctx->value);
			return $link['url'] === '' ? '' : Json::encode($link, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		public function renderView(FieldContext $ctx)
		{
			$link = $this->parse($ctx->value);
			if ($link['url'] === '') {
				return '';
			}

			$title = $link['title'] !== '' ? $link['title'] : $link['url'];
			return '<a href="' . $this->attr($link['url']) . '">' . $this->e($title) . '</a>';
		}
	}
