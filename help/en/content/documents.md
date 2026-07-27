# Documents

← [To the section “How the site is assembled”](README.md)

A document is a specific page, record, article or product. Its structure is given
category, and the appearance is a category template and a site template.

## Creating a document

1. Go to **Content → Documents** and click create.
2. First, select a category. Fields, groups, default values depend on it,
   URL template and rights.
3. Fill in the title, address, SEO and content fields.
4. Check the JSON data preview without saving if you need to see the full
   payload of the document.
5. Save the draft or publish the document.

The heading of an existing document cannot be freely changed: another heading has a different
a set of fields and storage rules. This transfer is performed as a separate migration.

## States

| State | What does |
| --- | --- |
| Published | It is publicly listed and accessible via direct URL. |
| Off | Delisted but available via known direct URL. |
| Out of deadline | If date checking is enabled, the date has not yet been published or has already expired; 404 is returned to the guest. |
| Deleted | Located in the cart, a 404 is returned to the guest and can be restored. |
| Permanently deleted | The document and its values ​​are deleted without recovery through the list. |

The home page and designated 404 page are protected from deletion. For document
No. 1 you cannot change the root address `/`.

Date checking is controlled by the setting `use_doctime`. When it's off, the dates
beginnings and endings remain informational. When enabled, the window is applied and
to listings, and to direct URLs. An authorized administrator can open a remote
or an out-of-date document on the site, but will see a warning; ordinary
the visitor is given a standard page with HTTP 404.

## Address and redirects

The full URL is collected from the category template and a short alias document. For example,
template `news/%Y/%m` and alias `new-office` give
`/news/2026/07/new-office`.

- short alias must be unique in its supported contract;
- the full path cannot be taken if it is already owned by a document, redirect or public
  module route;
- when the address changes, the alias history can create a permanent redirect;
- general history is available in **Documents → Redirects**, history of a specific page
  - from its editor;
- 301 and 308 are used for permanent transfer, 302 and 307 for temporary transfer.

Do not create a new document at the old address while the redirect is needed by search engines
systems and external links.

## Fields and data

The editor shows only the fields of the selected category. Order, groups, width,
mandatory, default value, prefix and suffix are set in the constructor
rubrics The field type itself determines the editor, validation, storage, and public output.

Document data is available:- in the category template via `[tag:fld:alias]`;
- in queries and filters by alias fields;
- via JSON API document;
- in hooks before and after saving;
- in a native snapshot, which combines the document and its fields.

## SEO and announcement

The document title does not replace all SEO fields. Check the title separately
description, robots, canonical, Open Graph and breadcrumb title.

Announcement - a short self-presentation of a document for lists, cards,
search and publications. If the project builds an announcement from fields or a request template, the field
can be left blank; do not copy the entire body text there.

## Saving, revisions and snapshot

Normal saving closes the editor, **Save and Stay** leaves it
open. `Ctrl+S` performs saving with continuation of work.

New revisions retain the basic document settings and all field values.
Old Fields Only images remain compatible. Before recovery
a backup revision of the current state is created; ID and category do not change.
Snapshot of public data is updated after saving and can be rebuilt
manually from the documents section.

The form also stores a local draft in the current browser and is protected by a number
versions. If the document has already been modified in another tab, the old form will not be overwritten
new data: you can save it locally, refresh the page and restore it for
manual association. Details: [Content Studio](content-studio.md).

The **On site** button for the selected revision opens it in the actual category template.
The link is signed by the current session for five minutes and works only for an employee with
the right `manage_documents` and the right to edit this section. Preview doesn't read
and does not write compiled/full-page cache, does not increase views, gets
`noindex` and `no-store`. Do not generate parameters `revision_preview`,
`preview_token` or the old `revission` manually: without signature they return 403.

## Check after change

1. Open the public URL in a new tab.
2. Check the category template, fields, images and navigation.
3. Check the title, description, canonical and Open Graph in the HTML source.
4. When changing the URL, open the old address and make sure it is redirected.
5. For a document in the catalog, check that its projection and filters are updated.
