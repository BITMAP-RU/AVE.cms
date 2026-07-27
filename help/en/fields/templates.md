# Public output and field templates

← [To the section “Document fields”](README.md)

## Two modes

- `doc` — field on the document page;
- `req` — field inside a request, list or card.

By default, `doc` calls `renderView()`, and `req` calls `renderFilter()`. For
galleries and links, request-mode behavior can be stricter so that heavy HTML
did not appear in every card without an obvious pattern.

## Output priority

Runtime selects the first suitable option:

1. non-empty template of a specific field from the database;
2. dot PHP type/field template;
3. standard method type `renderView()` or `renderFilter()`.

This allows you to change one site or one field without copying the entire type class.

## Dot PHP templates

Templates are located in:

```text
system/App/Content/Fields/Templates/<type>/
```

The names used for document mode are:

```text
field-doc-<field-id>-<template-id>.php
field-doc-<field-alias>-<template-id>.php
field-doc-<field-id>.php
field-doc-<field-alias>.php
field-doc.php
```

For query mode, replace `doc` with `req`. A more specific file has
priority. `template-id` — optional document/request template ID,
passed by runtime.

Available in the attached file:

```php
$template; // PublicFieldTemplateContext
$value;    // исходное значение
$items;    // нормализованный список элементов
$field;    // определение rubric_fields
```

Example for an image:

```php
<?php if (!$template->isEmpty()): ?>
  <?php $image = $template->first(); ?>
  <picture class="article-cover">
    <source srcset="<?= $template->escape($template->webp($image['url'])) ?>"
            type="image/webp">
    <img src="<?= $template->escape($image['url']) ?>"
         alt="<?= $template->escape($image['description']) ?>">
  </picture>
<?php endif; ?>
```

Context methods:

| Method | Result |
| --- | --- |
| `type()`, `mode()` | Type and `doc`/`req`. |
| `value()` | Initial value. |
| `field()`, `fieldId()`, `alias()` | Field metadata. |
| `templateId()` | ID of the current template. |
| `items()`, `first()`, `isEmpty()` | Normalized data. |
| `escape($value)` | HTML escaping. |
| `thumbnail($url, $size)` | Preview URL of a given size. |
| `webp($url)` | The WebP-derived path. |
| `host()` | Base address of the site. |

`thumbnail()` modes and sizes, cache lifecycle and derivative management
files are described in the [Media and Thumbnails](../media/README.md) section.

Don't execute SQL in a template. If the output requires additional data, prepare
them in the field type, module service or hook before rendering.

## Template from database

Templates `rubric_field_template` and `rubric_field_template_request` support:

- `[tag:value]` — full value;
- `[tag:parametr:0]`, `[tag:parametr:1]` — parts of the legacy value;
- `[tag:label]`, `[tag:alias]`, `[tag:default]` — field properties;
- `[tag:if_empty]...[/tag:if_empty]` and `if_notempty` with `[tag:else]`;
- preview creation tags and `[tag:img:...]`.

For a new structure type, a class method or PHP template with
`PublicFieldTemplateContext`: It works with named JSON data and not
depends on the items via `|`.
