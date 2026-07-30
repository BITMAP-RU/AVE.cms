<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/modules/Modules/module.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	$codeRoute = function ($reason) {
		return array(
			'permission' => 'install_module_code',
			'sensitive' => 'module.code',
			'reauth' => array('reason' => (string) $reason),
		);
	};

	return array(
		'code' => 'modules',
		'name' => 'Модули',
		'version' => '0.1.3',

		'settings' => array(
			'repository_enabled' => array('label' => 'Удалённый каталог модулей', 'type' => 'bool', 'default' => false, 'group' => 'modules_repository'),
			'repository_url' => array('label' => 'URL подписанного каталога', 'type' => 'string', 'default' => '', 'group' => 'modules_repository'),
			'repository_public_key' => array('label' => 'Публичный ключ каталога', 'type' => 'code', 'default' => '', 'group' => 'modules_repository', 'sensitive' => true),
		),

		'permissions' => array(
			'key' => 'modules',
			'items' => array(
				array(
					'code' => 'view_modules',
					'group_code' => 'navigation',
					'name' => 'Модули: просмотр',
					'description' => 'Просмотр установленных и доступных легаси-модулей.',
					'sort_order' => 10,
				),
				array(
					'code' => 'manage_modules',
					'group_code' => 'admin',
					'name' => 'Модули: управление',
					'description' => 'Загрузка ZIP, установка, настройка размещения, обновление, включение и деинсталляция модулей.',
					'sort_order' => 20,
				),
				array(
					'code' => 'install_module_code',
					'group_code' => 'admin',
					'name' => 'Модули: установка кода',
					'description' => 'Загрузка и исполнение официальных ZIP-пакетов AVE.cms.',
					'sort_order' => 30,
				),
			),
			'icon' => 'ti ti-puzzle',
			'priority' => 45,
		),

		'navigation' => array(
			array(
				'code' => 'modules',
				'label' => 'Модули',
				'url' => '#',
				'icon' => 'ti ti-puzzle',
				'permission' => '',
				'group' => 'Система',
				'sort_order' => 45,
				'match' => array(
					'/modules',
					'/system/document-jobs',
				),
			),
			array(
				'code' => 'modules_registry',
				'label' => 'Управление',
				'url' => '/modules/',
				'exact' => true,
				'permission' => 'view_modules',
				'group' => 'Система',
				'parent' => 'modules',
				'sort_order' => 1,
			),
		),
		'routes' => array(
			array('GET', '/modules', array(\App\Adminx\Modules\Controller::class, 'index')),
			array('GET', '/modules/', array(\App\Adminx\Modules\Controller::class, 'index')),
			array('POST', '/modules/archive/install', array(\App\Adminx\Modules\Controller::class, 'installArchive'), array(
				'permission' => 'install_module_code',
				'sensitive' => 'module.code',
				'reauth' => array('reason' => 'ZIP-пакет добавит в систему новый исполняемый код.'),
			)),
			array('POST', '/modules/repository/settings', array(\App\Adminx\Modules\Controller::class, 'saveRepositorySettings'), $codeRoute('Настройка источника определяет, откуда система получает исполняемый код.')),
			array('POST', '/modules/repository/settings/official', array(\App\Adminx\Modules\Controller::class, 'restoreOfficialRepository'), $codeRoute('Официальный профиль заменит текущий URL и открытый ключ каталога модулей.')),
			array('POST', '/modules/repository/refresh', array(\App\Adminx\Modules\Controller::class, 'refreshRepository')),
			array('POST', '/modules/repository/{code}/install', array(\App\Adminx\Modules\Controller::class, 'installRepository'), $codeRoute('Подписанный ZIP будет загружен, проверен и установлен вместе с миграциями.')),
			array('POST', '/modules/lifecycle/{code}/presentation', array(\App\Adminx\Modules\Controller::class, 'presentation')),
			array('POST', '/modules/lifecycle/{code}/toggle', array(\App\Adminx\Modules\Controller::class, 'toggle')),
			array('POST', '/modules/lifecycle/{code}/preflight/{operation}', array(\App\Adminx\Modules\Controller::class, 'preflight')),
			array('POST', '/modules/lifecycle/{code}/install', array(\App\Adminx\Modules\Controller::class, 'install'), $codeRoute('Установка выполнит миграции и код пакета.')),
			array('POST', '/modules/lifecycle/{code}/update', array(\App\Adminx\Modules\Controller::class, 'update'), $codeRoute('Обновление выполнит миграции и код пакета.')),
			array('POST', '/modules/lifecycle/{code}/reinstall', array(\App\Adminx\Modules\Controller::class, 'reinstall'), $codeRoute('Переустановка удалит данные и повторно выполнит код пакета.')),
			array('POST', '/modules/lifecycle/{code}/repair', array(\App\Adminx\Modules\Controller::class, 'repair'), $codeRoute('Восстановление повторно выполнит незавершённый этап установки.')),
			array('POST', '/modules/lifecycle/{code}/uninstall', array(\App\Adminx\Modules\Controller::class, 'uninstall'), $codeRoute('Деинсталляция выполнит код удаления и уничтожит данные модуля.')),
			array('POST', '/modules/lifecycle/{code}/remove', array(\App\Adminx\Modules\Controller::class, 'remove'), $codeRoute('Файлы исполняемого кода модуля будут физически удалены.')),
		),

		'view_globals' => array(
			'module_code' => 'modules',
		),
	);
