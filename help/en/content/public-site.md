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

## Template map

The **Template map** answers one practical question: where should a particular
part of a public page be edited? It reads the current installation and never
saves or switches anything by itself.

The current composition mode is shown first:

- **AVE.cms templates** means that the site template and rubric template build
  the page, while the active theme supplies assets and selected component
  overrides;
- **Theme shell** means that the configured Twig file builds the outer page
  shell, with rubric inheritance controlled by the theme manifest.

The page then shows actual chains for a text document, a content list, a
catalog category, a product card, and a full product page. Every step links to
the editor that owns it. The rubric table shows site-template assignments and
whether the main or additional rubric templates exist. Component groups show
whether markup comes from the active theme, a system fallback, or a missing
file.

A theme component is not an independent page. It works only when a rubric
template, request, block, or module calls it. Document content should therefore
remain in document fields rather than in a theme Twig file.

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

The map also reads `[tag:fld:*]` and `[tag:rfld:*]`. In a category-specific
template it verifies that the field exists. In a generic block the field is
marked as contextual because the actual document supplies the category.

**Dependencies** groups required fields, blocks, requests, navigation, and
modules by source. **Unused** lists saved templates and components without a
native assignment or reference. It is a review queue and never deletes data.

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

## Page templates

The **Public site → Page templates** tab exposes file-based Twig components
that previously required browsing the active theme directory. It includes the
fallback content-list view and general components owned by the active theme,
such as the page shell, breadcrumbs, account navigation, and type-specific
page views.

This does not replace site, rubric, or request templates. Saving validates Twig
syntax, records a theme revision, and clears the public cache. A component with
a system fallback can be returned to that fallback. Theme-only components are
edited here and deleted only from the full **Themes** file manager.

## Where each template is edited

| What you need to change | Control-panel section |
| --- | --- |
| Main site HTML shell | **Content → Templates** |
| Document, teaser, Open Graph, and field output | **Content → Rubrics and fields → Rubric templates** |
| Material list and one result card | **Content → Requests → Templates** |
| Reusable page fragment | **Content → Blocks** |
| Menu wrappers and levels | **Content → Navigation → Templates** |
| Page shell, breadcrumbs, and active-theme components | **Content → Public site → Page templates** |
| Catalog, product card, and product page | **Store → Products → Page templates** |
| Cart, checkout, order account, and store email | **Store → Settings → Templates** |
| Login, registration, profile, and connected login methods | **System → Site users → Account pages** |
| Search, polls, galleries, reviews, and other module features | The **Templates** tab or **Page template** action in the owning module |

**Themes** remains the complete file manager for developers and rare assets.
For normal editing, start in the owning section: it explains the component,
lists its available variables, and preserves the system fallback.
