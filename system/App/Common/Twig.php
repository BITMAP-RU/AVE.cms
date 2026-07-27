<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/Twig.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	/**
	 * Класс Twig для работы с шаблонизатором Twig
	 *
	 * Этот класс предоставляет функциональность для инициализации и управления
	 * шаблонизатором Twig в приложении. Он отвечает за создание экземпляра Twig,
	 * настройку путей к шаблонам, добавление глобальных переменных и т.д.
	 */
	namespace App\Common;

	use App\Common\Loader\Load;
	use App\Common\Twig\TwigExtensions;
	use Twig\Cache\FilesystemCache;
	use Twig\Environment;
	use Twig\Error\LoaderError;
	use Twig\Extension\CoreExtension;
	use Twig\Extension\DebugExtension;
	use Twig\Extension\StringLoaderExtension;
	use Twig\Loader\ArrayLoader;
	use Twig\Loader\ChainLoader;
	use Twig\Loader\FilesystemLoader;
	use Twig\Profiler\Profile;
	use Twig\TwigFilter;
	use Twig\TwigFunction;

	class Twig
	{
		/**
		 * Экземпляр Twig Environment для работы с шаблонами
		 * @var \Twig\Environment
		 */
		public static $twig;

		/**
		 * Глобальные переменные, доступные во всех шаблонах
		 * @var array
		 */
		public static $twig_vars = [];

		/**
		 * Пути к директориям с шаблонами
		 * @var array
		 */
		public static $twig_paths = [];

		/**
		 * Текущий шаблон
		 * @var string
		 */
		public static $template;

		/**
		 * Параметры конфигурации Twig
		 * @var array
		 */
		public static $params = [];

		/**
		 * Экземпляр класса (паттерн Singleton)
		 * @var self|null
		 */
		protected static $instance;

		/**
		 * Загрузчик шаблонов из файловой системы
		 * @var \Twig\Loader\FilesystemLoader
		 */
		protected static $loader;

		/**
		 * Загрузчик шаблонов из массива
		 * @var \Twig\Loader\ArrayLoader
		 */
		protected static $loaderArray;

		/**
		 * Настройка автоматического экранирования вывода
		 * @var mixed
		 */
		protected static $autoescape;

		/**
		 * Профайлер для отслеживания производительности
		 * @var \Twig\Profiler\Profile
		 */
		protected static $profile;


		/**
		 * Конструктор класса Twig
		 * Инициализирует загрузчики шаблонов, настраивает параметры Twig и добавляет расширения
		 *
		 * @return void
		 */
		public function __construct()
		{
			// Загружает класс TwigExtensions для расширения функциональности Twig
			Load::addClass('TwigExtensions', BASEPATH . DS . 'system' . DS . 'App' . DS . 'Common' . DS . 'Twig' . DS . 'TwigExtesions.php');

			// Создает загрузчик шаблонов из файловой системы с указанными путями
			self::$loader = new FilesystemLoader(self::$twig_paths);

			// Создает загрузчик шаблонов из массива (для временных шаблонов)
			self::$loaderArray = new ArrayLoader([]);

			// Создает цепочку загрузчиков для поиска шаблонов
			$loader_chain = new ChainLoader([self::$loaderArray, self::$loader]);

			// Устанавливает параметры конфигурации Twig
			self::$params += [
				'charset' => 'UTF-8',
				'cache' => BASEPATH . DS . 'tmp' . DS . 'cache' . DS . 'twig',
				'debug' => defined('PHP_DEBUGGING') && PHP_DEBUGGING,
				'autoescape' => 'html',
			];

			// Создает экземпляр Environment Twig с заданными загрузчиками и параметрами
			self::$twig = new Environment($loader_chain, self::$params);

			// Добавляет расширение TwigExtensions к экземпляру Twig
			self::$twig->addExtension(new TwigExtensions());

			// Добавляет глобальные переменные в Twig
			self::$twig_vars += [];

			foreach (self::$twig_vars as $key => $var) {
				self::addGlobal($key, $var);
			}
		}


		/**
		 * Инициализация экземпляра класса (паттерн Singleton)
		 * Создает единственный экземпляр класса Twig, если он еще не создан
		 *
		 * @return self Возвращает экземпляр класса Twig
		 */
		public static function init()
		{
			// Проверяет, существует ли уже экземпляр класса
			if (!isset(self::$instance))
			{
				// Создает новый экземпляр класса
				self::$instance = new self;
			}

			// Возвращает экземпляр класса
			return self::$instance;
		}


		/**
		 * Сброс экземпляра класса (паттерн Singleton)
		 * Удаляет текущий экземпляр класса, чтобы можно было создать новый
		 *
		 * @return void
		 */
		public static function resetInstance()
		{
			// Проверяет, существует ли экземпляр класса
			if (self::$instance)
			{
				// Устанавливает экземпляр в null для сброса
				self::$instance = null;
			}
		}


		/**
		 * Получение экземпляра Twig Environment
		 * Возвращает текущий экземпляр окружения Twig
		 *
		 * @return \Twig\Environment Возвращает экземпляр Twig Environment
		 */
		public static function twig()
		{
			return self::$twig;
		}


		/**
		 * Получение загрузчика шаблонов из файловой системы
		 * Возвращает экземпляр FilesystemLoader для работы с шаблонами из файлов
		 *
		 * @return \Twig\Loader\FilesystemLoader Возвращает загрузчик шаблонов
		 */
		public static function loader()
		{
			return self::$loader;
		}


		/**
		 * Установка временного шаблона в массиве
		 * Добавляет шаблон в загрузчик из массива для использования в коде
		 *
		 * @param string $name Имя шаблона
		 * @param string $template Содержимое шаблона
		 * @return void
		 */
		public static function setTemplate($name, $template)
		{
			// Устанавливает шаблон в загрузчик из массива
			self::$loaderArray->setTemplate($name, $template);
		}


		/**
		 * Добавление пути к директории с шаблонами
		 * Добавляет новый путь для поиска шаблонов в файловой системе
		 *
		 * @param string $template_path Путь к директории с шаблонами
		 * @param string $namespace Пространство имен (по умолчанию '__main__')
		 * @return void
		 */
		public static function addPath($template_path, $namespace = '__main__')
		{
			// Добавляет путь к загрузчику шаблонов из файловой системы
			self::$loader->addPath($template_path, $namespace);
		}


		/**
		 * Добавление глобальной переменной в Twig
		 * Добавляет переменную, доступную во всех шаблонах
		 *
		 * @param string $name Имя переменной
		 * @param mixed $variable Значение переменной
		 * @return void
		 */
		public static function addGlobal($name, $variable)
		{
			// Добавляет глобальную переменную в экземпляр Twig
			self::$twig->addGlobal($name, $variable);
		}


		/**
		 * Добавление нескольких глобальных переменных
		 * Добавляет массив переменных, доступных во всех шаблонах
		 *
		 * @param array $variables Массив переменных для добавления
		 * @return void
		 */
		public static function addGlobals(array $variables)
		{
			// Проходит по каждому элементу массива переменных
			foreach ($variables as $key => $var)
			{
				// Добавляет каждую переменную отдельно
				self::addGlobal($key, $var);
			}
		}


		/**
		 * Добавление пути в начало списка путей к шаблонам
		 * Добавляет путь в начало списка поиска шаблонов (более высокий приоритет)
		 *
		 * @param string $template_path Путь к директории с шаблонами
		 * @param string $namespace Пространство имен (по умолчанию '__main__')
		 * @return void
		 */
		public static function prependPath($template_path, $namespace = '__main__')
		{
			// Добавляет путь в начало списка загрузчиков шаблонов из файловой системы
			self::$loader->prependPath($template_path, $namespace);
		}
	}
