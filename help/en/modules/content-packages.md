# Content Packs

← [Back to the “Modules” section](README.md)

Module `content_packages` transfers structure and content between installations
AVE.cms in verifiable JSON format `ave-content-package-v1`. It replaces the old ones
`rubimex` and `faster`: does not run PHP import files, does not read `serialize()` and
does not execute arbitrary SQL.

## What's included in the package

The structure of each selected section is always transferred:

- the category itself and its URL settings;
- groups and field definitions, JSON settings, form conditions and field display templates;
- main category document template and code before/after saving;
- connections with another category, if both categories are included in the package.

Additional parts are switched on with separate switches:

| Option | Composition |
| --- | --- |
| Templates | Page template, category template options. |
| Requests | Output options, templates, nested groups and conditions. |
| Documents | Document details and values ​​of all fields. |
| Rights of categories | Permissions for groups found on the target site by name or ID. |

System blocks are selected separately and do not require selection of a category.

The package stores the values of media fields and file paths, but does not include the files themselves from
`uploads/`. When transferring to another server, the download directory needs to be synchronized
separately, keeping relative paths.

## Export

1. Open **Modules → Content Packs → Export**.
2. Provide a meaningful name for the package.
3. Include only the optional parts you need. Documents and default rights
   disabled so as not to accidentally upload user data.
4. Mark headings and independent units.
5. Click **Download JSON**.

One file may contain related categories. This is preferable to several
separate files: field links and related categories are then reassigned in one
operations.

## Pre-check and import

1. Go to the **Import** tab and select `.json` up to 25 MB in size.
2. Click **Check Package**. At this step the database does not change.
3. Check the number of categories, fields, queries, conditions, documents and blocks.
4. If missing field types are specified, first install the one providing them
   module. The import button will remain disabled.
5. Confirm the import.

The verified file is tied to the current administrator session for 30 minutes. Before
with the entry, the system re-verifies the SHA-256 of the file, so it can be seamlessly replaced with JSON
between preview and confirmation is not possible.

All entities of the same import are created in a transaction. Field, query or error
document rolls back the entire operation. Hooks `content.document.saved`,
`content.document.created` and `content.document.published` are launched only
after a successful commit: when rolling back the package will not send an email, webhook or
publication for a non-existent document.Along with the request, the result contract, preview renderer,
stable last sorting criterion and declarative condition sources.
Native confirmation is tied to specific site data, so imported
The Native request is safely translated to Shadow. After import, run the check
Native is already on the target database and only then enable it again.

## New IDs and conflicts

The original numeric IDs are never written directly. The system creates new
ID and reassigns:

- fields in templates `[tag:fld:N]`, `[tag:rfld:N]` and expressions `field-N`;
- fields and nested groups of query conditions;
- links to fields in the form conditions of individual fields and entire groups;
- templates for document headings;
- parents of documents;
- related categories and field settings with links to categories.

If form conditions are enabled in the source rubric, the switch is also transferred.
Old IDs inside the rules are replaced with new ones only after all fields have been created. If
the rule refers to a field that is not in the package, the import stops and
rolls back the entire operation: a partially working form is not created.

Existing data is not overwritten. If an alias conflicts, a new one is created
value with the suffix `-import-N`, and the matching name is marked
"import N". So one package can be safely re-downloaded, but this
will create a separate copy of the content.

Once the ID is reassigned, the package updates the JSON snapshots of the affected documents.
The public site will not see old module tokens or cached field values.

## Code of categories and blocks

JSON may contain PHP code from category or block settings. During import it
only saved as text, but after import the standard runtime will be able to
execute. Import packages only from a trusted source and view
code for categories and blocks before inclusion in production.

The File Security module allows a text token in this particular JSON
`<?php`, but continues to block dangerous high-level structures:
executing a command from an HTTP parameter, dynamic include, decoding chains
and other signs of embedded code. For the rest of the PHP downloads inside
non-executable file is still prohibited.

The format limits the package to 100 categories, 100 page templates, 500 blocks,
5000 fields, 1000 queries and 10000 documents. For large transfers it is better
make several logically complete packages.

When exporting, field values ​​are read in batches across a set of documents. Therefore
the number of SQL queries does not increase by one query for each document; large
the category should still be divided according to meaning due to the size of the final JSON and
subsequent downloading of files.

## Removing a moduleUninstallation deletes temporary preview files and settings of the module itself.
Imported categories, documents, queries, templates and blocks remain: after
a successful operation is normal site content. Remove it through the appropriate
control panel sections.

Old PHP files `rubimex`/`faster` are not directly supported. First
transfer the data to a working AVE.cms installation, then generate a new JSON via
this module.
