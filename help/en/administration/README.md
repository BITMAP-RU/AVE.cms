# System management

This section describes daily work with the AVE.cms blank panel. Content
entities are included in [separate chapter](../content/README.md), module development -
to [modular system](../modules/README.md).

## Core partition map

| Section | What is it for | Read more |
| --- | --- | --- |
| Dashboard | Site status, general counters, recent documents and module widgets. | [Dashboard and interface](dashboard.md) |
| Documents | Pages and posts of all sections. | [Documents](../content/documents.md) |
| Categories and fields | Types of content and forms of documents. | [Categories](../content/rubrics.md) |
| Templates | General shell of public pages. | [Templates](../content/templates.md) |
| Blocks | Reusable fragments and design logic. | [Blocks](../content/blocks.md) |
| Requests | Lists of documents with conditions and templates. | [Queries](../content/requests.md) |
| Navigation | Menus and link trees. | [Navigation](../content/navigation.md) |
| Catalog | Universal partition trees and filters. | [Catalogue](../content/catalog.md) |
| Media | Files, images, WebP and thumbnails. | [Media](../media/README.md) |
| Roles and rights | Panel staff permissions. | [Users and access](users.md) |
| Users | Employees who can work in the panel. | [Users and access](users.md) |
| Site users | Registration, personal account and public profile. | [Users and access](users.md) |
| Settings | Site parameters, interface, security, cache and diagnostics. | [Settings](settings.md) |
| Database | Schema, InnoDB, backups and migrations. | [Database and Events](operations.md) |
| Events | Audit, runtime, 404, SQL errors and transitions. | [Database and Events](operations.md) |
| Updates | Signed kernel patches, backup and rollback. | [AVE.cms updates](updates.md) |
| PHP console | Manual administrative diagnostics of PHP code. | [PHP console](console.md) |
| Modules | Installing, updating, disabling and removing extensions. | [Modules](../modules/README.md) |

Additional partitions appear only after installing the corresponding
modules. The presence of package files does not mean that it is installed or enabled.

## Recommended order for setting up a new site

1. Fill in the basic settings, email and public addresses.
2. Create a website template and categories with fields.
3. Create documents, navigation and necessary blocks.
4. Set up queries and catalogs.
5. Create employee roles and assign minimal rights.
6. Set up registration and public accounts if the project needs them.
7. Check diagnostics, 404, SQL log and backup.
8. Install only the required modules.Before launching a working website, a separate readiness checklist is useful: addresses,
templates, 404, preview, mail, permissions, cron replacements without CLI, cache and logs. He can
become the next independent help topic.
