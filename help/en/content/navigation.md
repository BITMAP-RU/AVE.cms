# Navigation

← [To the section “How the site is assembled”](README.md)

Navigation is an independent tree of links to documents and custom URLs. She doesn't
determines the address of the document and does not delete the document along with the item.

## Creating a menu

1. Specify a name and a short unique alias, for example `main`.
2. Customize the outer wrapper and templates of the levels used.
3. Select the groups that can be navigated.
4. Create items and arrange them by dragging and dropping.
5. Insert the `[tag:navigation:main]` tag into the template or block.

By default, the first level is sufficient. The second and third levels are customizable
only if the tree actually uses them.

## Items

The item can link to a document or contain an arbitrary URL. When choosing
document name and link are filled in automatically, after which they can be
override for a specific menu.

Additionally available are description, target, image, CSS ID, class and style.
The image is selected through the media picker. For a regular link use `_self`,
to consciously open a new tab - `_blank`.

The level is determined by the position of the item in the tree. Drag changes parent,
level and order; A separate numeric position field is not required for this.

## Level templates

Rendering is performed in the following order:

```text
Обёртка: начало
  Уровень 1: начало
    обычный или активный пункт
    следующий пункт
  Уровень 1: конец
Обёртка: конец
```

`[tag:content]` is replaced with ready-made items at the beginning of the level. Without this tag
items are added after the initial markup. The end of the level is always displayed after
its points.

Minimum first level:

```html
<!-- Обёртка: начало -->
<nav class="site-navigation" aria-label="Основная навигация">

<!-- Уровень 1: начало -->
<ul class="site-navigation-list">[tag:content]

<!-- Обычный пункт -->
<li><a href="[tag:link]">[tag:linkname]</a></li>

<!-- Активный пункт -->
<li class="is-active"><a href="[tag:link]" aria-current="page">[tag:linkname]</a></li>

<!-- Уровень 1: конец -->
</ul>

<!-- Обёртка: конец -->
</nav>
```

## Nested levels

The parent item template must contain the following level of tag:

```html
<li>
  <a href="[tag:link]">[tag:linkname]</a>
  [tag:level:2]
</li>
```

For the second level, `[tag:level:3]` is used similarly. If there is no tag,
child items exist in the tree, but will not end up in the HTML.

Conditions `[tag:if_sub_level]`, `[tag:if_no_sub_level]`, `[tag:if_first]`,
`[tag:if_last]`, `[tag:if_every:N]` and paired `[tag:/if]` allow you to change
markup without PHP.

## Active branch

The system searches for an item in the current document or a matching alias, builds the path to
root and applies the active template to the corresponding items. For correct
`aria-current` assign it only to the link of the current item if the design
distinguishes between the current page and the active parent.

## Cache and diagnostics

Changing the templates or structure clears the cache of this navigation. Manual cleaning
needed after external editing of the database.

If the item is not visible, check:

1. activity of the point and all parents;
2. access of the current group to navigation;
3. availability of a template of the required level;
4. `[tag:level:2]` or `[tag:level:3]` from the parent;
5. external and level closing markings.
