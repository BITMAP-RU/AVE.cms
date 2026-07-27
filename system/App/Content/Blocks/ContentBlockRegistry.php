<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Blocks/ContentBlockRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Blocks;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Declarative block types for future structured, repeatable content fields. */
	class ContentBlockRegistry
	{
		protected static $blocks = array();
		protected static $defaultsRegistered = false;

		public static function register($code, array $definition, $source = 'core', $available = true)
		{
			self::ensureDefaults();
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код блока контента: ' . $code);
			}
			if (isset(self::$blocks[$code]) && (string) self::$blocks[$code]['source'] !== (string) $source) {
				throw new \RuntimeException('Код блока контента уже зарегистрирован: ' . $code);
			}

			$block = self::normalize($code, $definition);
			$block['source'] = (string) $source;
			$block['available'] = (bool) $available;
			self::$blocks[$code] = $block;
			return $block;
		}

		public static function registerMany($source, array $definitions, $available = true)
		{
			$registered = array();
			foreach ($definitions as $key => $definition) {
				if (!is_array($definition)) { continue; }
				$code = is_string($key) ? $key : (isset($definition['code']) ? $definition['code'] : '');
				$registered[] = self::register($code, $definition, $source, $available);
			}
			return $registered;
		}

		public static function get($code, $includeUnavailable = false)
		{
			self::ensureDefaults();
			$code = strtolower(trim((string) $code));
			if (!isset(self::$blocks[$code])) { return null; }
			if (!$includeUnavailable && empty(self::$blocks[$code]['available'])) { return null; }
			return self::$blocks[$code];
		}

		public static function all($includeUnavailable = false)
		{
			self::ensureDefaults();
			$blocks = array();
			foreach (self::$blocks as $block) {
				if ($includeUnavailable || !empty($block['available'])) { $blocks[] = $block; }
			}
			usort($blocks, function ($left, $right) {
				$priority = (int) $left['priority'] <=> (int) $right['priority'];
				return $priority !== 0 ? $priority : strcasecmp((string) $left['title'], (string) $right['title']);
			});
			return $blocks;
		}

		public static function reset()
		{
			self::$blocks = array();
			self::$defaultsRegistered = false;
		}

		protected static function ensureDefaults()
		{
			if (self::$defaultsRegistered) { return; }
			self::$defaultsRegistered = true;
			$definitions = array(
				'paragraph' => array('title' => 'Текст', 'icon' => 'ti-align-left', 'priority' => 10, 'fields' => array(array('key' => 'text', 'type' => 'multi_line', 'required' => true))),
				'rich_text' => array('title' => 'Форматированный текст', 'icon' => 'ti-text-size', 'priority' => 20, 'fields' => array(array('key' => 'html', 'type' => 'rich_text', 'required' => true))),
				'image' => array('title' => 'Изображение', 'icon' => 'ti-photo', 'priority' => 30, 'fields' => array(array('key' => 'image', 'type' => 'image_single', 'required' => true), array('key' => 'caption', 'type' => 'single_line'))),
				'quote' => array('title' => 'Цитата', 'icon' => 'ti-quote', 'priority' => 40, 'fields' => array(array('key' => 'text', 'type' => 'multi_line', 'required' => true), array('key' => 'author', 'type' => 'single_line'))),
				'document_relation' => array('title' => 'Связанные материалы', 'icon' => 'ti-link', 'priority' => 50, 'fields' => array(array('key' => 'documents', 'type' => 'doc_from_rub_search', 'required' => true))),
			);
			foreach ($definitions as $code => $definition) {
				$block = self::normalize($code, $definition);
				$block['source'] = 'core';
				$block['available'] = true;
				self::$blocks[$code] = $block;
			}
		}

		protected static function normalize($code, array $definition)
		{
			$title = trim(isset($definition['title']) ? (string) $definition['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('У блока контента отсутствует название: ' . $code); }
			$fields = array();
			$keys = array();
			foreach (isset($definition['fields']) && is_array($definition['fields']) ? $definition['fields'] : array() as $field) {
				if (!is_array($field)) { continue; }
				$key = strtolower(trim(isset($field['key']) ? (string) $field['key'] : ''));
				$type = trim(isset($field['type']) ? (string) $field['type'] : '');
				if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) || $type === '' || isset($keys[$key])) {
					throw new \InvalidArgumentException('Некорректное поле блока ' . $code . ': ' . ($key !== '' ? $key : '(без ключа)'));
				}
				$keys[$key] = true;
				$fields[] = array(
					'key' => $key,
					'type' => $type,
					'label' => isset($field['label']) ? trim((string) $field['label']) : '',
					'required' => !empty($field['required']),
					'settings' => isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array(),
				);
			}
			if (!$fields) { throw new \InvalidArgumentException('Блок контента не содержит полей: ' . $code); }

			return array(
				'code' => $code,
				'title' => $title,
				'description' => isset($definition['description']) ? trim((string) $definition['description']) : '',
				'icon' => isset($definition['icon']) ? trim((string) $definition['icon']) : 'ti-box',
				'priority' => isset($definition['priority']) ? (int) $definition['priority'] : 100,
				'fields' => $fields,
				'settings' => isset($definition['settings']) && is_array($definition['settings']) ? $definition['settings'] : array(),
			);
		}
	}
