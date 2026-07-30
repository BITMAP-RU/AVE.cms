<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Common/AuditLog.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Common;

	use DB;
	use App\Helpers\Date;
	use App\Helpers\Json;
	use App\Helpers\Request;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/**
	 * Общий журнал аудита технических и административных действий (Этап 8).
	 *
	 * Отдельно от бизнес-истории задач (App\Marketplace\Models\History): здесь
	 * фиксируются действия над ролями, настройками, полное удаление, очистка
	 * истории и т.п. — кто, что, над чем, детали (before/after), IP, UA, когда.
	 */
	class AuditLog
	{
		public static function table()
		{
			return SystemTables::table('audit_log');
		}

		/**
		 * Записать событие аудита.
		 *
		 * @param string $action  Код действия, напр. 'role.updated', 'settings.updated'.
		 * @param array  $ctx     actor_id|actor_name|target_type|target_id|meta(array)
		 */
		public static function record($action, array $ctx = [])
		{
			try {
				DB::Insert(self::table(), [
					'actor_id' => !empty($ctx['actor_id']) ? (int) $ctx['actor_id'] : null,
					'actor_name' => isset($ctx['actor_name']) ? (string) $ctx['actor_name'] : '',
					'action' => (string) $action,
					'target_type' => isset($ctx['target_type']) ? (string) $ctx['target_type'] : '',
					'target_id' => !empty($ctx['target_id']) ? (int) $ctx['target_id'] : null,
					'meta' => isset($ctx['meta']) && $ctx['meta'] !== null ? Json::encode($ctx['meta'], JSON_UNESCAPED_UNICODE) : null,
					'ip' => substr((string) Request::ip(), 0, 45),
					'user_agent' => substr((string) Request::userAgent(), 0, 255),
					'created_at' => Date::nowSql(),
				]);
			} catch (\Throwable $e) {
				error_log('Audit log write failed: ' . $e->getMessage());
				return false;
			}

			return true;
		}

		/** Последние записи журнала. */
		public static function recent($limit = 100)
		{
			$limit = max(1, min(1000, (int) $limit));
			$rows = DB::query(
				"SELECT * FROM " . self::table() . " ORDER BY created_at DESC, id DESC LIMIT " . $limit
			)->getAll();
			return $rows ?: [];
		}

		/** Человекочитаемые названия действий (чистая карта, для UI и тестов). */
		public static function actionLabels()
		{
			return [
				'role.created' => 'Роль создана',
				'role.updated' => 'Роль изменена',
				'role.copied' => 'Роль скопирована',
				'role.deleted' => 'Роль удалена',
				'user.bulk_status_updated' => 'Состояние пользователей изменено',
				'settings.updated' => 'Настройки изменены',
				'settings.interface_updated' => 'Интерфейс изменен',
				'settings.security_updated' => 'Настройки безопасности изменены',
				'settings.checklist_updated' => 'Чек-лист приёмки изменён',
				'settings.checklist_reset' => 'Чек-лист приёмки сброшен',
				'rubric.created' => 'Рубрика создана',
				'rubric.updated' => 'Рубрика изменена',
				'rubric.field_type_enabled' => 'Тип поля включён',
				'rubric.field_type_disabled' => 'Тип поля отключён',
				'rubric.field_preset_applied' => 'Стартовый набор полей применён',
				'directory.created' => 'Справочник создан',
				'directory.updated' => 'Справочник изменён',
				'directory.deleted' => 'Справочник удалён',
				'directory.item_created' => 'Значение справочника добавлено',
				'directory.item_updated' => 'Значение справочника изменено',
				'directory.item_deleted' => 'Значение справочника удалено',
				'document.creation_preset_created' => 'Пресет создания документа добавлен',
				'document.creation_preset_deleted' => 'Пресет создания документа удалён',
				'block.created' => 'Блок создан',
				'block.updated' => 'Блок изменён',
				'block.copied' => 'Блок скопирован',
				'block.deleted' => 'Блок удалён',
				'block.revision_restored' => 'Ревизия блока восстановлена',
				'section_access.updated' => 'Доступ к разделам изменён',
				'category.created' => 'Группа товаров создана',
				'category.updated' => 'Группа товаров изменена',
				'category.deleted' => 'Группа товаров удалена',
				'content.document.deleted' => 'Документ удалён',
				'trash.restored' => 'Сущность восстановлена из корзины',
				'trash.purged' => 'Сущность физически удалена',
				'task.deleted_full' => 'Задача полностью удалена',
				'history.cleared' => 'История товара очищена',
				'module.enabled' => 'Модуль включён',
				'module.disabled' => 'Модуль отключён',
				'module.installed' => 'Модуль установлен',
				'module.updated' => 'Модуль обновлён',
				'module.reinstalled' => 'Модуль переустановлен',
				'module.uninstalled' => 'Модуль деинсталлирован',
				'module.archive_installed' => 'Модуль установлен из ZIP',
				'module.presentation_updated' => 'Размещение модуля изменено',
				'module.template_updated' => 'Шаблон модуля изменён',
					'module.runtime_updated' => 'Frontend runtime модуля изменён',
					'core_update.repository_settings' => 'Источник обновлений ядра изменён',
					'core_update.prepared' => 'Патч ядра подготовлен',
					'core_update.downloaded' => 'Патч ядра загружен',
					'core_update.completed' => 'Ядро обновлено',
					'core_update.failed' => 'Ошибка обновления ядра',
					'core_update.rolled_back' => 'Обновление ядра отменено',
					'core_update.discarded' => 'Подготовленный патч ядра отменён',
				'benchmark.completed' => 'Тест производительности завершён',
				'benchmark.failed' => 'Ошибка теста производительности',
				'benchmark.deleted' => 'Результат теста производительности удалён',
				'benchmark.history_cleared' => 'История тестов производительности очищена',
				'console.snippet_saved' => 'Сниппет консоли сохранён',
				'console.snippet_deleted' => 'Сниппет консоли удалён',
				'catalog.item_created' => 'Раздел каталога создан',
				'catalog.item_saved' => 'Раздел каталога изменён',
				'catalog.item_deleted' => 'Раздел каталога удалён',
				'catalog.reordered' => 'Порядок каталога изменён',
				'catalog.settings_saved' => 'Настройки каталога изменены',
				'catalog.product_shipping_saved' => 'Упаковка товара изменена',
				'catalog.product_shipping_copied' => 'Упаковка скопирована вариантам',
				'catalog.product_shipping_bulk_status' => 'Расчёт доставки у товаров изменён',
				'catalog.shipping_template_created' => 'Шаблон упаковки создан',
				'catalog.shipping_template_saved' => 'Шаблон упаковки изменён',
				'catalog.shipping_template_deleted' => 'Шаблон упаковки удалён',
				'catalog.variant_selector_settings_saved' => 'Вид выбора вариантов изменён',
				'catalog.bed_attributes_imported' => 'Характеристики кроватей заполнены из прайс-листа',
				'commerce.order_created' => 'Заказ создан менеджером',
				'commerce.order_composition_updated' => 'Состав заказа изменён',
				'console.executed' => 'Код в системной консоли выполнен',
				'console.execution_failed' => 'Ошибка системной консоли',
				'document_job.created' => 'Операция с документами создана',
				'document_job.saved' => 'Операция с документами изменена',
				'document_job.deleted' => 'Операция с документами удалена',
				'document_job.started' => 'Операция с документами запущена',
				'document_job.completed' => 'Операция с документами завершена',
				'document_job.cancelled' => 'Операция с документами остановлена',
				'document_job.failed' => 'Ошибка операции с документами',
				'document_job.logs_cleared' => 'Журнал операции очищен',
				'document_jobs.logs_cleared' => 'Журналы операций очищены',
				'file_security.scan_started' => 'Проверка файлов запущена',
				'file_security.scan_completed' => 'Проверка файлов завершена',
				'file_security.scan_cancelled' => 'Проверка файлов остановлена',
				'file_security.settings_updated' => 'Настройки защиты файлов изменены',
				'file_security.finding_ignore' => 'Находка проверки проигнорирована',
				'file_security.finding_reopen' => 'Находка проверки возвращена в работу',
				'file_security.finding_trust' => 'Файл добавлен в доверенную базу',
				'file_security.finding_quarantine' => 'Файл помещён в карантин',
				'file_security.finding_restore' => 'Файл восстановлен из карантина',
				'scheduler.task_run' => 'Задача планировщика запущена',
				'scheduler.task_cancelled' => 'Задача планировщика остановлена',
				'scheduler.task_updated' => 'Расписание задачи изменено',
				'scheduler.token_rotated' => 'Адрес планировщика обновлён',
				'search.settings_updated' => 'Настройки поиска изменены',
				'search.reindex_started' => 'Переиндексация поиска запущена',
				'search.reindex_completed' => 'Переиндексация поиска завершена',
				'reviews.settings_updated' => 'Настройки отзывов изменены',
				'theme.created' => 'Тема создана',
				'theme.imported' => 'Тема импортирована',
				'theme.activated' => 'Тема активирована',
				'theme.deleted' => 'Тема удалена',
				'theme.asset_created' => 'Файл темы создан',
				'theme.asset_saved' => 'Файл темы изменён',
				'theme.asset_deleted' => 'Файл темы удалён',
				'theme.assets_uploaded' => 'Файлы темы загружены',
				'theme.folder_created' => 'Каталог темы создан',
				'theme.manifest_saved' => 'Подключения темы изменены',
				'theme.settings_saved' => 'Настройки темы изменены',
				'theme.revision_restored' => 'Ревизия файла темы восстановлена',
				'theme.revision_deleted' => 'Ревизия файла темы удалена',
				'theme.revisions_cleared' => 'Ревизии темы очищены',
				'events.audit_cleared' => 'Журнал аудита очищен',
				'database.backup_restored' => 'База данных восстановлена из резервной копии',
				'database.backup_restore_failed' => 'Ошибка восстановления базы данных',
				'auth.reauth_confirmed' => 'Пароль для чувствительной операции подтверждён',
				'auth.reauth_failed' => 'Неудачное подтверждение пароля',
				'auth.phone_registered' => 'Пользователь зарегистрирован по телефону',
				'auth.phone_authenticated' => 'Пользователь вошёл по телефону',
				'mcp.connection_created' => 'MCP-подключение создано',
				'mcp.connection_revoked' => 'MCP-подключение отозвано',
				'mcp.read_called' => 'Выполнен read-only вызов MCP',
			'smsc_auth.settings_updated' => 'Настройки входа по SMS изменены',
				'smsc_auth.test_sent' => 'Отправлено тестовое SMS',
				'reliable_events.subscription_saved' => 'Webhook-подписка сохранена',
				'reliable_events.subscription_deleted' => 'Webhook-подписка удалена',
			];
		}

		public static function actionLabel($action)
		{
			$labels = self::actionLabels();
			return isset($labels[$action]) ? $labels[$action] : (string) $action;
		}
	}
