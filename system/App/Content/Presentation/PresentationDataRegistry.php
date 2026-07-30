<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Presentation/PresentationDataRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Presentation;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Declarative palette of values exposed to reusable public presentations. */
	class PresentationDataRegistry
	{
		protected static $items = array();
		protected static $defaultsRegistered = false;

		public static function register($code, array $definition, $source = 'core', $available = true)
		{
			self::ensureDefaults();
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код данных представления: ' . $code);
			}

			if (isset(self::$items[$code]) && (string) self::$items[$code]['source'] !== (string) $source) {
				throw new \RuntimeException('Данные представления уже зарегистрированы: ' . $code);
			}

			$item = self::normalize($code, $definition);
			$item['source'] = (string) $source;
			$item['available'] = (bool) $available;
			self::$items[$code] = $item;
			return $item;
		}

		public static function registerMany($source, array $definitions, $available = true)
		{
			self::ensureDefaults();
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
			if (!isset(self::$items[$code])) { return null; }
			if (!$includeUnavailable && empty(self::$items[$code]['available'])) { return null; }
			return self::$items[$code];
		}

		public static function all($includeUnavailable = false)
		{
			self::ensureDefaults();
			$items = array();
			foreach (self::$items as $item) {
				if ($includeUnavailable || !empty($item['available'])) { $items[] = $item; }
			}

			usort($items, function ($left, $right) {
				$group = strcasecmp((string) $left['group'], (string) $right['group']);
				if ($group !== 0) { return $group; }
				$priority = (int) $left['priority'] <=> (int) $right['priority'];
				return $priority !== 0 ? $priority : strcasecmp((string) $left['title'], (string) $right['title']);
			});
			return $items;
		}

		public static function groups($includeUnavailable = false)
		{
			$groups = array();
			foreach (self::all($includeUnavailable) as $item) {
				$key = (string) $item['group'];
				if (!isset($groups[$key])) {
					$groups[$key] = array('code' => $key, 'title' => (string) $item['group_title'], 'items' => array());
				}

				$groups[$key]['items'][] = $item;
			}

			return array_values($groups);
		}

		public static function reset()
		{
			self::$items = array();
			self::$defaultsRegistered = false;
		}

		protected static function ensureDefaults()
		{
			if (self::$defaultsRegistered) { return; }
			self::$defaultsRegistered = true;
			$definitions = array(
				'document.id' => array('title' => 'ID материала', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'integer', 'path' => 'item.id', 'priority' => 10),
				'document.title' => array('title' => 'Название', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'string', 'path' => 'item.title', 'priority' => 20),
				'document.url' => array('title' => 'Адрес', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'url', 'path' => 'item.url', 'priority' => 30),
				'document.excerpt' => array('title' => 'Анонс', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'text', 'path' => 'item.excerpt', 'priority' => 40),
				'document.description' => array('title' => 'Описание', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'text', 'path' => 'item.description', 'priority' => 50),
				'document.rubric_id' => array('title' => 'ID типа контента', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'integer', 'path' => 'item.rubric_id', 'priority' => 60),
				'document.published_at' => array('title' => 'Дата публикации', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'integer', 'path' => 'item.published_at', 'priority' => 70),
				'document.views' => array('title' => 'Просмотры', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'integer', 'path' => 'item.views', 'priority' => 80),
				'document.image' => array('title' => 'Первое изображение', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'media', 'path' => 'item.image', 'priority' => 90),
				'document.thumb' => array('title' => 'Превью изображения', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'media', 'path' => 'item.thumb', 'priority' => 100),
				'document.fields' => array('title' => 'Поля типа контента', 'group' => 'document', 'group_title' => 'Материал', 'type' => 'map', 'path' => 'item.fields', 'priority' => 110, 'dynamic' => true),
				'context.code' => array('title' => 'Место использования', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'string', 'path' => 'context.code', 'priority' => 10),
				'context.position' => array('title' => 'Позиция в списке', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'integer', 'path' => 'context.position', 'priority' => 20),
				'context.total' => array('title' => 'Всего элементов', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'integer', 'path' => 'context.total', 'priority' => 30),
				'context.page' => array('title' => 'Текущая страница', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'integer', 'path' => 'context.page', 'priority' => 40),
				'context.pages' => array('title' => 'Всего страниц', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'integer', 'path' => 'context.pages', 'priority' => 50),
				'context.pagination' => array('title' => 'Данные пагинации', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'map', 'path' => 'context.pagination', 'priority' => 60, 'contexts' => array('content_list')),
				'context.pagination_html' => array('title' => 'Готовая пагинация', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'text', 'path' => 'context.pagination_html', 'priority' => 70, 'contexts' => array('content_list', 'search_results')),
				'context.query' => array('title' => 'Поисковая фраза', 'group' => 'context', 'group_title' => 'Контекст', 'type' => 'string', 'path' => 'context.query', 'priority' => 80, 'contexts' => array('search_results')),
				'search.relevance' => array('title' => 'Релевантность', 'group' => 'search', 'group_title' => 'Поиск', 'type' => 'number', 'path' => 'item.search.relevance', 'priority' => 10, 'contexts' => array('search_results')),
				'related.score' => array('title' => 'Оценка похожести', 'group' => 'related', 'group_title' => 'Похожие материалы', 'type' => 'number', 'path' => 'item.related.score', 'priority' => 10, 'contexts' => array('related')),
				'related.matches' => array('title' => 'Совпавшие признаки', 'group' => 'related', 'group_title' => 'Похожие материалы', 'type' => 'integer', 'path' => 'item.related.matches', 'priority' => 20, 'contexts' => array('related')),
				'related.source' => array('title' => 'Источник связи', 'group' => 'related', 'group_title' => 'Похожие материалы', 'type' => 'string', 'path' => 'item.related.source', 'priority' => 30, 'contexts' => array('related')),
			);
			foreach ($definitions as $code => $definition) {
				$item = self::normalize($code, $definition);
				$item['source'] = 'core';
				$item['available'] = true;
				self::$items[$code] = $item;
			}
		}

		protected static function normalize($code, array $definition)
		{
			$title = trim(isset($definition['title']) ? (string) $definition['title'] : '');
			$group = strtolower(trim(isset($definition['group']) ? (string) $definition['group'] : 'general'));
			$path = trim(isset($definition['path']) ? (string) $definition['path'] : '');
			if ($title === '') { throw new \InvalidArgumentException('У данных представления отсутствует название: ' . $code); }
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $group)) { throw new \InvalidArgumentException('Некорректная группа данных: ' . $group); }
			if ($path === '' || !preg_match('/^[a-z][a-z0-9_.]{1,127}$/', $path)) {
				throw new \InvalidArgumentException('Некорректный путь данных представления: ' . $code);
			}

			$type = strtolower(trim(isset($definition['type']) ? (string) $definition['type'] : 'string'));
			if (!in_array($type, array('string', 'text', 'integer', 'number', 'boolean', 'url', 'media', 'list', 'map', 'action'), true)) {
				$type = 'string';
			}

			return array(
				'code' => (string) $code,
				'title' => $title,
				'description' => isset($definition['description']) ? trim((string) $definition['description']) : '',
				'group' => $group,
				'group_title' => trim(isset($definition['group_title']) ? (string) $definition['group_title'] : '') ?: ucfirst($group),
				'type' => $type,
				'path' => $path,
				'icon' => trim(isset($definition['icon']) ? (string) $definition['icon'] : 'ti-brackets'),
				'priority' => isset($definition['priority']) ? (int) $definition['priority'] : 100,
				'dynamic' => !empty($definition['dynamic']),
				'contexts' => isset($definition['contexts']) && is_array($definition['contexts']) ? array_values($definition['contexts']) : array(),
			);
		}
	}
