<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Loader/Load.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common\Loader;

	defined("BASEPATH") || die('Direct access to this location is not allowed.');

	/**
	 * PSR-4 автозагрузчик классов AVE.cms
	 *
	 * Порядок разрешения имени класса:
	 *   1. Псевдонимы ($aliases)
	 *   2. Явные пути ($classes)
	 *   3. PSR-4 пространства имён ($namespaces, несколько директорий на префикс)
	 */
	class Load
	{
		/** @var array<string,string> Явные пути: ClassName => /path/to/File.php */
		protected static $classes = [];

		/**
		 * PSR-4 пространства имён.
		 * Поддерживает несколько базовых директорий на один префикс:
		 *   $namespaces['App\\'] = ['/path/a', '/path/b']
		 * @var array<string,string[]>
		 */
		protected static $namespaces = [];

		/** @var array<string,string> Псевдонимы: Alias => RealClass */
		protected static $aliases = [];

		/** @var string[] Директории, зарегистрированные через addDirectory() */
		protected static $directories = [];

		protected function __construct()
		{
			//
		}

		// ------------------------------------------------------------------ //
		//  Регистрация классов и пространств имён
		// ------------------------------------------------------------------ //

		public static function addClass($className, $classPath)
		{
			self::$classes[$className] = $classPath;
		}

		public static function addClasses(array $classes)
		{
			foreach ($classes as $name => $path) {
				self::$classes[$name] = $path;
			}
		}

		/**
		 * Зарегистрировать пространство имён для PSR-4 автозагрузки.
		 * Несколько вызовов с одним namespace добавляют директории (не перезаписывают).
		 *
		 * @param string $namespace Пространство имён без завершающего слэша
		 * @param string $path      Базовая директория
		 */
		public static function regNamespace($namespace, $path)
		{
			$key = trim($namespace, '\\') . '\\';
			$dir = rtrim($path, DS);

			if (!isset(self::$namespaces[$key])) {
				self::$namespaces[$key] = [];
			}

			if (!in_array($dir, self::$namespaces[$key], true)) {
				self::$namespaces[$key][] = $dir;
			}
		}

		/**
		 * Пакетная регистрация пространств имён.
		 *
		 * @param array<string,string> $namespaces ['Namespace' => '/path']
		 */
		public static function addNamespaces(array $namespaces)
		{
			foreach ($namespaces as $namespace => $path) {
				self::regNamespace($namespace, $path);
			}
		}

		public static function addAlias($alias, $className)
		{
			self::$aliases[$alias] = $className;
		}

		/**
		 * Добавить директорию в пул для loadDirectories()
		 */
		public static function addDirectory($path)
		{
			$dir = rtrim($path, DS);
			if (!in_array($dir, self::$directories, true)) {
				self::$directories[] = $dir;
			}
		}

		// ------------------------------------------------------------------ //
		//  Загрузка файлов
		// ------------------------------------------------------------------ //

		/**
		 * Подключить один PHP-файл (require_once с проверкой существования).
		 *
		 * @param string $path Абсолютный путь к файлу
		 * @return bool
		 */
		public static function loadFile($path)
		{
			if (!is_file($path)) {
				return false;
			}

			require_once $path;
			return true;
		}

		/**
		 * Подключить все *.php файлы из указанной директории.
		 *
		 * @param string|null $path null = использовать все зарегистрированные через addDirectory()
		 */
		public static function addFiles($path = null)
		{
			$dirs = $path !== null ? [$path] : self::$directories;

			foreach ($dirs as $dir) {
				$files = glob($dir . DS . '*.php');
				if (!$files) {
					continue;
				}

				foreach ($files as $file) {
					require_once $file;
				}
			}
		}

		/**
		 * Подключить все *.php файлы из всех зарегистрированных директорий.
		 */
		public static function loadDirectories()
		{
			self::addFiles(null);
		}

		// ------------------------------------------------------------------ //
		//  Разрешение и проверка классов
		// ------------------------------------------------------------------ //

		/**
		 * Проверить, можно ли разрешить класс (без его загрузки).
		 * Проверяет aliases → classes → namespaces.
		 *
		 * @param string $className
		 * @return bool
		 */
		public static function has($className)
		{
			$className = ltrim($className, '\\');

			if (class_exists($className, false) || interface_exists($className, false)) {
				return true;
			}

			if (isset(self::$aliases[$className]) || isset(self::$classes[$className])) {
				return true;
			}

			foreach (self::$namespaces as $namespace => $baseDirs) {
				if (strpos($className, $namespace) === 0) {
					$relativeClass = str_replace('\\', DS, substr($className, strlen($namespace)));
					foreach ($baseDirs as $baseDir) {
						if (file_exists($baseDir . DS . $relativeClass . '.php')) {
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Основной метод автозагрузки (PSR-4).
		 * Регистрируется через spl_autoload_register в init().
		 *
		 * @param string $className
		 * @return bool
		 */
		public static function loadClass($className)
		{
			$className = ltrim($className, '\\');

			if (isset(self::$aliases[$className])) {
				return class_alias(self::$aliases[$className], $className);
			}

			if (isset(self::$classes[$className])) {
				$path = self::$classes[$className];
				if (file_exists($path)) {
					require_once $path;
					return true;
				}
			}

			foreach (self::$namespaces as $namespace => $baseDirs) {
				if (strpos($className, $namespace) === 0) {
					$relativeClass = str_replace('\\', DS, substr($className, strlen($namespace)));
					foreach ($baseDirs as $baseDir) {
						$filePath = $baseDir . DS . $relativeClass . '.php';
						if (file_exists($filePath)) {
							require_once $filePath;
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Загрузить модель (prefix Model_*)
		 *
		 * @param string $className
		 * @return bool
		 */
		public static function loadModel($className)
		{
			$className = 'Model_' . ucfirst(ltrim($className, '\\'));

			if (isset(self::$aliases[$className])) {
				return class_alias(self::$aliases[$className], $className);
			}

			if (isset(self::$classes[$className]) && file_exists(self::$classes[$className])) {
				require_once self::$classes[$className];
				return true;
			}

			return false;
		}

		/**
		 * Создать экземпляр класса (автозагрузка + instantiate).
		 *
		 * @param string $className
		 * @return object|null
		 */
		public static function factory($className)
		{
			$className = ltrim($className, '\\');

			if (isset(self::$aliases[$className])) {
				$className = self::$aliases[$className];
			}

			if (!class_exists($className)) {
				self::loadClass($className);
			}

			return class_exists($className) ? new $className() : null;
		}

		// ------------------------------------------------------------------ //
		//  Модули
		// ------------------------------------------------------------------ //

		/**
		 * Загрузить все модули админ-панели из директории path/modules/
		 *
		 * @param string $path
		 * @return array ['errors' => [...]]
		 */
		public static function addAdminModules($path = '')
		{
			$modulesDir = $path . DS . 'modules';
			$dir        = dir($modulesDir);
			$modules    = [];

			while (false !== ($entry = $dir->read())) {
				if (strpos($entry, '.') === 0) {
					continue;
				}

				$module_dir = $dir->path . DS . $entry;

				if (!is_dir($module_dir)) {
					continue;
				}

				$moduleFile = $module_dir . DS . 'module.php';

				if (!is_file($moduleFile) || !self::loadFile($moduleFile)) {
					$modules['errors'][] = $entry;
					continue;
				}

				self::setAdminModel('Model_' . ucfirst($entry), $module_dir . DS . 'model.php');

				$module_name = 'Module_' . ucfirst($entry);
				if (class_exists($module_name)) {
					new $module_name();
				}
			}

			$dir->close();

			return $modules;
		}

		public static function setAdminModel($name, $path)
		{
			if (is_dir(dirname($path)) && is_file($path)) {
				self::addClasses([$name => $path]);
			}
		}

		// ------------------------------------------------------------------ //
		//  Инициализация
		// ------------------------------------------------------------------ //

		/**
		 * Зарегистрировать loadClass() в стеке spl_autoload.
		 */
		public static function init()
		{
			spl_autoload_register([self::class, 'loadClass'], true);
		}

		/**
		 * Зарегистрировать Twig-автолоадер (PSR-0 стиль с подчёркиваниями).
		 */
		public static function twigLoad()
		{
			$baseDir = BASEPATH . DS . 'system' . DS . 'vendor' . DS . 'Twig' . DS;

			spl_autoload_register(static function ($class) use ($baseDir) {
				if (strncmp('Twig', $class, 4) !== 0) {
					return;
				}

				$relativeClass = substr($class, 4);
				$file = $baseDir . str_replace(['_', '\\'], [DS, DS], $relativeClass) . '.php';

				if (file_exists($file)) {
					require_once $file;
				}
			});
		}

		// ------------------------------------------------------------------ //
		//  Отладка
		// ------------------------------------------------------------------ //

		/**
		 * Вывести в лог информацию о разрешении класса (отладка автозагрузки).
		 */
		public static function debugLoad($className)
		{
			$className = ltrim($className, '\\');
			$lines     = [
				"Load::debugLoad(): {$className}",
				"Namespaces: " . print_r(self::$namespaces, true),
			];

			foreach (self::$namespaces as $namespace => $baseDirs) {
				if (strpos($className, $namespace) === 0) {
					$relativeClass = str_replace('\\', DS, substr($className, strlen($namespace)));
					foreach ($baseDirs as $baseDir) {
						$filePath = $baseDir . DS . $relativeClass . '.php';
						$lines[]  = "Matched: ns={$namespace} dir={$baseDir} file={$filePath} exists=" . (file_exists($filePath) ? 'yes' : 'no');
					}
				}
			}

			if (class_exists('Logger')) {
				$logger = \Logger::getInstance();
				foreach ($lines as $line) {
					$logger->info($line);
				}
			} else {
				foreach ($lines as $line) {
					error_log($line);
				}
			}
		}

		// ------------------------------------------------------------------ //
		//  Геттеры и сброс
		// ------------------------------------------------------------------ //

		public static function getAliases(): array
		{
			return self::$aliases;
		}

		public static function getNamespaces(): array
		{
			return self::$namespaces;
		}

		public static function getClasses(): array
		{
			return self::$classes;
		}

		public static function getDirectories(): array
		{
			return self::$directories;
		}

		/**
		 * Сбросить внутренние массивы.
		 *
		 * @param string|null $type 'classes' | 'namespaces' | 'aliases' | 'directories' | null (все)
		 */
		public static function reset($type = null)
		{
			if ($type === 'classes' || $type === null) {
				self::$classes = [];
			}

			if ($type === 'namespaces' || $type === null) {
				self::$namespaces = [];
			}

			if ($type === 'aliases' || $type === null) {
				self::$aliases = [];
			}

			if ($type === 'directories' || $type === null) {
				self::$directories = [];
			}
		}
	}
