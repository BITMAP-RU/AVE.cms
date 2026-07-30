# How AVE.cms builds a website

This chapter explains the public model of AVE.cms: what is a site template, category,
field, document, navigation, request, block and directory, how they are related and in what
are turned into a finished HTML page.

## General scheme

```text
URL
  -> документ
    -> рубрика
      -> поля документа
      -> шаблон рубрики
        -> [tag:maincontent]
          -> шаблон сайта
            -> блоки и модули
            -> запросы
            -> навигации
            -> SEO и хлебные крошки
              -> готовый HTML
```

Each entity has one main area of responsibility:

| Entity | Destination |
| --- | --- |
| Website template | General HTML wrapper: `head`, header, footer and places for components. |
| Topic | Static CSS/JS, images, fonts and how to include them. |
| Category | Content type: a set of fields, URL rules, rights and method of displaying one document. |
| Field | Single value contract: editor, validation, storage and public output. |
| Document | A specific category entry with its URL, status, SEO and field values. |
| Navigation | Independent tree of links to documents and custom URLs. |
| Request | Saved selection of documents with conditions, sorting and list template. |
| Block | A reusable piece of markup or logic. |
| Catalog | Hierarchy of sections, purpose of documents, characteristics, filters and listings. |
| Module | Installable extension with routes, tags, tables, hooks and interface. |

## How-to guides

If you're new to the system or want to understand the new editor in its entirety,
start with the in-depth chapter [Content Studio: From Category to Publishing](content-studio.md).

| Panel Section | Guide |
| --- | --- |
| Documents | [Creation, publication, URL, revisions and system pages](documents.md) |
| Categories and fields | [Design content type, form and public output](rubrics.md) |
| Dictionaries | [Shared value lists without documents, URLs, or SEO](directories.md) |
| Templates | [External shell of the site, tags, cache and revisions](templates.md) |
| Twig components | [Point modular inserts and the limits of their application](twig-components.md) |
| Topics | [CSS, JavaScript, Images, Fonts and Theme ZIP Packages](themes.md) |
| Blocks | [Reusable snippets, editors and parameters](blocks.md) |
| Navigation | [Level Templates, Items and HTML Assembly](navigation.md) |
| Requests | [Document selections, condition groups and list templates](requests.md) |
| Catalog | [Universal section trees, fields and filters](catalog.md) |
| Public site | [Structure map, presentations and component placements](public-site.md) |
| Presentations | [Reusable cards, lists, and safe public switching](presentations.md) |
| Content model tools | [Directories, relations, computed fields, and field sets](content-model-tools.md) |

Media, fields and modules are placed in separate chapters: [Media and
thumbnails](../media/README.md), [Document fields](../fields/README.md) and
[Modular system](../modules/README.md).

The main public site is assembled with built-in AVE.cms entities. Twig doesn't
replaces site templates, categories and queries. For complex modular components it
connects via a regular registered tag; details are given in
[separate chapter](twig-components.md).

## Three levels of templates

In AVE.cms, the word "template" is used for three related but different levels.

### 1. Website template

The site template contains the entire HTML document:

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
  <header class="site-header">
    [tag:navigation:main]
  </header>

  <main class="site-main">
    [tag:maincontent]
  </main>

  <footer class="site-footer">
    [tag:sysblock:footer]
  </footer>
  [tag:rubfooter]
</body>
</html>
```

The main contract of the site template is the tag `[tag:maincontent]`. There's an engine in it
substitutes the result of the category template of the current document or HTML system
module pages.

A website template usually contains:

- `<html>`, `<head>` and `<body>`;
- SEO tags of the current document;
- general header and basement;
- CSS and JavaScript connection tags for the active theme;
- main and auxiliary navigation;
- common blocks and modular components;
- `[tag:rubheader]` and `[tag:rubfooter]` for point rubric inserts.

The site template does not need to know the full set of fields for a news, product or article.
The rubric owns this.

### 2. Main category template

The basic template of a category describes the contents of one document:

```html
<article class="article">
  <header class="article__header">
    <h1>[tag:doc:document_title]</h1>
    <time>[tag:docdate]</time>
  </header>

  <div class="article__lead">[tag:fld:lead]</div>
  <div class="article__image">[tag:fld:image]</div>
  <div class="article__content">[tag:fld:content]</div>
