# Migration from old AVE.cms

← [Back to the “Modules” section](README.md)

The `legacy_migration` module transfers an old AVE.cms site into a clean
installation of the new system. It does not merge two active sites: starter
content is replaced while document, rubric, field, template, and relation IDs
are preserved.

## When to use

The migration is designed for a new installation with two starting
documents. On a site where working content has already been created, the launch will be
blocked. For repeatable CSV, Excel or XML download, use
["Import documents"](document-import.md).

## Operating procedure

1. Create a separate empty AVE.cms and install the module.
2. Open **Modules → Migration AVE.cms**, enter the old database connection,
   and click **Save and check**.
3. The module runs analysis automatically. It reads the schema and counters
   without changing the source or target database.
4. Review the transfer map. It shows the source table, target table, and row
   count for every supported entity. Fix critical core-table or field-type
   issues.
5. Start transfer. The action creates a job immediately. A compressed JSONL
   backup of affected tables, settings, and system users is then created in
   separate AJAX steps before starter content is cleared.
6. Wait for the number of rows to be verified.
7. Zip the old `uploads/` and upload it to the completed launch.
8. Install the required modules again, check stored PHP code, and rebuild search.

## Progress and resume

Migration is not performed by one long HTTP request. The module runs these
server-side steps:

1. back up target tables;
2. clear starter data only;
3. transfer source tables in batches;
4. normalize fields, requests, blocks, users, and settings;
5. reconcile row counts.

The report shows the overall percentage, current operation, active table,
processed rows, and per-table state. The server stores a checkpoint after every
batch.

If the browser tab is closed, the connection is lost, or a temporary error
occurs, open the run from history and click **Continue**. Completed tables are
not transferred again.

**Rollback transfer** becomes available only after the backup is complete. An
incomplete backup file is never used for restore.

## What is carried over

- templates, categories, groups and fields;
- documents, field values, revisions, tags, keywords, redirects and views;
- requests and conditions, menus and items, blocks;
- public users and groups;
- privileged legacy users of groups 1 and 3 as system accounts.

The old home page is imported as document `ID 1` and replaces the starter home
page. The configured 404 document is imported with all documents, and its ID is
restored from the old site settings.

Public user `ID 1`, created by the installer for the current administrator, is
preserved. Source user `ID 1` is skipped; all other public users retain their
legacy IDs.

New JSON field settings are created from legacy values. For old conditions
requests, a root group is created. Regular visual blocks are translated into
unified register of blocks.

Columns that did not exist in old AVE.cms are filled with safe initial values.
Documents receive empty `guid` and `module_catalog` values, while rubrics
receive an empty OG template. Legacy MyISAM `NULL` values are normalized when
the new schema requires a non-null value.

The main site settings are copied to both the system registry and the public
configuration. Legacy breadcrumb setting typos are normalized automatically.
Database, SMTP, and other secret passwords are not copied and must be entered
again after the migration has been checked.

Compiled SQL and Native confirmation are not transferred between databases:
the conditions are preserved, but the request is run in Legacy before a new check in
the control panel. At the end of the migration, old JSON snapshots of documents and category schemes are deleted.
At the first public opening, they are built anew in batches from already transferred ones.
data. Rollback performs the same reset, so the cache cannot remain from
canceled transfer.

## What is not automatically transferred

- active sessions, remember tokens, API keys and auditing;
- passwords and secrets of payment systems;
- old PHP modules, their files, and `module_*` tables;
- theme code and other executable files;
- catalog, commerce, contacts, and other legacy module data.

This applies only to module tables in the legacy source database. Native modules
already installed in the target system do not block full migration. Their
tables, settings, versions, and enabled state are not checked for emptiness,
cleared, or changed.

Legacy module tables are summarized as one informational report item. Install
the required native modules after the core migration. A dedicated importer or
hook adapter is required only when data from a particular old module must also
be retained.

## Clean installation boundary

The starter home page, 404 page, rubric, fields, template, menu, and related
service rows do not block migration because they are replaced. Keywords,
revisions, and views are also accepted while they reference only starter
documents `ID 1` and `ID 2`. Any additional working content blocks the run to
prevent accidental overwrite.

## Migration hooks

| Hook | Data | Destination |
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

If a row cannot be written, the report includes the target table and the source
row ID that caused the MySQL error. This makes it possible to correct one value
in a copy of the old database and run the analysis again.

The **Rollback transfer** button is available for completed, failed, or canceled transfers.
launch. It restores a snapshot of the target database. Files already written from ZIP to `uploads/`,
are not automatically deleted: before file transfer, save a separate copy of `uploads/`.
