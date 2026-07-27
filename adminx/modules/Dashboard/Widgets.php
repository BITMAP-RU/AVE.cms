<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Dashboard/Widgets.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Dashboard;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Adminx\Support\InterfaceSettings;

	class Widgets
	{
		public static function catalog(array $moduleDefinitions = array())
		{
			return InterfaceSettings::dashboardCatalog(array_merge(self::coreCatalog(), $moduleDefinitions));
		}

		public static function compose(array $stats, array $documents, array $moduleWidgets)
		{
			$items = array(
				array(
					'layout_code' => 'core.system_kpis', 'label' => 'Состояние системы',
					'description' => 'Документы, пользователи, 404 и ошибки базы данных.',
					'icon' => 'ti ti-chart-dots-3', 'sort_order' => 10,
					'template' => '@dashboard/widgets/system-kpis.twig', 'data' => array('stats' => $stats),
				),
				array(
					'layout_code' => 'core.recent_documents', 'label' => 'Недавние документы',
					'description' => 'Последние измененные документы в виде таблицы.',
					'icon' => 'ti ti-file-pencil', 'sort_order' => 20,
					'template' => '@dashboard/widgets/recent-documents.twig', 'data' => array('documents' => $documents),
				),
				array(
					'layout_code' => 'core.shortcuts', 'label' => 'Быстрый доступ',
					'description' => 'Ссылки на основные рабочие разделы системы.',
					'icon' => 'ti ti-bolt', 'sort_order' => 1000,
					'template' => '@dashboard/widgets/shortcuts.twig', 'data' => array(),
				),
			);

			foreach ($moduleWidgets as $widget) {
				$widget['sort_order'] = 100 + (isset($widget['sort_order']) ? (int) $widget['sort_order'] : 100);
				$items[] = $widget;
			}

			return InterfaceSettings::applyDashboard($items);
		}

		protected static function coreCatalog()
		{
			return array(
				array('layout_code' => 'core.system_kpis', 'label' => 'Состояние системы', 'description' => 'Документы, пользователи, 404 и SQL ошибки.', 'icon' => 'ti ti-chart-dots-3', 'sort_order' => 10),
				array('layout_code' => 'core.recent_documents', 'label' => 'Недавние документы', 'description' => 'Таблица последних измененных документов.', 'icon' => 'ti ti-file-pencil', 'sort_order' => 20),
				array('layout_code' => 'core.shortcuts', 'label' => 'Быстрый доступ', 'description' => 'Переходы к основным разделам.', 'icon' => 'ti ti-bolt', 'sort_order' => 1000),
			);
		}
	}
