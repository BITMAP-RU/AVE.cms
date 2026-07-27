<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/Types/ContactValue.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields\Types;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Content\Fields\AbstractFieldType;
	use App\Content\Fields\FieldContext;

	/** Email, phone or URL with mode-aware validation and public linking. */
	class ContactValue extends AbstractFieldType
	{
		public function code()
		{
			return 'contact';
		}

		public function name()
		{
			return 'Контакт';
		}

		public function settingsSchema()
		{
			return array(
				array(
					'key' => 'mode',
					'type' => 'select',
					'label' => 'Тип контакта',
					'options' => array('email' => 'Email', 'phone' => 'Телефон', 'url' => 'URL / ссылка'),
					'default' => 'email',
				),
				array(
					'key' => 'clickable',
					'type' => 'bool',
					'label' => 'Делать ссылкой',
					'default' => true,
				),
				array(
					'key' => 'label',
					'type' => 'text',
					'label' => 'Подпись ссылки',
					'hint' => 'Если пусто, на сайте показывается само значение.',
				),
				array(
					'key' => 'new_window',
					'type' => 'bool',
					'label' => 'URL в новом окне',
					'hint' => 'Используется только для режима URL.',
				),
			);
		}

		public function renderEdit(FieldContext $ctx)
		{
			$mode = $this->mode($ctx);
			$type = $mode === 'phone' ? 'tel' : ($mode === 'url' ? 'url' : 'email');
			$placeholder = $mode === 'phone' ? '+7 999 000-00-00' : ($mode === 'url' ? 'https://example.com' : 'name@example.com');
			return '<input type="' . $type . '" class="input" autocomplete="off" placeholder="' . $this->attr($placeholder)
				. '" name="' . $this->attr($ctx->inputName()) . '" value="' . $this->attr($this->value($ctx)) . '">';
		}

		public function renderView(FieldContext $ctx)
		{
			$value = $this->value($ctx);
			if ($value === '' || !$this->valid($ctx)) {
				return '';
			}

			$label = trim((string) $ctx->setting('label', ''));
			$label = $label !== '' ? $label : $value;
			if (!$ctx->setting('clickable', true)) {
				return $this->e($label);
			}

			$mode = $this->mode($ctx);
			$href = $value;
			$attrs = '';
			if ($mode === 'email') {
				$href = 'mailto:' . $value;
			} elseif ($mode === 'phone') {
				$href = 'tel:' . self::phoneHref($value);
			} elseif ($ctx->setting('new_window', false)) {
				$attrs = ' target="_blank" rel="noopener noreferrer"';
			}

			return '<a href="' . $this->attr($href) . '"' . $attrs . '>' . $this->e($label) . '</a>';
		}

		public function save(FieldContext $ctx)
		{
			$value = $this->value($ctx);
			if ($value === '') {
				return '';
			}

			if (!$this->valid($ctx)) {
				throw new \RuntimeException('Укажите корректное контактное значение');
			}

			return $this->mode($ctx) === 'email'
				? (function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value))
				: $value;
		}

		public function valid(FieldContext $ctx)
		{
			$value = $this->value($ctx);
			if ($value === '') {
				return true;
			}

			$mode = $this->mode($ctx);
			if ($mode === 'email') {
				return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
			}

			if ($mode === 'phone') {
				if (!preg_match('/^\+?[0-9\s()\-.]+$/', $value)) {
					return false;
				}

				$digits = preg_replace('/\D+/', '', $value);
				return strlen($digits) >= 5 && strlen($digits) <= 20;
			}

			if (preg_match('#^(?:javascript|data|vbscript):#i', $value)) {
				return false;
			}

			if (isset($value[0]) && in_array($value[0], array('/', '#', '?'), true)) {
				return true;
			}

			$parts = parse_url($value);
			return is_array($parts) && isset($parts['scheme'], $parts['host'])
				&& in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true);
		}

		protected function mode(FieldContext $ctx)
		{
			$mode = (string) $ctx->setting('mode', 'email');
			return in_array($mode, array('email', 'phone', 'url'), true) ? $mode : 'email';
		}

		protected function value(FieldContext $ctx)
		{
			$value = $ctx->value;
			if (is_array($value)) {
				$value = isset($value['raw']) ? $value['raw'] : '';
			}

			return trim((string) $value);
		}

		protected static function phoneHref($value)
		{
			$value = trim((string) $value);
			$plus = isset($value[0]) && $value[0] === '+' ? '+' : '';
			return $plus . preg_replace('/\D+/', '', $value);
		}
	}
