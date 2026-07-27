<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/FieldRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\LifecycleEvent;
	use App\Helpers\Hooks;

	/**
	 * Реестр типов полей. Автоскан папки Types/: добавил класс в Fields/Types/ =
	 * появился новый тип. Один реестр на редактор /adminx и публичный вывод.
	 */
	class FieldRegistry
	{
		/** @var FieldType[] code => instance */
		protected static $types = array();

		/** @var array code => registration metadata */
		protected static $metadata = array();

		protected static $discovered = false;

		public static function register(FieldType $type, $source = 'core', $creatable = true)
		{
			$code = (string) $type->code();
			self::$types[$code] = $type;
			self::$metadata[$code] = array(
				'source' => (string) $source,
				'creatable' => (bool) $creatable,
				'class' => get_class($type),
			);
		}

		/** Register an autoloadable module field type without scanning its directory. */
		public static function registerClassName($class, $source = 'module', $creatable = true)
		{
			$class = ltrim((string) $class, '\\');
			if ($class === '' || !class_exists($class)) {
				throw new \RuntimeException('Класс типа поля не найден: ' . $class);
			}

			$ref = new \ReflectionClass($class);
			if ($ref->isAbstract() || !$ref->implementsInterface(FieldType::class)) {
				throw new \RuntimeException('Класс не реализует FieldType: ' . $class);
			}

			$instance = new $class();
			self::register($instance, $source, $creatable);
			return (string) $instance->code();
		}

		public static function get($code)
		{
			self::ensureDiscovered();
			return isset(self::$types[(string) $code]) ? self::$types[(string) $code] : null;
		}

		public static function has($code)
		{
			self::ensureDiscovered();
			return isset(self::$types[(string) $code]);
		}

		/** @return FieldType[] */
		public static function all()
		{
			self::ensureDiscovered();
			return self::$types;
		}

		/** Список code => name (для конструктора рубрики). */
		public static function options()
		{
			self::ensureDiscovered();
			$out = array();
			foreach (self::$types as $code => $type) {
				$out[$code] = $type->name();
			}

			return $out;
		}

		public static function metadata($code = null)
		{
			self::ensureDiscovered();
			if ($code === null) {
				return self::$metadata;
			}

			return isset(self::$metadata[(string) $code]) ? self::$metadata[(string) $code] : null;
		}

		public static function codesBySource($source)
		{
			self::ensureDiscovered();
			$codes = array();
			foreach (self::$metadata as $code => $metadata) {
				if (isset($metadata['source']) && (string) $metadata['source'] === (string) $source) {
					$codes[] = (string) $code;
				}
			}

			return $codes;
		}

		/**
		 * Просканировать директорию с типами и зарегистрировать все классы.
		 * Ожидает файлы вида Types/SingleLine.php с классом
		 * App\Content\Fields\Types\SingleLine.
		 */
		public static function discover($dir = null)
		{
			if ($dir === null) {
				$dir = __DIR__ . DIRECTORY_SEPARATOR . 'Types';
			}

			if (!is_dir($dir)) {
				self::$discovered = true;
				return;
			}

			$classes = array();
			foreach (scandir($dir) ?: array() as $entry) {
				if ($entry === '.' || $entry === '..') {
					continue;
				}

				$path = $dir . DIRECTORY_SEPARATOR . $entry;
				if (is_file($path) && substr($entry, -4) === '.php') {
					$class = 'App\\Content\\Fields\\Types\\' . substr($entry, 0, -4);
					$classes[$class] = $path;
					self::registerClass($class, $path);
				}
			}

			// A concrete type may be scanned before an abstract parent located in
			// the same directory (for example Analoque -> DocFromRubMulti).
			foreach ($classes as $class => $path) {
				self::registerClass($class, $path);
			}

			self::$discovered = true;
			$event = new LifecycleEvent('field_registry', 'registered', null, array(
				'types' => array_keys(self::$types),
				'metadata' => self::$metadata,
			), self::$types, array('count' => count(self::$types)), 'field_registry');
			Hooks::action('content.field.registry', $event);
		}

		protected static function registerClass($class, $file)
		{
			if (!class_exists($class) && is_file($file)) {
				require_once $file;
			}

			if (!class_exists($class)) {
				return;
			}

			$ref = new \ReflectionClass($class);
			if ($ref->isAbstract() || !$ref->implementsInterface('App\\Content\\Fields\\FieldType')) {
				return;
			}

			$instance = new $class();
			if ($instance instanceof FieldType) {
				self::register($instance, 'core', true);
			}
		}

		protected static function ensureDiscovered()
		{
			if (!self::$discovered) {
				self::discover();
			}
		}

		/** Для тестов. */
		public static function reset()
		{
			self::$types = array();
			self::$metadata = array();
			self::$discovered = false;
		}
	}
