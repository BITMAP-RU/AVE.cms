# Module migrations

← [To the “Modules” section](README.md)

The managed module creates tables and source data when explicitly installed or
update via the control panel. Production does not require a CLI.

## Announcement

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

`id` is unique within the module and is written to the system ledger. Already completed
ID is not applied a second time. Therefore, the published migration is not edited:
any subsequent change gets a new file and a new ID.

## Table prefixes

In SQL you cannot write a project-specific prefix. Use placeholders:

| Placeholder | Destination |
| --- | --- |
| `{{prefix}}` | The main prefix of the current installation. |
| `{{content_prefix}}` | Content tables. |
| `{{catalog_prefix}}` | Catalog tables. |
| `{{module_prefix}}` | Module tables. |
| `{{basket_prefix}}` | Cart tables. |
| `{{contacts_prefix}}` | Contact form tables. |
| `{{system_prefix}}` | System tables. |

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

## Idempotency

Ledger protects against restarting after success, but migration should still
be as idempotent as possible. Use `IF NOT EXISTS`,
`ON DUPLICATE KEY UPDATE` and checks in lifecycle-handler when SQL depends on
current scheme. For `ADD COLUMN IF NOT EXISTS` and `ADD INDEX IF NOT EXISTS`
the migrator itself checks `information_schema` and sends the portable syntax,
therefore, such migrations also work on older versions of MySQL/MariaDB.

Don't perform permanent deletion and long bulk processing in the same migration
without the possibility of continuation. For large volumes, provide a protected
batch web operation with progress.

## PHP data migration

If the change requires parsing JSON or performing conditional conversion
existing strings, use PHP instead of MySQL version specific functions:

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

The file should return a callable. It gets `module` and `migration` into `$context`
and returns a non-negative number of DB operations. Migrator executes callback
and writing to the ledger in one transaction, so don't call `commit()` or
`rollback()` independently.

## Install and update handlers

SQL is not suitable for every operation. Descriptor can optionally declare
callable:

```php
'lifecycle' => array(
    'managed' => true,
    'install' => array(Installer::class, 'install'),
    'update' => array(Installer::class, 'update'),
    'uninstall' => array(Installer::class, 'uninstall'),
),
```

Handler is needed to securely transform data, create directories, or
another operation that cannot be expressed in portable SQL. He must quit
exception on error: The manager should not mark the operation as successful.

## Uninstallation

Simple option:

```php
'lifecycle' => array(
    'managed' => true,
    'uninstall' => array('migrations/uninstall.sql'),
),
```

```sql
DROP TABLE IF EXISTS `{{prefix}}_admin_notes`;
```

The module rights are synchronized by the manager and removed by standard uninstallation.
Its own uninstall file must remove tables and data belonging to the module.
General settings and local secrets are deleted by the module manager. To save
data shutdown is used, not uninstallation.

`reinstall` first completely removes data via uninstall and then executes
clean installation. Do not offer this button as a safe fix without backup.

## Migration check

- SQL does not contain a project prefix;
- tables are created in InnoDB and the required encoding;
- new tables have primary keys and indexes for real queries;
- the new version does not change the already published migration ID;
- an already applied ID is not executed again; new official version of the file
  updates the checksum ledger without changing the data again;
- the error stops the installation and is visible to the administrator;
- uninstall was tested separately on a test copy;
- a backup was made before the irreversible update.

The states and blocking operations are described in [Module Operations](operations.md).
