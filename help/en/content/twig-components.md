# Dot Twig components

← [To the section “How the site is assembled”](README.md)

AVE.cms does not use Twig instead of site templates, categories, queries, blocks and
navigation. These entities remain the primary way to assemble a public page.
Twig is used inside a complex module when its output cannot be reasonably collected
regular tags.

## What to choose

| Problem | Standard tool |
| --- | --- |
| General header, footer and HTML wrapper | Website template |
| Page of news, article or other document | Category template |
| List of documents and list card | Request Templates |
| Repeatable editable insert | Block |
| Link tree | Navigation |
| Document value | Field and its public template |
| Catalog, cart, search, login or interactive form | Modular tag |

First use the appropriate tool from the table. A new Twig component is needed
only when the component needs data prepared by the service,
user state, API, or complex interactive behavior.

## How to insert a component

The Twig file is not specified directly in the editor. The module registers
regular public tag:

```text
[mod_search]
[mod_basket:mini]
[mod_gallery:12]
```

Such a tag can be placed in the template of a site, category, request or block. Next
the engine executes a regular pipeline:

```text
тег
  -> зарегистрированный обработчик модуля
  -> получение и проверка данных
  -> Twig-шаблон модуля
  -> готовый HTML
```

The page editor sees the stable tag, and not the path to the PHP or Twig file.
Removing or disabling a module does not leave an arbitrary executable template.

## Why is there no tag with the path to the file?

Constructions like `[tag:twig:путь/к/file.twig]` are not used in AVE.cms. Such
the tag would allow the template to independently select files from the database, bypassing the contract
module and pass raw data to the view.

Valid Twig templates are determined by the module itself. He is also responsible for:

- set of available data;
- checking tag parameters;
- user rights and status;
- connecting CSS and JavaScript;
- caching;
- safe behavior in the absence of data.

## Where to edit appearance

Managed settings and module templates are edited in its control panel section. If
The module is allowed to be overridden by the active theme, the file is located in:

```text
templates/<theme>/views/<module>_public/<template>.twig
```

File overriding is part of theme development. It doesn't replace
standard templates for categories and queries and should not be used just for the sake of another
wrapper or CSS class.

## Caching

Component that reads user, session, cart, CSRF or parameters
current request is private. It is rendered after reading the shared cache
pages.

Shared cache is only allowed for deterministic HTML that is the same for everyone
visitors. The decision is made by the module developer when registering a tag. B
This mode cannot be switched in the page editor.

## Rule of thumb

If the desired result can be obtained using a category template, query, block,
navigation or an existing module tag, do not create a new Twig component.
If the staff contract is really not enough, it is first formulated what
data and behavior are missing. After this, the corresponding module is expanded
or engine and a documented tag is added.

## System blocks in Twig

To avoid duplicating managed content within theme files, use:

```twig
{{ theme_sysblock('footer_contacts') }}
```

The function accepts an alias or system unit ID and returns the already processed
HTML. Nested tags, block parameters, caching and public hooks work like this
the same as with the usual insertion of `[tag:sysblock:footer_contacts]` into the category template.
If the text needs to be edited from a panel, store it in a block, and in Twig
leave only this challenge.
