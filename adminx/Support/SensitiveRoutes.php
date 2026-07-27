<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         adminx/Support/SensitiveRoutes.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Adminx\Support;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use InvalidArgumentException;

	/** Metadata contract and runtime inventory for high-risk Adminx routes. */
	class SensitiveRoutes
	{
		protected static $operations = array(
			'module.code' => 'Установка или изменение исполняемого кода модуля',
			'core.update' => 'Установка подписанного обновления ядра',
			'stored_php.execute' => 'Выполнение сохранённого PHP-кода',
			'stored_php.write' => 'Сохранение PHP-кода, исполняемого runtime',
			'theme_assets.write' => 'Изменение исполняемых CSS/JavaScript и файлов публичной темы',
			'legacy_migration.write' => 'Изменение реквизитов исходной системы',
			'legacy_migration.execute' => 'Очистка стартовых данных и миграция старой AVE.cms',
			'database.restore' => 'Полная замена рабочей схемы данными из резервной копии',
		);
		protected static $required = array(
			'POST /modules/archive/install' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/install' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/update' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/reinstall' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/repair' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/uninstall' => array('module.code', 'install_module_code'),
			'POST /modules/lifecycle/{code}/remove' => array('module.code', 'install_module_code'),
			'POST /system/updates/settings' => array('core.update', 'install_core_updates'),
			'POST /system/updates/upload' => array('core.update', 'install_core_updates'),
			'POST /system/updates/download/{id}' => array('core.update', 'install_core_updates'),
			'POST /system/updates/jobs/{id}/step' => array('core.update', 'install_core_updates'),
			'POST /system/updates/jobs/{id}/rollback' => array('core.update', 'install_core_updates'),
			'POST /system/console/execute' => array('stored_php.execute', 'execute_console'),
			'POST /blocks' => array('stored_php.write', 'manage_blocks'),
			'POST /blocks/{id}' => array('stored_php.write', 'manage_blocks'),
			'POST /blocks/{id}/copy' => array('stored_php.write', 'manage_blocks'),
			'POST /blocks/revisions/{revision}/restore' => array('stored_php.write', 'manage_blocks'),
			'POST /templates' => array('stored_php.write', 'manage_templates'),
			'POST /templates/{id}' => array('stored_php.write', 'manage_templates'),
			'POST /templates/{id}/copy' => array('stored_php.write', 'manage_templates'),
			'POST /templates/revisions/{revision}/restore' => array('stored_php.write', 'manage_templates'),
			'POST /rubrics' => array('stored_php.write', 'manage_rubrics'),
			'POST /rubrics/{id}' => array('stored_php.write', 'manage_rubrics'),
			'POST /rubrics/{id}/templates/main' => array('stored_php.write', 'manage_rubrics'),
			'POST /rubrics/{id}/templates' => array('stored_php.write', 'manage_rubrics'),
			'POST /rubrics/templates/{template}' => array('stored_php.write', 'manage_rubrics'),
			'POST /requests' => array('stored_php.write', 'manage_requests'),
			'POST /requests/{id}' => array('stored_php.write', 'manage_requests'),
			'POST /requests/{id}/copy' => array('stored_php.write', 'manage_requests'),
			'POST /requests/{id}/conditions' => array('stored_php.write', 'manage_requests'),
			'POST /navigation' => array('stored_php.write', 'manage_navigation'),
			'POST /navigation/{id}' => array('stored_php.write', 'manage_navigation'),
			'POST /navigation/{id}/copy' => array('stored_php.write', 'manage_navigation'),
			'POST /modules/content-packages/import' => array('stored_php.write', 'import_content_packages'),
			'POST /system/document-jobs' => array('stored_php.write', 'manage_document_jobs'),
			'POST /system/document-jobs/{id}' => array('stored_php.write', 'manage_document_jobs'),
			'POST /system/document-jobs/{id}/start' => array('stored_php.execute', 'execute_document_jobs'),
			'POST /system/document-jobs/{id}/step' => array('stored_php.execute', 'execute_document_jobs'),
			'POST /themes/file' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/files/create' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/upload' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/path/delete' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/manifest' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/create' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/activate' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/import' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/delete' => array('theme_assets.write', 'manage_themes'),
			'POST /themes/revisions/{id}/restore' => array('theme_assets.write', 'manage_themes'),
			'POST /modules/legacy-migration/profiles' => array('legacy_migration.write', 'manage_legacy_migration'),
			'POST /modules/legacy-migration/profiles/{id}/delete' => array('legacy_migration.write', 'manage_legacy_migration'),
			'POST /modules/legacy-migration/runs/{id}/start' => array('legacy_migration.execute', 'run_legacy_migration'),
			'POST /modules/legacy-migration/runs/{id}/rollback' => array('legacy_migration.execute', 'run_legacy_migration'),
			'POST /modules/legacy-migration/runs/{id}/files' => array('legacy_migration.execute', 'run_legacy_migration'),
			'POST /database/backup/restore' => array('database.restore', 'manage_database'),
		);

		public static function validate(array $route)
		{
			$options = isset($route['options']) && is_array($route['options']) ? $route['options'] : array();
			$operation = isset($options['sensitive']) ? trim((string) $options['sensitive']) : '';
			$key = self::routeKey($route);
			$required = isset(self::$required[$key]) ? self::$required[$key] : null;
			if ($operation === '' && !$required) {
				return;
			}

			if ($required && ($operation !== $required[0]
				|| !isset($options['permission'])
				|| (string) $options['permission'] !== $required[1])) {
				throw new InvalidArgumentException('Нарушена политика чувствительного маршрута ' . $key);
			}

			if (!isset(self::$operations[$operation])) {
				throw new InvalidArgumentException('Неизвестная чувствительная операция: ' . $operation);
			}

			$method = isset($route['method']) ? strtoupper((string) $route['method']) : '';
			if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
				throw new InvalidArgumentException('Чувствительный маршрут не может использовать безопасный HTTP-метод');
			}

			if (empty($options['permission']) || empty($options['reauth'])) {
				throw new InvalidArgumentException('Чувствительный маршрут обязан объявить permission и reauth');
			}
		}

		public static function inventory(array $routes)
		{
			$items = array();
			foreach ($routes as $route) {
				self::validate($route);
				$options = isset($route['options']) && is_array($route['options']) ? $route['options'] : array();
				if (empty($options['sensitive'])) {
					continue;
				}

				$items[] = array(
					'method' => isset($route['method']) ? (string) $route['method'] : '',
					'pattern' => isset($route['pattern']) ? (string) $route['pattern'] : '',
					'operation' => (string) $options['sensitive'],
					'permission' => (string) $options['permission'],
					'reauth' => $options['reauth'],
				);
			}

			return $items;
		}

		public static function required()
		{
			return self::$required;
		}

		protected static function routeKey(array $route)
		{
			return strtoupper(isset($route['method']) ? (string) $route['method'] : '')
				. ' '
				. (isset($route['pattern']) ? (string) $route['pattern'] : '');
		}
	}
