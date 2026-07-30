# Public site: how a page is assembled

← [Back to “How AVE.cms builds a website”](README.md)

The **Public site** section is a read-only overview. It shows which components
assemble the site, where they are used, and which editor owns each setting. It
does not publish or change content automatically.

## Base components

| Component | Responsibility |
| --- | --- |
| Site template | The outer HTML document, header, footer, and main content slot. |
| Category | A content type, its fields, URL rules, and single-document template. |
| Document | Values entered into the fields of one category. |
| Request | Selects, sorts, paginates, and renders several documents. |
| Block | A reusable content or logic fragment. |
| Navigation | A link tree and markup rules for its levels. |
| Catalog | Organizes documents into sections and adds filters. |
| Module | Adds an installable feature, routes, tags, and administration UI. |

A normal page is assembled in this order:

```text
page URL
  → document
  → category and field values
  → category template
  → site template
  → embedded blocks, requests, navigation, and modules
  → final HTML
```

The Public site section visualizes this chain. Content is still edited in the
regular component editors.

## Structure

Each row represents one category. It shows its site template, document and field
counts, related requests and catalogs, and clear structural problems. The
diagnostics are informative and never repair data automatically.

## Presentations

A **presentation** is an optional visual layer for already prepared data. It
can render the same result as cards, a compact list, a slider, search results,
or related content.

| Base component | Presentation |
| --- | --- |
| Defines data, relationships, or behavior. | Defines how prepared data looks. |
| A request selects documents. | A presentation renders the request result. |
| A category defines document fields. | A presentation reads their values. |
| A module performs a feature. | A presentation may render its output. |

A presentation does not replace a request, category, block, navigation, or
module. Existing AVE.cms templates remain fully supported.

Safe workflow:

1. Create a presentation.
2. Edit its item, wrapper, empty state, and optional CSS.
3. Preview it with a real document.
4. Save and publish the reviewed version.
5. Create an assignment by selecting a real rubric, request, catalog section,
   or installed module by name.
6. Enable **New presentation** only for the required target.

Before switching, open **Diagnostics**. It validates Twig, missing assignment
targets, and a preview with a real document. The check does not publish or
change the public site.

A draft never changes the public site by itself. See
[Presentations](presentations.md) for the complete editor guide.

## Placements

A placement is a component tag found in saved markup, for example:

```text
[tag:sysblock:footer]
[tag:request:12]
[tag:navigation:main]
[mod_search]
```

**Placements** shows every occurrence. **Used by** groups equal components and
shows their usage count.

The first action opens the exact source containing the tag. The second action
opens the exact block, request, navigation, or owning module. If a module cannot
be resolved, AVE.cms opens the general module registry.

The placement map does not execute public pages and does not modify saved
markup. **Rebuild map** only rereads saved templates.

## Diagnostics

Diagnostics answers two practical questions:

1. which saved data and components assemble a specific public page;
2. why a document is included in or excluded from a request result.

The inspection is read-only. It does not execute the public page, save a
document, rebuild caches, or modify templates.

To inspect a page:

1. Open **Public site → Diagnostics**.
2. Enter a public path such as `/news/example`, or a document ID.
3. Select **Inspect page**.

The result contains the document and its status, the category schema and field
values, active templates, related requests and presentations, referenced
blocks/navigation/modules, cache keys, and actionable findings. Template source
is represented by its length and hash rather than copied into the report.
Passwords, tokens, and other sensitive values are redacted before rendering.

To explain a request, enter its ID and a document ID. Parameters used by dynamic
conditions can be entered as a query string:

```text
catalog=beds&color=white
```

or as a JSON object:

```json
{"catalog":"beds","color":"white"}
```

AVE.cms displays the base restrictions, the normalized `AND`/`OR` tree, actual
and expected values, and the final inclusion result. Parameters exist only for
the inspection call and do not leak into the current panel request.

Each result also contains a collapsed structured JSON payload for developers
and future integrations.

## What this section does not do

- move tags between templates;
- repair references automatically;
- enable presentations without an explicit assignment;
- replace the regular component editors;
- change public rendering while the map is being viewed.

This keeps ownership clear: the map finds a relationship, and the component
editor changes the data that actually owns it.
