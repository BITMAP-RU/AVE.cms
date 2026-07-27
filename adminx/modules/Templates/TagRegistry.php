<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Templates/TagRegistry.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Templates;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use DB;
	use App\Content\ContentTables;
	use App\Helpers\Hooks;

	class TagRegistry
	{
		public static function groups()
		{
			$groups = array(
				self::group('Основные', array(
					self::tag('[tag:theme:folder]', 'Папка темы', 'folder'),
					self::tag('[tag:sitename]', 'Название сайта'),
					self::tag('[tag:maincontent]', 'Основной контент'),
					self::tag('[tag:title]', 'Заголовок'),
					self::tag('[tag:excerpt]', 'Краткое описание'),
					self::tag('[tag:alias]', 'Alias'),
					self::tag('[tag:domain]', 'Домен'),
					self::tag('[tag:home]', 'Главная'),
				)),
				self::group('SEO', array(
					self::tag('[tag:keywords]', 'Keywords'),
					self::tag('[tag:description]', 'Description'),
					self::tag('[tag:robots]', 'Robots'),
					self::tag('[tag:canonical]', 'Canonical'),
				)),
				self::group('Пути и файлы', array(
					self::tag('[tag:path]', 'Корень'),
					self::tag('[tag:mediapath]', 'Медиа-путь'),
					self::tag('[tag:theme-styles]', 'CSS из реестра активной темы'),
					self::tag('[tag:theme-scripts]', 'JavaScript из реестра активной темы'),
					self::tag('[tag:asset:css/app.css]', 'Версионированный URL файла темы', 'css/app.css'),
				)),
				self::group('Композиция', array(
					self::tag('[tag:rubheader]', 'Header рубрики'),
					self::tag('[tag:rubfooter]', 'Footer рубрики'),
					self::tag('[tag:breadcrumb]', 'Хлебные крошки'),
					self::tag('[tag:printlink]', 'Печать'),
					self::tag('[tag:version]', 'Версия'),
				)),
				self::group('Связанные данные', array(
					self::tag('[tag:doc:XXX]', 'Поле документа', 'XXX'),
					self::tag('[tag:sysblock:XXX]', 'Системный блок', 'XXX'),
					self::tag('[tag:teaser:XXX]', 'Тизер', 'XXX'),
					self::tag('[tag:navigation:XXX]', 'Навигация', 'XXX'),
				)),
				self::group('Системные блоки', self::sysblockTags()),
				self::group('Навигации', self::navigationTags()),
				self::group('Условия', array(
					self::tag("[tag:if_print]\n\n[/tag:if_print]", 'Для печати', '', '[tag:if_print]'),
					self::tag("[tag:if_notprint]\n\n[/tag:if_notprint]", 'Не печать', '', '[tag:if_notprint]'),
				)),
				self::group('HTML', array(
					self::tag("<ol>\n\n</ol>", 'Список', '', 'OL'),
					self::tag("<ul>\n\n</ul>", 'Список', '', 'UL'),
					self::tag('<li></li>', 'Пункт', '', 'LI'),
					self::tag('<p class=""></p>', 'Абзац', '', 'P'),
					self::tag('<strong></strong>', 'Жирный', '', 'B'),
					self::tag('<em></em>', 'Курсив', '', 'I'),
					self::tag('<h1></h1>', 'Заголовок', '', 'H1'),
					self::tag('<h2></h2>', 'Заголовок', '', 'H2'),
					self::tag('<h3></h3>', 'Заголовок', '', 'H3'),
					self::tag("<div class=\"\" id=\"\">\n\n</div>", 'Блок', '', 'DIV'),
					self::tag('<a href="" title=""></a>', 'Ссылка', '', 'A'),
					self::tag('<img src="" alt="" />', 'Изображение', '', 'IMG'),
					self::tag('<span></span>', 'Span', '', 'SPAN'),
					self::tag("<pre>\n\n</pre>", 'Pre', '', 'PRE'),
					self::tag('<br>', 'Перенос', '', 'BR'),
					self::tag("\t", 'Табуляция', '', 'TAB'),
				)),
			);

			$groups = Hooks::filter('admin.templates.tag_groups', $groups);
			return is_array($groups) ? $groups : array();
		}

		protected static function sysblockTags()
		{
			$table = ContentTables::table('sysblocks');
			if (!self::tableExists($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT id, sysblock_name, sysblock_alias, sysblock_active'
				. ' FROM ' . $table
				. ' ORDER BY sysblock_active DESC, sysblock_name ASC, id ASC'
				. ' LIMIT 200'
			)->getAll();

			$items = array();
			foreach ($rows as $row) {
				$id = (int) $row['id'];
				$name = trim((string) $row['sysblock_name']);
				$alias = trim((string) $row['sysblock_alias']);
				$key = $alias !== '' ? $alias : (string) $id;
				$description = $name !== '' ? $name : ('Системный блок #' . $id);
				if ((string) $row['sysblock_active'] !== '1') {
					$description .= ' · выключен';
				}

				$items[] = self::tag('[tag:sysblock:' . $key . ']', $description, '', '[tag:sysblock:' . $key . ']');
			}

			return $items;
		}

		protected static function navigationTags()
		{
			$table = ContentTables::table('navigation');
			if (!self::tableExists($table)) {
				return array();
			}

			$rows = DB::query(
				'SELECT navigation_id, alias, title'
				. ' FROM ' . $table
				. ' ORDER BY title ASC, navigation_id ASC'
				. ' LIMIT 200'
			)->getAll();

			$items = array();
			foreach ($rows as $row) {
				$id = (int) $row['navigation_id'];
				$title = trim((string) $row['title']);
				$alias = trim((string) $row['alias']);
				$key = $alias !== '' ? $alias : (string) $id;
				$description = $title !== '' ? $title : ('Навигация #' . $id);
				$items[] = self::tag('[tag:navigation:' . $key . ']', $description, '', '[tag:navigation:' . $key . ']');
			}

			return $items;
		}

		protected static function tableExists($table)
		{
			return (bool) DB::query('SHOW TABLES LIKE %s', (string) $table)->getValue();
		}

		protected static function group($title, array $items)
		{
			return array('title' => (string) $title, 'items' => $items);
		}

		protected static function tag($value, $description, $select = '', $label = '')
		{
			return array(
				'value' => (string) $value,
				'description' => (string) $description,
				'select' => (string) $select,
				'label' => (string) ($label !== '' ? $label : $value),
			);
		}
	}
