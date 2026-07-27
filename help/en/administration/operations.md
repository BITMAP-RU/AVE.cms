# Database and system events

← [To system management](README.md)

These sections are responsible for operation and diagnostics, and not for editing
content.

## Database

AVE.cms targets MySQL 5.7+ and InnoDB. The name of all tables is constructed from
prefix `dbpref` to `configs/db.config.php`; do not use a hard prefix in
SQL or module.

The screen only shows the current schema and prefix tables. Available for everyone
engine, size and approximate number of lines.

### Apply kernel migrations

The button is shown only when the delivery contains items that have not yet been completed.
migration of built-in partitions. If ledger and files match, it is visible instead
status **Scheme is current**. During a regular update, these migrations are performed
automatically inside the core patch. Manual surgery is needed to restore or
development builds: it applies pending kernel migrations and
synchronizes the rights registry. It does not read the backup and does not return old ones
data. Migrations of the installed module are applied by its own lifecycle.
Ledger does not allow you to rerun a successfully completed migration. Before
By changing the working scheme, create a backup copy.

### InnoDB and maintenance

The clean install uses InnoDB. Translation of the remaining legacy MyISAM tables
perform only after a backup: a large table may temporarily block the record.
`REPAIR` makes sense for MyISAM and is not a standard InnoDB procedure.

`OPTIMIZE` does not need to be run after every change. Use it after
large data deletion or based on diagnostic results.

### Backups

The panel creates a gzip dump of the current prefix in `tmp/backup`, allows you to download,
download back, restore and delete it. Uploaded file first
is checked and not executed automatically. New dumps use portable
placeholder `{{prefix}}`: a copy can be deployed to another installation with a different
`dbpref`.

Before recovery, the system checks the format, valid SQL commands, tables
and file immutability. Then automatically creates a backup copy of the current
database and only after that replaces the data. If the dump execution fails,
the system will try to immediately return the safety copy. Everyone is restored
table of the current installation, so selectively return one entry with this button
it is impossible.

During the operation, a window opens with live stages: file verification, safety
copy, clear the current schema, import tables and reset the runtime cache. For import
the current table and overall progress are shown. The page may work several times
minutes; do not run the second recovery in parallel. If MySQL rejects DDL
or data, the message will contain the table name and the original server error, and not
general inscription “nothing restored.”AVE.cms dumps are portable between the same system versions, different database name
and another prefix. When creating a MariaDB copy, the table descriptions are normalized to
MySQL 5.7 compatible format. Old service empty value `ENUM`,
which the MySQL non-strict mode allowed is replaced with the standard column value
from `DEFAULT`. This allows you to restore an old copy on a server with strict
SQL mode without disabling verification of other data. Before transferring between
different versions, first update both installations to the same build.

A copy on the same server does not protect against disk loss: transfer regularly
working backups to external storage and check the recovery on a separate
installation.

## System events

The section brings together several independent sources:

| Source | Contents |
| --- | --- |
| Audit | Actions of administrators and lifecycle operations. |
| Application Log | Service messages for public runtime and integrations. |
| 404 errors | Queries to missing addresses. |
| SQL errors | DB query errors. |
| Transitions | External sources, UTM tags and login pages. |

States are colored according to their meaning: success, change, warning, error,
dangerous action and information. Record details are disclosed separately to
the table remained readable.

The log can be filtered, exported and cleared if you have management permission.
Audit clearing itself leaves a new record of the fact of clearing. Before deletion
data needed for investigation, save CSV.

## What to check regularly

1. New SQL errors after release.
2. Repeated 404 on internal links and assets.
3. Unexpected dangerous actions in an audit.
4. Growth of the runtime log and repeated warnings.
5. Unusual referral sources and advertising tags.
6. Availability of a fresh, verified backup.
