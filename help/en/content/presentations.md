# Presentations

← [Back to “How AVE.cms builds a website”](README.md)

A presentation controls only the markup of an already prepared data set. It
does not select documents, change rubric fields, or execute SQL.

```text
rubrics and documents -> request or catalog -> presentation -> HTML
```

## Presentation parts

| Part | Purpose |
| --- | --- |
| Item | Renders one document or product available as `item`. |
| Wrapper | Combines rendered items into a list, grid, or slider. Item HTML is available as `content`. |
| Empty result | Used when the source returns no records. |
| CSS | Added only to a page that actually renders this presentation. |

Example item:

```twig
<article class="content-card">
  <h2><a href="{{ item.url }}">{{ item.title }}</a></h2>
  {% if item.excerpt %}<p>{{ item.excerpt }}</p>{% endif %}
</article>
```

Example wrapper:

```twig
<div class="content-grid">
  {{ content|raw }}
</div>
```

Use `content|raw` because `content` contains HTML rendered from the item
template. Regular values such as `item.title` remain auto-escaped by Twig.

## Data palette

The **Data** tab lists values that can be inserted into the current editor. A
base document provides `item.id`, `item.rubric_id`, `item.title`, `item.url`,
`item.excerpt`, `item.description`, `item.published_at`, `item.views`,
`item.image`, `item.thumb`, `item.fields`, `context.code`, and
`context.position`. List contexts also provide `context.total`,
`context.page`, and `context.pages`.

Enabled modules extend the palette. The product module adds article, price,
images, variants, and actions. Click a palette row to insert its Twig
expression instead of typing the path manually.

Product lists also expose:

- `item.stock` as a boolean availability flag;
- `item.shipping_packages` as a list of cargo packages;
- `actions.cart`, `actions.favorite`, and `actions.compare` as public action
  metadata.

Each package contains `title`, `quantity`, `weight_kg`, `length_cm`,
`width_cm`, and `height_cm`:

```twig
{% for package in item.shipping_packages %}
  <span>
    {{ package.title }}:
    {{ package.length_cm }} × {{ package.width_cm }} × {{ package.height_cm }} cm
  </span>
{% endfor %}
```

Action data describes availability and operation identifiers. Button markup
belongs to the presentation, while cart, favourites, and comparison behaviour
stays in the owning module.

## Draft and publication

Saving a draft does not change the public site:

1. Create a presentation.
2. Fill in its item, wrapper, optional empty state, and CSS.
3. validate Twig and preview it with a real document.
4. Save the draft.
5. Publish the verified version.
6. Add an assignment and explicitly choose **New presentation**.

The public site reads only the published snapshot. Later draft edits stay
private until the next publication.

## Assignment modes

| Mode | Behaviour |
| --- | --- |
| Current output (`legacy`) | Keeps the existing renderer. |
| Preparation (`preview`) | Saves the relation without switching public output. |
| New presentation (`native`) | Uses the published presentation. |

Targets are selected by name. The editor lists real rubrics, requests, catalog
sections, and installed modules, so an administrator does not need to find and
enter an ID manually. A target deleted later is marked as missing and should be
removed or replaced.

Assignments are resolved from specific to broad:

```text
catalog -> request -> rubric -> module -> global
```

A specific `legacy` assignment deliberately blocks a broader `native`
assignment. This lets an administrator revert one catalog without disabling
the shared presentation elsewhere.

## Connected output contexts

The shared renderer handles these output contexts:

- **Content list** for product feeds and catalog listings;
- **Configured requests** rendered by `[tag:request:...]`;
- **Rubric lists** rendered by `[mod_content_list:...]`;
- **Search results** for documents, products, and mixed result sets;
- **Related content** for documents and products;
- **Favorites** and **Viewed content** for customer lists.

Choose the required context, target **Module**, and use `products` as the
target code to cover the product subsystem. A **Catalog** target with its
section ID is more specific.

Use module target `search` for generic search output and `related` for generic
related content. A single-rubric result can use a more specific rubric
assignment, while a configured request assignment has higher priority.

To style a configured request, choose the **Request** target and its ID. The
request keeps ownership of selection, ordering, limits, and pagination, while
the presentation replaces its legacy `main/item` markup. Use a **Rubric**
target for a generic rubric list. Broad module targets `requests` and
`content` are available, but a specific target is safer for the first switch.

The product adapter runs first and supplies prices, stock, variants, and
actions. For non-product or mixed sets, the core builds one generic document
contract. The system never mixes old and new cards in one list: either the
complete presentation renders, or the existing renderer handles the complete
result.

Search presentations also receive `item.search.relevance` and
`context.query`. Related presentations receive `item.related.score`,
`item.related.matches`, `item.related.source`, and profile information in
`context.profile_id`, `context.profile_code`, and `context.profile_title`.

A configured request provides `context.request_id` and ready-to-use pagination
markup in `context.pagination_html`. Render it from the wrapper with
`{{ context.pagination_html|raw }}`.

In `native` mode the presentation owns the list wrapper. Page headings, search
controls, result counters, pagination, and clear actions stay with the owning
module. Presentation CSS is included for full pages and AJAX updates.

Other contexts keep their existing renderers. Merely creating a presentation
does not switch public output.

## Diagnostics

The **Diagnostics** tab runs an explicit pre-publication check:

- validates Twig in the item, wrapper, and empty-result templates;
- verifies that every assignment still points to an existing entity;
- checks that a `native` assignment has a published version;
- renders the draft with the document selected on the **Preview** tab.

Warnings do not switch output or repair data automatically. Fix errors before
enabling a new public renderer. Use **Public site → Placements** for deleted
block, request, navigation, and module tags; it shows both the source template
and the exact component editor.

## Placements and usage map

**Public site -> Placements** shows where existing templates include blocks,
requests, navigation menus, and module tags. This is an overview of the current
site rather than a second page builder:

- **Before content** covers site-template tags before `[tag:maincontent]` and
  the rubric header template.
- **Main content** covers the primary rubric template, blocks, navigation
  templates, and request wrappers.
- **After content** covers tags after `[tag:maincontent]` and the rubric footer.
- **Metadata** covers the rubric Open Graph template.
- **List item** covers request-item and teaser templates.

A red row means that the referenced block, request, or navigation no longer
exists. Disabled blocks are marked separately. Module tags are listed as-is;
their final availability is owned by the module that registered the tag.

**Rebuild map** reads all saved templates again. This is normally unnecessary:
the cached index is invalidated after a template, rubric, block, request, or
navigation is saved.

The map never changes public rendering. Use the action in the rightmost column
to open the existing editor and fix the source.

## Failure-safe output

The existing renderer is used when an assignment is missing, its mode is not
`native`, no published snapshot exists, required data is unavailable, or Twig
throws an error. The error is logged, but it must not turn the public page into
an HTTP 500 response or remove the whole catalog.

## Custom theme overrides

A theme that overrides product-list module templates must preserve the optional
`presentation` variable:

```twig
{% if presentation is not null %}
  {{ presentation|raw }}
{% else %}
  {# existing card output #}
{% endif %}
```

AVE.cms default templates already support this contract.
