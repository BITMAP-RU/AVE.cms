<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/HookCatalog.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Registry of stable extension points exposed by the framework and modules. */
	class HookCatalog
	{
		protected static $definitions = array();
		protected static $booted = false;

		public static function register($name, array $definition = array(), $source = 'core')
		{
			$name = trim((string) $name);
			if ($name === '') {
				return;
			}

			self::boot();
			$current = isset(self::$definitions[$name]) ? self::$definitions[$name] : array();
			self::$definitions[$name] = array_merge(array(
				'name' => $name,
				'kind' => 'action',
				'domain' => self::domain($name),
				'description' => '',
				'context' => LifecycleEvent::class,
				'mutable' => true,
				'source' => (string) $source,
				'observed' => false,
			), $current, $definition, array('name' => $name));
		}

		public static function registerMany(array $definitions, $source = 'module')
		{
			foreach ($definitions as $key => $definition) {
				if (is_string($definition)) {
					self::register($definition, array(), $source);
					continue;
				}

				if (!is_array($definition)) {
					continue;
				}

				$name = isset($definition['name']) ? $definition['name'] : (is_string($key) ? $key : '');
				unset($definition['name']);
				self::register($name, $definition, $source);
			}
		}

		public static function observe($name, $kind)
		{
			self::boot();
			if (!isset(self::$definitions[$name])) {
				self::register($name, array('kind' => (string) $kind, 'description' => 'Динамическое событие'), 'runtime');
			}

			self::$definitions[$name]['observed'] = true;
			self::$definitions[$name]['kind'] = (string) $kind;
		}

		public static function get($name)
		{
			self::boot();
			return isset(self::$definitions[$name]) ? self::$definitions[$name] : null;
		}

		public static function all()
		{
			self::boot();
			ksort(self::$definitions, SORT_NATURAL | SORT_FLAG_CASE);
			return array_values(self::$definitions);
		}

		protected static function boot()
		{
			if (self::$booted) {
				return;
			}

			self::$booted = true;

			self::registerDefaults(array(
				'system.initialize' => 'Инициализация системного runtime завершена',
				'module.registered' => 'Дескриптор модуля зарегистрирован',
				'module.installed' => 'Модуль установлен',
				'module.updated' => 'Модуль обновлен',
				'module.enabled' => 'Модуль включен',
				'module.disabled' => 'Модуль выключен',
				'module.uninstalling' => 'Модуль готовится к удалению',
				'module.uninstalled' => 'Модуль удален',
				'frontend.request.received' => 'Начало публичного HTTP-запроса',
				'frontend.runtime.ready' => 'Публичное окружение и модули загружены',
				'frontend.route.resolved' => 'Публичный маршрут разрешен',
				'frontend.response.rendering' => 'Ответ подготовлен перед финальной выдачей',
				'frontend.response.rendered' => 'Финальный HTML публичного ответа сформирован',
				'content.document.loading' => 'Документ готовится к загрузке',
				'content.document.loaded' => 'Документ загружен',
				'content.document.saving' => 'Документ готовится к сохранению',
				'content.document.saved' => 'Документ сохранен',
				'content.document.created' => 'Документ создан',
				'content.document.updated' => 'Документ изменен',
				'content.document.published' => 'Документ опубликован',
				'content.document.unpublished' => 'Документ снят с публикации',
				'content.document.deleting' => 'Документ готовится к удалению',
				'content.document.deleted' => 'Документ удален',
				'content.document.snapshot_built' => 'JSON-снимок документа перестроен',
				'content.rubric.loading' => 'Рубрика готовится к загрузке',
				'content.rubric.loaded' => 'Рубрика загружена',
				'content.query.loading' => 'Запрос AVE готовится к загрузке',
				'content.query.loaded' => 'Запрос AVE загружен',
				'content.query.rendering' => 'Запрос AVE готовится к рендеру',
				'content.query.rendered' => 'Запрос AVE отрендерен',
				'content.template.loading' => 'Шаблон страницы готовится к загрузке',
				'content.template.loaded' => 'Шаблон страницы загружен',
				'content.template.rendering' => 'Шаблон страницы готовится к выполнению',
				'content.template.rendered' => 'Шаблон страницы выполнен',
				'content.block.loading' => 'Визуальный блок готовится к загрузке',
				'content.block.loaded' => 'Визуальный блок загружен',
				'content.block.rendering' => 'Визуальный блок готовится к рендеру',
				'content.block.rendered' => 'Визуальный блок отрендерен',
				'content.sysblock.loading' => 'Системный блок готовится к загрузке',
				'content.sysblock.loaded' => 'Системный блок загружен',
				'content.sysblock.rendering' => 'Системный блок готовится к рендеру',
				'content.sysblock.rendered' => 'Системный блок отрендерен',
				'content.field.registry' => 'Нативные и модульные типы полей зарегистрированы',
				'content.field.normalizing' => 'Значение поля готовится к нормализации',
				'content.field.normalized' => 'Значение поля нормализовано',
				'content.field.validating' => 'Значение поля готовится к проверке',
				'content.field.validated' => 'Значение поля проверено',
				'content.field.saving' => 'Значение поля готовится к сохранению',
				'content.field.saved' => 'Значение поля сохранено',
				'content.field.rendering' => 'Поле готовится к рендеру',
				'content.field.rendered' => 'Поле отрендерено',
				'auth.user.registered' => 'Публичный пользователь зарегистрирован',
				'auth.user.registering' => 'Публичный пользователь готовится к регистрации',
				'auth.user.authenticated' => 'Публичный пользователь вошел',
				'auth.user.logged_out' => 'Публичный пользователь вышел',
				'auth.phone.code_sent' => 'Код входа отправлен на телефон',
				'auth.account.links' => 'Модули дополняют ссылки личного кабинета',
				'auth.account.overview' => 'Модули дополняют обзор личного кабинета',
				'commerce.cart.changed' => 'Состав корзины изменен',
				'commerce.order.creating' => 'Заказ готовится к созданию',
				'commerce.order.created' => 'Заказ создан',
				'commerce.order.status_changed' => 'Состояние заказа изменено',
				'commerce.payment.completed' => 'Платеж завершен',
				'commerce.delivery.quoted' => 'Стоимость доставки рассчитана',
				'cache.invalidating' => 'Кеш готовится к сбросу',
				'cache.invalidated' => 'Кеш сброшен',
				'file.upload.validating' => 'Загружаемый файл проверяется перед сохранением',
				'file.upload.stored' => 'Загруженный файл сохранён',
			));

			self::$definitions['module.registered']['context'] = 'string';
			self::$definitions['module.registered']['mutable'] = false;
			self::$definitions['file.upload.validating']['kind'] = 'filter';
			self::$definitions['file.upload.validating']['context'] = 'array';
			self::$definitions['file.upload.stored']['context'] = 'array';
			self::$definitions['auth.account.links']['kind'] = 'filter';
			self::$definitions['auth.account.links']['context'] = 'array<int,array{url:string,label:string}>';
			self::$definitions['auth.account.overview']['kind'] = 'filter';
			self::$definitions['auth.account.overview']['context'] = 'array{user_id:int,preview:bool,extensions:array}';
			foreach (array(
				'content.document.saving',
				'content.document.saved',
				'content.document.created',
				'content.document.updated',
				'content.document.published',
				'content.document.unpublished',
			) as $name) {
				self::$definitions[$name]['context'] = \App\Content\Documents\DocumentSaveEvent::class;
			}
		}

		protected static function registerDefaults(array $definitions)
		{
			foreach ($definitions as $name => $description) {
				self::$definitions[$name] = array(
					'name' => $name,
					'kind' => 'action',
					'domain' => self::domain($name),
					'description' => $description,
					'context' => LifecycleEvent::class,
					'mutable' => true,
					'source' => 'core',
					'observed' => false,
				);
			}
		}

		protected static function domain($name)
		{
			$parts = explode('.', (string) $name, 2);
			return isset($parts[0]) ? $parts[0] : 'application';
		}
	}
