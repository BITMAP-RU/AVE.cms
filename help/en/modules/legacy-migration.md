# Migration from old AVE.cms

← [Back to the “Modules” section](README.md)

Module `legacy_migration` transfers the contents of the old AVE.cms to
clean native installation. It is not a normal import: ID
documents, categories, fields, templates and relationships are preserved.

## When to use

The migration is designed for a new installation with two starting
documents. On a site where working content has already been created, the launch will be
blocked. For repeatable CSV, Excel or XML download, use
["Import documents"](document-import.md).

## Operating procedure

1. Create a separate empty AVE.cms and install the module.
2. Open **Modules → Migration AVE.cms** and add access to the old database.
3. Run the analysis. It reads the schema and counters, but does not change both databases.
4. Fix criticisms: missing modules, tables or field types.
5. Start transfer. Before clearing the startup content, the module will create
   a compressed JSONL copy of the affected tables, settings, and system users.
6. Wait for the number of rows to be verified.
7. Zip the old `uploads/` and upload it to the completed launch.
8. Install the necessary modules, check the PHP code and re-index the search/catalogue.

## What is carried over

- templates, categories, groups and fields;
- documents, field values, revisions, tags, keywords, redirects and views;
- requests and conditions, menus and items, blocks;
- public users and groups;
- supported catalog, contacts, RSS and cart tables, if their modules are already installed;
- privileged legacy users of groups 1 and 3 as system accounts.

New JSON field settings are created from legacy values. For old conditions
requests, a root group is created. Regular visual blocks are translated into
unified register of blocks.

Compiled SQL and Native confirmation are not transferred between databases:
the conditions are preserved, but the request is run in Legacy before a new check in
Adminx. At the end of the migration, old JSON snapshots of documents and category schemes are deleted.
At the first public opening, they are built anew in batches from already transferred ones.
data. Rollback performs the same reset, so the cache cannot remain from
canceled transfer.

## What is not automatically transferred

- active sessions, remember tokens, API keys and auditing;
- passwords and secrets of payment systems;
- old PHP modules and their files;
- theme code and other executable files;
- unknown modular tables.

Such tables appear in the report as unmatched. First you need to install
a native analogue of the module or write an adapter using hooks.

## Migration hooks| Hook | Data | Destination |
| --- | --- | --- |
| `legacy_migration.plan` | `profile`, `tables`, `plan` | Supplement the plan after analysis. |
| `legacy_migration.row` | `run`, `job`, `row` | Change one line before recording. |
| `legacy_migration.completed` | `run`, `report` | Run post-processing after reconciliation. |

The `legacy_migration.row` handler must return the entire context with the changed
`row`. Through it, the receiver module can recode the column or translate
the value of the old module into the new format.

## File security

The ZIP is unpacked in portions. The module rejects:

- absolute paths and `../`;
- symbolic links;
- `.htaccess`, `.user.ini`, dot and executable files;
- double executable extensions;
- archives over 100,000 files or 10 GB after unpacking.

Files are written only to `uploads/`. By default, existing files are not
are replaced.

## After transfer

Automatic reconciliation checks lines but does not replace acceptance. Open
real pages of each category, check the menu, queries, blocks, media and
saved PHP code. If there is any discrepancy, do not delete the source database and the local one
startup backup.

The **Rollback transfer** button is available for completed, failed, or canceled transfers.
launch. It restores a snapshot of the target database. Files already written from ZIP to `uploads/`,
are not automatically deleted: before file transfer, save a separate copy of `uploads/`.