</article>
```

After processing the fields, this HTML becomes `[tag:maincontent]` of the outer
website template.

### 3. Additional category template

The document can choose an alternative output option for the same rubric. For example,
The “Articles” section may have templates:

- regular article;
- longread;
- photo gallery;
- promo page.

The set of fields and rules of the category remain common, only the composition changes
specific document.

## Category

A category is a content type, not a document folder. Examples of headings:

- News;
- Articles;
- Pages;
- Goods;
- Employees;
- Questions and answers.

The rubric defines:

- set and groups of fields;
- order and width of fields in the document editor;
- default field values;
- main and additional public templates;
- teaser template for request elements;
- external website template;
- template for generating URLs for new documents;
- rights of user groups;
- Open Graph, inserts in header and footer;
- code and hooks before and after saving the document.

For example, the “News” category may contain the fields `image`, `lead`, `content`,
`source` and `gallery`. All documents in this section use the same contract
data, so queries and templates can access fields using stable aliases.

More information about types, settings and storage of values: [Document fields](../fields/README.md).

## Document

A document is a specific record of the selected category. It stores:

- ID and category;
- name and title of bread crumbs;
- alias and URL history;
- publication, deletion and validity period;
- author and date;
- announcement;
- SEO, robots, keywords and tags;
- category field values;
- selected additional category template;
- connections with catalogs and modules.

The document provides data, but should not independently describe all
the outer shell of the site.

### Document URL

A rubric can form alias using the following template:

```text
news/%year/%month/%alias
```

For a document with short alias `company-opened-office` the result will be:

```text
/news/2026/07/company-opened-office
```

The menu does not participate in the formation of the URL. After changing the alias, the previous address may
remain in the redirect history and lead to a new URL.

## Navigation

Navigation is an independent tree of links. She stores:

- paragraphs and nesting levels;
- link to a document or arbitrary URL;
- title, description, target and image;
- CSS class and additional attributes;
- accessibility to user groups;
- templates for regular and active items of each level.

Navigation is inserted into the page using alias:

```text
[tag:navigation:main]
```

### How HTML navigation is built

Navigation patterns are applied in a strict order:

```text
Обёртка: начало
  Уровень 1: начало
    Обычный или активный пункт уровня 1
    ...
  Уровень 1: конец
Обёртка: конец
```

For a submenu, the parent level item template must contain the location
next level output:

```html
<!-- Уровень 1: обычный пункт -->
<li>
  <a href="[tag:link]">[tag:linkname]</a>
  [tag:level:2]
</li>

<!-- Уровень 2: обычный пункт -->
<li>
  <a href="[tag:link]">[tag:linkname]</a>
  [tag:level:3]
</li>
```

`Level N: start` specifies a list wrapper. Tag `[tag:content]` inside it
is replaced by ready-made level items. If the tag is not specified, the items are added
after the initial marking. `Level N: end` is always displayed after all items
level. The `Wrapper: start` and `Wrapper: end` fields are displayed once around
the whole tree.

Example of first level settings:

```html
<!-- Обёртка: начало -->
<nav class="site-navigation" aria-label="Основная навигация">

<!-- Уровень 1: начало -->
<ul class="site-navigation-list">[tag:content]

<!-- Уровень 1: конец -->
</ul>

