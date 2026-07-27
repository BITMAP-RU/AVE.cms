# Website templates

← [To the section “How the site is assembled”](README.md)

A website template is the outer shell of a finished page. It contains an HTML document,
metadata, general header, navigation, main content location and footer.

## Minimum contract

```html
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>[tag:title]</title>
  <meta name="description" content="[tag:description]">
  <meta name="robots" content="[tag:robots]">
  <link rel="canonical" href="[tag:canonical]">
  [tag:rubheader]
</head>
<body>
  [tag:navigation:main]
  [tag:maincontent]
  [tag:sysblock:footer]
  [tag:rubfooter]
</body>
</html>
```

The required semantic tag is `[tag:maincontent]`. Without it, the document will be found
and processed, but the content of the category will not appear on the page.

## What to place in the template

- general `<head>`, CSS and JavaScript themes;
- header and basement;
- general navigation;
- system blocks that are the same for many pages;
- SEO tags of the current document;
- places for header/footer inserts of the section.

Place a card for a specific article or product in the category template. Lists
documents belong to queries or a catalog. This reduces the number of conditions in
global shell.

## Tag palette

The palette is built from the registry and shows the current blocks, navigation, queries and
system values. Once selected, the tag is inserted into the current position of the editor, and
the panel closes.

Prefer alias instead of numeric ID:

```text
[tag:navigation:main]
[tag:request:latest_news]
[tag:sysblock:footer]
```

This makes the template easier to transfer between installations if the system IDs are different.

## Modular Twig components

A complex module can return HTML from an internal Twig template, but into the site template
it is still inserted with a regular registered tag:

```text
[mod_search]
[mod_basket:mini]
```

Do not specify the path to the Twig file directly in the template. Before creation
new component, check whether the problem can be solved using a rubric, query, block,
navigation or an existing modular tag. The full contract is described in chapter
["Dot Twig Components"](twig-components.md).

## PHP and verification

The template may contain project PHP, but the error will break all pages that contain it
are used. Run a syntax check before saving. General business logic
It’s better to keep it in services and hooks, leaving composition and small
conditions.

Don't run database queries in a card loop. Prepare the data with the request,
directory, block or service before rendering.

## Saving and cache

The template source is stored in the database. After saving, the system atomically updates
public render file cache. The cache path is an internal detail; not
edit `.inc` manually, otherwise the next save will overwrite the changes.

The cache rebuild button creates files anew from the database. It is needed after the transfer,
file recovery or diagnostics, and not after each regular save.

## Revisions

When creating, saving, copying, importing and restoring,
revisions. Restore first saves the current state as a backup, then
Applies the selected snapshot and updates the cache.

Template No. 1 is systemic and cannot be deleted. Another template cannot be deleted until it is
uses at least one rubric.

## Procedure for changing the design

1. Create a copy of the template.
2. Assign it to a test rubric or document.
3. Check the regular page, 404, pagination, forms and logged in status.
4. Check mobile width and lack of horizontal scrolling.
5. After checking, switch work rubrics.
6. Delete the old template only after the observation period.
