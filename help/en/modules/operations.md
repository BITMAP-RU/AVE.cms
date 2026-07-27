# Module operations and states

← [To the “Modules” section](README.md)

The controlled module passes through the states `available`, `enabled` and `disabled`.
The presence of a folder does not mean that the code is active.

## Operations in the control panel

| Operation | What's going on |
| --- | --- |
| Install | Dependencies are checked, migrations and install-handler are applied, permissions are synchronized, the module is enabled. |
| Update | New migrations and update-handler are executed; enabled/disabled is saved. |
| Turn off | Routes, menus and context hooks are disabled; tables and settings are saved. |
| Enable | Dependencies are checked, then runtime contributions are reconnected. |
| Uninstall | Dependent modules and field types are checked, uninstall is performed, data, settings, secrets and rights are deleted. |
| Reinstall | Completely removes the module with its data and immediately performs a clean installation. |
| Restore | Repeats the interrupted operation; migrations that have already been applied are skipped through the log. |
| Delete files | After uninstallation, the permitted administrative module or composite package is physically removed. |

All modifying operations require CSRF and administrative rights. On production
they are executed from **System → Modules**, no CLI required.

## Dependencies

```php
'requires' => array('commerce', 'users'),
```

- installation and enablement is impossible without installed and enabled dependencies;
- shutdown or uninstallation is blocked by active dependent modules;
- the installation order is determined by the module manager;
- cyclic dependencies are not allowed.

## Switched off module

To prevent existing data from becoming unreadable, common config/settings remain,
top-level files and services, event definitions, and field type handlers.
Administrative and public routes, menus, assets and
context handlers from sections `admin`/`public`.

For a field of a disabled module, creation is blocked, but an already existing field
continues to be read and stored.

## Removal restrictions

Uninstallation will stop if:

- another included package depends on the module;
- its field type is used in at least one category;
- uninstall-handler failed.

Physically deleting files is a separate operation. It is only allowed
for an uninstalled module with `package.removable = true`. Two are allowed
protected root: `<admin-directory>/modules/<Folder>` for independent
administrative module and `modules/<code>` for the composite package. Together with
The folder clears secrets, settings, ledger migrations and module state records.
The path is checked via `realpath`; remove parent directory or built-in
This action cannot be used to partition the kernel.

## Reinstallation and partial error

`reinstall` causes a complete uninstall and deletes user data
module. Before it you need a backup. If the operation is aborted, the module receives
status `dirty`; runtime does not connect until recovery is successful.

If the ZIP passes the test but the installation migration fails, the files
are saved. After eliminating the cause, click “Restore”: ledger will not work
re-run already completed migrations.