<!-- Обёртка: конец -->
</nav>
```

The result will have a complete structure
`<nav><ul>...items...</ul></nav>`. If there are no navigation options available,
outer wrappers are not output.

Deleting a menu item does not delete the document or disable its URL. One document
may be in several menus or not included in any.

## Requests

A “request” in AVE.cms is not an HTTP request, but a saved selection of documents. He
answers two questions:

1. Which documents to choose?
2. How to display a list and each of its elements?

The request may ask:

- one or more headings;
- simple and nested groups of conditions;
- combinations of `AND` and `OR`;
- sorting;
- limit and pagination;
- element template;
- list wrapper;
- caching settings.

Example requests:

- latest news;
- popular articles;
- materials of the current author;
- related documents;
- stock;
- goods with specified properties.

The query tag uses an ID or alias:

```text
[tag:request:latest_news]
```

Conditions with different logic should be grouped explicitly:

```text
Опубликован
И
(
    Рубрика = Новости
    ИЛИ
    Рубрика = Статьи
)
И
(
    Тег = Важное
    ИЛИ
    Просмотры > 1000
)
```

The system first selects documents, then applies an element template to each and
combines elements into a list wrapper.

## Blocks

A block is a reusable fragment that can be inserted into a website template,
category template, request or other block.

Examples:

- contacts in the basement;
- banner;
- subscription form;
- warning;
- advantages;
- a complex component with PHP logic.

```text
[tag:sysblock:product_card]
[tag:sysblock:contacts]
```

Blocks can contain queries, navigations, modular tags, and conditions. For new
logic, it is preferable to transfer complex operations to services and modules, leaving
in the block composition and small design conditions.

## Catalog

Catalog is a separate layer above categories, documents and fields. It's not equal
store and should not assume that every project contains goods.

The directory can be used for:

- product catalogue;
- news portal;
- knowledge base;
- catalog of services;
- document libraries;
- database of specialists or organizations;
- any structured section with filtering.

### Directory location in the system

```text
Рубрики и поля
      |
      v
Документы
      |
      v
Каталог
  |- разделы и вложенность
  |- назначенные документы
  |- наборы характеристик
  |- фильтры и условия
  |- сортировка
  |- шаблон карточки и листинга
  `- индекс для быстрого публичного поиска
```

A rubric is responsible for the structure of one document. The directory is responsible for where and
how many documents are shown to the user.

### What does the directory store?

- own name and purpose;
- root and subpartitions;
- documents of each section;
- used fields and field groups;
- fields included by default for new sections;
- filters and their order;
- conditions for displaying sections and documents;
- sorting and pagination settings;
- card and listing configuration;
- projection of data for fast public reading.

### Catalog and requests

A regular request is suitable for an independent selection: latest news, works
author, shares or related articles.

The catalog is needed when additionally required:

- constant hierarchy of sections;
- assignment of documents to sections;
- facet filters;
- agreed cards;
- controlled sorting;
- fast index of a large set of documents.

Queries and the catalog complement each other. For example, the home page might
show the “New Items” request, and going to the section opens a full-fledged catalog with
filters.

### Product opportunities

Products are an extension of the universal catalog. The goods module adds:

- article, price, old price and availability;
- product characteristics;
- variants of one product;
- product cards;
- commodity indexation.

Cart, orders, payments and deliveries belong to separate modules. Their physical
deletion should not delete documents, categories and universal structure
catalogue.

### How a directory partition is displayed

1. The URL determines the directory and its section.
2. The section settings, a set of fields and filters are loaded.
3. The input values ​​of the filters are normalized and checked.
4. The index selects suitable documents.
5. Sorting and pagination are used.
6. A card is collected for each document.
7. Cards are combined into a listing.
8. The result is inserted into the public directory page.

The card is a separate managed template. It should not be copied to
several system blocks, otherwise the main, catalog and collections begin to look
and work differently.

### Indexing and directory cache

A public filter does not have to parse all JSON field values every time. After
When a document is saved, its data is projected into the directory index. Index contains
only the values needed for listing, sorting and filtering.

A rebuild is required after the change:

- the document or its fields;
- assigning the document to sections;
- set of characteristics;
- filter settings;
- card configurations;
- structure of product variants.

