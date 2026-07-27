<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Requests/RequestRendererRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Requests;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Declarative registry of request result presentations supplied by core and modules. */
	class RequestRendererRegistry
	{
		protected static $renderers = array();
		protected static $defaultsRegistered = false;

		public static function register($code, array $definition, $source = 'core', $available = true)
		{
			self::ensureDefaults();
			$code = strtolower(trim((string) $code));
			if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $code)) {
				throw new \InvalidArgumentException('Некорректный код renderer: ' . $code);
			}

			if (isset(self::$renderers[$code]) && (string) self::$renderers[$code]['source'] !== (string) $source) {
				throw new \RuntimeException('Код renderer уже зарегистрирован: ' . $code);
			}

			$normalized = self::normalize($code, $definition);
			$normalized['source'] = (string) $source;
			$normalized['available'] = (bool) $available;
			self::$renderers[$code] = $normalized;
			return $normalized;
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
			if (!isset(self::$renderers[$code])) { return null; }
			if (!$includeUnavailable && empty(self::$renderers[$code]['available'])) { return null; }
			return self::$renderers[$code];
		}

		public static function all($includeUnavailable = false)
		{
			self::ensureDefaults();
			$renderers = array();
			foreach (self::$renderers as $renderer) {
				if ($includeUnavailable || !empty($renderer['available'])) { $renderers[] = $renderer; }
			}

			usort($renderers, function ($left, $right) {
				$priority = (int) $left['priority'] <=> (int) $right['priority'];
				return $priority !== 0 ? $priority : strcasecmp((string) $left['title'], (string) $right['title']);
			});
			return $renderers;
		}

		public static function previewOptions()
		{
			return array_values(array_filter(self::all(), function ($renderer) {
				return !empty($renderer['preview']);
			}));
		}

		public static function normalizePreviewCode($code)
		{
			$renderer = self::get($code);
			return $renderer && !empty($renderer['preview']) ? (string) $renderer['code'] : 'data_cards';
		}

		public static function reset()
		{
			self::$renderers = array();
			self::$defaultsRegistered = false;
		}

		protected static function ensureDefaults()
		{
			if (self::$defaultsRegistered) { return; }
			self::$defaultsRegistered = true;
			self::$renderers = array(
				'legacy' => self::withRuntime(array(
					'code' => 'legacy',
					'title' => 'Legacy HTML',
					'description' => 'Текущие шаблоны main/item и совместимый публичный вывод.',
					'icon' => 'ti-code',
					'priority' => 10,
					'formats' => array('html'),
					'preview' => false,
					'public' => true,
					'presentation' => 'legacy',
				), 'core', true),
				'data_cards' => self::withRuntime(array(
					'code' => 'data_cards',
					'title' => 'Карточки данных',
					'description' => 'Читаемый предпросмотр свойств из контракта результата.',
					'icon' => 'ti-layout-cards',
					'priority' => 20,
					'formats' => array('data'),
					'preview' => true,
					'public' => false,
					'presentation' => 'cards',
				), 'core', true),
				'data_list' => self::withRuntime(array(
					'code' => 'data_list',
					'title' => 'Компактный список',
					'description' => 'Название и основные значения в одной строке.',
					'icon' => 'ti-list-details',
					'priority' => 30,
					'formats' => array('data'),
					'preview' => true,
					'public' => false,
					'presentation' => 'list',
				), 'core', true),
				'data_table' => self::withRuntime(array(
					'code' => 'data_table',
					'title' => 'Таблица',
					'description' => 'Материалы по строкам, выбранные данные по колонкам.',
					'icon' => 'ti-table',
					'priority' => 40,
					'formats' => array('data'),
					'preview' => true,
					'public' => false,
					'presentation' => 'table',
				), 'core', true),
				'json' => self::withRuntime(array(
					'code' => 'json',
					'title' => 'JSON',
					'description' => 'Структурированный результат для API и интеграций.',
					'icon' => 'ti-braces',
					'priority' => 50,
					'formats' => array('json'),
					'preview' => true,
					'public' => false,
					'presentation' => 'json',
				), 'core', true),
			);
		}

		protected static function normalize($code, array $definition)
		{
			$title = trim(isset($definition['title']) ? (string) $definition['title'] : '');
			if ($title === '') { throw new \InvalidArgumentException('У renderer отсутствует название: ' . $code); }

			$formats = array();
			foreach (isset($definition['formats']) && is_array($definition['formats']) ? $definition['formats'] : array() as $format) {
				$format = strtolower(trim((string) $format));
				if ($format !== '' && preg_match('/^[a-z0-9_-]{1,32}$/', $format) && !in_array($format, $formats, true)) {
					$formats[] = $format;
				}
			}

			if (!$formats) { $formats[] = 'data'; }

			$presentation = isset($definition['presentation']) ? strtolower(trim((string) $definition['presentation'])) : 'cards';
			if (!in_array($presentation, array('cards', 'list', 'table', 'json', 'legacy', 'custom'), true)) { $presentation = 'custom'; }

			return array(
				'code' => $code,
				'title' => $title,
				'description' => isset($definition['description']) ? trim((string) $definition['description']) : '',
				'icon' => isset($definition['icon']) ? trim((string) $definition['icon']) : 'ti-template',
				'priority' => isset($definition['priority']) ? (int) $definition['priority'] : 100,
				'formats' => $formats,
				'preview' => !empty($definition['preview']),
				'public' => !empty($definition['public']),
				'presentation' => $presentation,
				'settings' => isset($definition['settings']) && is_array($definition['settings']) ? $definition['settings'] : array(),
				'contract' => isset($definition['contract']) && is_array($definition['contract']) ? $definition['contract'] : array(),
			);
		}

		protected static function withRuntime(array $definition, $source, $available)
		{
			$definition['source'] = (string) $source;
			$definition['available'] = (bool) $available;
			return $definition;
		}
	}
