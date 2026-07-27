# Миграции модуля

← [К разделу «Модули»](README.md)

Управляемый модуль создаёт таблицы и исходные данные при явной установке или
обновлении через панель управления. Production не требует CLI.

## Объявление

```php
'migrations' => array(
    array(
        'id' => '001_create_notes',
        'file' => 'migrations/001_create_notes.sql',
    ),
    array(
        'id' => '002_add_note_color',
        'file' => 'migrations/002_add_note_color.sql',
    ),
	array(
		'id' => '003_normalize_note_settings',
		'file' => 'migrations/003_normalize_note_settings.php',
	),
),
```

`id` уникален внутри модуля и записывается в системный ledger. Уже выполненный
ID второй раз не применяется. Поэтому опубликованную миграцию не редактируют:
любое следующее изменение получает новый файл и новый ID.

## Префиксы таблиц

В SQL нельзя писать префикс конкретного проекта. Используйте плейсхолдеры:

| Плейсхолдер | Назначение |
| --- | --- |
| `{{prefix}}` | Основной префикс текущей установки. |
| `{{content_prefix}}` | Контентные таблицы. |
| `{{catalog_prefix}}` | Таблицы каталога. |
| `{{module_prefix}}` | Таблицы модулей. |
| `{{basket_prefix}}` | Таблицы корзины. |
| `{{contacts_prefix}}` | Таблицы контактных форм. |
| `{{system_prefix}}` | Системные таблицы. |

```sql
CREATE TABLE IF NOT EXISTS `{{prefix}}_admin_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `content` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Идемпотентность

Ledger защищает от повторного запуска после успеха, но миграция всё равно должна
быть насколько возможно идемпотентной. Используйте `IF NOT EXISTS`,
`ON DUPLICATE KEY UPDATE` и проверки в lifecycle-handler, когда SQL зависит от
текущей схемы. Для `ADD COLUMN IF NOT EXISTS` и `ADD INDEX IF NOT EXISTS`
мигратор сам проверяет `information_schema` и отправляет переносимый синтаксис,
поэтому такие миграции работают и на старых версиях MySQL/MariaDB.

Не выполняйте в одной миграции необратимое удаление и долгую массовую обработку
без возможности продолжения. Для больших объёмов предоставьте защищённую
пакетную web-операцию с прогрессом.

## PHP-миграции данных

Если изменение требует разобрать JSON или выполнить условное преобразование
существующих строк, используйте PHP вместо функций конкретной версии MySQL:

```php
<?php

defined('BASEPATH') || die('Direct access to this location is not allowed.');

return function (array $context) {
	$rows = DB::query('SELECT id, settings FROM my_table')->getAll() ?: array();

	foreach ($rows as $row) {
		$settings = \App\Helpers\Json::toArray($row['settings']);
		$settings['enabled'] = true;
		DB::Update('my_table', array(
			'settings' => \App\Helpers\Json::encode($settings),
		), 'id = %i', (int) $row['id']);
	}

	return count($rows) + 1;
};
```

Файл должен вернуть callable. Он получает `module` и `migration` в `$context`
и возвращает неотрицательное количество DB-операций. Мигратор выполняет callback
и запись в ledger в одной транзакции, поэтому не вызывайте внутри `commit()` или
`rollback()` самостоятельно.

## Install и update handlers

SQL подходит не для каждой операции. Descriptor может дополнительно объявить
callable:

```php
'lifecycle' => array(
    'managed' => true,
    'install' => array(Installer::class, 'install'),
    'update' => array(Installer::class, 'update'),
    'uninstall' => array(Installer::class, 'uninstall'),
),
```

Handler нужен для безопасного преобразования данных, создания каталогов или
другой операции, которую нельзя выразить переносимым SQL. Он обязан бросить
исключение при ошибке: менеджер не должен отмечать операцию успешной.

## Деинсталляция

Простой вариант:

```php
'lifecycle' => array(
    'managed' => true,
    'uninstall' => array('migrations/uninstall.sql'),
),
```

```sql
DROP TABLE IF EXISTS `{{prefix}}_admin_notes`;
```

Права модуля синхронизируются менеджером и удаляются штатной деинсталляцией.
Собственный uninstall-файл обязан удалить принадлежащие модулю таблицы и данные.
Общие настройки и локальные секреты удаляет менеджер модулей. Для сохранения
данных используется выключение, а не деинсталляция.

`reinstall` сначала полностью удаляет данные через uninstall, а затем выполняет
чистую установку. Не предлагайте эту кнопку как безопасное исправление без backup.

## Проверка миграции

- SQL не содержит префикс проекта;
- таблицы создаются в InnoDB и нужной кодировке;
- у новых таблиц есть первичные ключи и индексы под реальные запросы;
- новая версия не меняет уже опубликованный migration ID;
- уже применённый ID не выполняется повторно; новая официальная версия файла
  обновляет checksum ledger без повторного изменения данных;
- ошибка останавливает установку и видна администратору;
- uninstall проверен отдельно на тестовой копии;
- перед необратимым обновлением сделан backup.

Состояния и блокировки операций описаны в [Операциях модуля](operations.md).