## Modules

The module is a physically detachable extension of AVE.cms. He might add:- own public and administrative routes;
- tags for templates, categories and blocks;
- new field types;
- hooks and events;
- tables and migrations;
- settings and rights;
- item in the control panel menu;
- action in the header, notifications and Dashboard widget;
- background web tasks and integration with external services.

### Where the module is involved in the assembly

Before searching for document `PublicKernel` only loads included public parts
installed modules. After this, three main scenarios are possible.

1. **Custom route.** The module fully responds to the URL and completes the request
   without document. This is how APIs, callbacks and file endpoints work.
2. **Page in the site shell.** The module provides `[tag:maincontent]`, but
   uses the selected site template, SEO and a common public pipeline.
3. **Built-in component.** The module registers a tag that occurs in
   template for a site, category, request or block.

For example, a feedback form might register the tag:

```text
[tag:module:contacts:feedback]
```

The template is responsible only for the location of the component. Validation, shipping and storage
data belongs to the module.

### Dependencies and lifecycle

```text
Файлы доступны
  -> установка миграций и прав
    -> модуль включен
      -> маршруты, меню, hooks и теги работают
```

- **Disable** stops loading runtime contributions, but saves the data.
- **Uninstall** executes the script declared by the module and removes the rights.
- **Physical deletion** is only possible for the package being separated after
  uninstallation.
- **Dependencies** do not allow you to turn off a module while another one depends on it
  package included.

The core, categories and documents should not directly include the module's PHP files.
Interaction is built through its public services, tags, routes and hooks.

More details: [Modular system](../modules/README.md).

## Public page assembly order

The actual pipeline of one public request:

1. `PublicKernel` starts the framework, session, authorization, modules, security and hooks.
2. Direct public routes of modules are checked.
3. The URL is parsed into alias, pagination and additional parameters.
4. The document is located by alias.
5. If there is no document, the redirect history is checked, then the 404 document is loaded.
6. Along with the document, the category, rights and external website template are downloaded.
7. The full page cache is checked.
8. The configured public rubric code is executed before loading the document.
9. The publication of the document and the read permission of the current group are checked.
10. The main or additional heading template is filled in with the fields of the document.
11. The resulting HTML is inserted into `[tag:maincontent]` of the site template.
12. Header, Open Graph and footer categories are substituted.
13. Request fields, blocks, system blocks and teasers are processed.
14. Tags of installed modules are processed.
15. Saved queries are executed.
16. Navigations are formed.
17. SEO, document data, breadcrumbs and system paths are substituted.
18. Allowed PHP is executed from stored templates.
19. The result goes through the response hooks.
20. Quick editing and public debug are added for the administrator.
21. The finished HTML is sent to the browser and, if necessary, saved in the cache.

When hitting a full cache, some of the internal steps are not re-executed, but
the logical structure of the result remains the same.

## Caching

The public runtime uses several levels:

| Level | What is stored |
| --- | --- |
| Document Snapshot | Normalized document properties and field values. |
| Document template | The result of filling out the category template. |
| Full page | Website template along with content and components. |
| Request | The result of a heavy selection or listing. |
| Catalog | Projection for filtering, sorting and cards. |

Keys take into account document, category, template, user group, field versions and
pagination. After saving a document, category, field, request, template, or
directory configuration, associated entries must be invalidated.

More details: [Base and content cache](../database/cache.md).

## Public pipeline hooksHook is a named point of intervention in the operation of the system. Module signs
event handler and receives a structured context. This allows
add design logic without changing the kernel.

### Main public points

```text
frontend.request.received
frontend.route.resolved
content.document.loading
content.document.loaded
content.rubric.loading
content.rubric.loaded
content.query.loading
content.query.loaded
content.query.rendering
content.query.rendered
content.template.rendering
content.template.rendered
frontend.response.rendering
frontend.response.rendered
```

Events `loading` allow you to prepare or replace the source before reading.
Events `loaded` receive an already loaded object and can add to the result.
The `rendering` and `rendered` events operate on pre- and post-render content.

### Saving documents

There are separate stages for administrative and API saving:

```text
content.document.saving
content.document.saved
content.document.created
content.document.updated
content.document.published
content.document.unpublished
content.document.deleting
content.document.deleted
content.document.snapshot_built
```

The exact names of available events and their contexts are shown in the hooks directory.
The processor must check the category, ID or status of the document and quickly
terminate if the event does not suit it.

Application examples:

- publish a new article in Telegram or Zen;
- transfer the order to an external CRM;
- check the product certificate before publication;
- supplement the document with external API data;
- set the task of rebuilding the search index;
- clear CDN or third-party cache after changing the page.

### Field hooks

The field type itself is responsible for normalizing and validating the value, but the module can
subscribe to a common field loop:

```text
content.field.normalizing
content.field.normalized
content.field.validating
content.field.validated
content.field.saving
content.field.saved
content.field.rendering
content.field.rendered
```

The handler is necessarily limited to the alias, ID, or field type. Otherwise he will
run for every field of every document.

### Catalog and hooks

The directory index is updated following the saving of the document and its snapshot,
so integrations can use actual events
`content.document.saved` and `content.document.snapshot_built`. Invalidation
passes through `cache.invalidating` and `cache.invalidated`.

The installed catalog or product module may declare additional
events via `hook_definitions`. Their names and context become part of
contracts of this particular module appear in the general hooks directory. Use
an undeclared name cannot be used as a system contract.

### Changing and canceling a result

`LifecycleEvent` may return a modified result. Cancellation supported
only at stages where it is expressly stated in the contract. When saving a document
for controlled failure use `DocumentSaveEvent::fail()`. Error
should be clear and not leave partially saved data.

For post-storage side effects, step `saved` is preferred. For
The pre-record stage is used to validate and disable the operation. External HTTP call
should not be executed inside a long transaction if it can be deferred until
successful conservation.

Full directory, priorities, context formats and debugging:
[Hooks and events](../hooks/README.md).

## Practical procedure for creating a website

1. Create an external website template with `[tag:maincontent]`.
2. Create categories for actual types of content.
3. Add and arrange fields for each category.
4. Collect the main heading template and the necessary alternatives.
5. Create documents and fill in field values.
6. Set up alias and check redirect history.
7. Create navigation and link documents or URLs.
8. Create queries for feeds and related materials.
9. Put the repeated parts into blocks.
10. If necessary, create a catalog, its sections, fields and filters.
11. Install only the modules needed by the project.
12. Check rights, SEO, 404, pagination, cache and mobile output.

## Typical compositions

### News site

```text
Рубрики: Новости, Статьи, Страницы
Запросы: Последние, Популярные, По теме
Каталог: необязателен; может использоваться как рубрикатор публикаций
Модули: RSS, формы, авторизация — по необходимости
```

### Knowledge base

```text
Рубрики: Инструкции, FAQ, Справочные страницы
Каталог: разделы знаний и фильтры по продукту/версии
Запросы: Обновленные, Связанные, Популярные
Навигация: основное меню и дерево документации
```

### Online store

```text
Рубрики: Товары, Страницы, Новости
Каталог: товарные разделы, характеристики и фильтры
Модуль товаров: цены, наличие, варианты и карточки
Commerce: корзина, заказы, избранное и история просмотров
Gateways: оплаты, авторизация и доставки отдельными пакетами
```

## Frequent errors

- Place all document markup in an external website template.
- Use the menu to generate the document URL.
- Copy one card to several system units.
- Use a long chain `AND/OR` without groups of conditions.
- Make the catalog dependent on products and cart.
- Read JSON of all fields for each public filter instead of the directory index.
- Place large business logic in a stored template instead of a service or module.
- Forgetting to invalidate the cache after changing a template, field or directory.
