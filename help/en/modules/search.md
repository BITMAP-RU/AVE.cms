# Search the site

← [Back to the “Modules” section](README.md)

Module `search` provides its own full-text index, page
results, search suggestions and API. Search does not need to be assembled by PHP code in
system unit. The module itself updates the index when saving and deleting a document,
and the appearance of the output can be obtained from a ready-made AVE.cms request.

## Features

- search by title, alias, announcement and allowed fields of documents;
- separate areas: news, articles, products or any sets of headings;
- four standard algorithms and registration of your own strategies;
- built-in output or cards from an existing request;
- search tips;
- live update of results and pagination without reloading the page;
- JSON API with an explicitly selected set of fields;
- HTML API for ready output and `both` mode for JSON along with HTML;
- hooks before search, after search, before output and after API projection.

## Quick start

1. Open **Modules → Management** and install **Site Search**.
2. Go to **Modules → Site Search**.
3. On the **Basic** tab, set the URL, page template, and limits.
4. Select categories or leave the selection blank to search across all categories.
5. Select a built-in output or a query with ready-made cards.
6. On the **Index** tab, click **Reindex**.
7. Add `[mod_search]` to the template, block or document content, if necessary
   separate search form.
8. Open `/search?q=installation` and check the output.

Reindexing is performed in chunks through the panel and does not require a command line.
After the first construction, regular saving and deleting of documents is supported
index automatically.

When the **Scheduler** is installed, it exposes a disabled-by-default
**Search reindex** chunked task. It can be started manually or enabled on a
schedule. Its cursor and progress survive between ticks, so closing the panel
does not interrupt that rebuild.

## Basic settings

### Public page

| Setting | Destination |
| --- | --- |
| Search URL | Results page address. Default is `/search`. |
| Page Template | A regular website template, in which the module places results in `[tag:maincontent]`. |
| On page | Number of results, from 5 to 100. |
| Hints | The maximum number of options under the search bar. |
| Minimum characters | A request shorter than this value is not executed. |

The module reserves the page URL and hint address. The document cannot be received
busy alias. The address `/api/v1/search` is reserved for the search API and is not
can be assigned to a search page.

If a document with the old alias `search` existed before installing the module, the panel
will show a warning. While the module is turned on, its route has priority.

### What's included in the index

A document is included in the search when it:

- published and not deleted;
- has the **Show in search** attribute enabled;
- not yet expired;
- refers to an authorized category.The index includes a title, alias, teaser and values of fields that have the
search. A scheduled document can be pre-recorded in the index, but not
will appear in the search results before the publication date. An overdue document is excluded by SQL-
condition without mandatory reindexing.

After changing the search attribute of a field or after importing directly into the database, run
complete reindexing. Normal saving via AVE.cms does this itself.

## Output settings

The search algorithm only selects document IDs and determines their order.
Display is configured separately.

### Built-in template

The **Built-in search template** mode displays a universal list: date, name,
announcement and link. This is a ready-made option for a simple site and checks that
the index is configured correctly.

This is a deliberate exception to the general AVE.cms rule: the search module provides
own backup markup so that the output works immediately after installation.
The engine does not impose this markup on other subsystems. On the working site external
it is better to pass the search type to a regular AVE.cms request or its own handler
hook.

The public view files are located in:

```text
modules/search/app/view/
modules/search/app/assets/
```

The files contain the functional page shell, form, and bundled assets. Use the
**Page shell** button in the module header to edit the form, complete page,
AJAX fragment, and pagination. Saving creates an active-theme override, checks
Twig syntax, records a theme revision, and invalidates the public cache.

Result-card markup remains on the **Output templates** tab. The page-shell
editor controls where the form and result fragment live; output templates
control the contents of each result. Existing requests remain the best option
when different sections require different cards.

In **Modules → Site Search → Output Templates** the built-in markup can be changed
without editing files. Four templates are available:

- summary of what was found;
- one result;
- list wrapper;
- empty output.

Main element tags: `[tag:docid]`, `[tag:url]`, `[tag:title]`,
`[tag:excerpt]`, `[tag:date]`, `[tag:datetime]`, `[tag:score]`. Wrapped
`[tag:summary]`, `[tag:items]`, `[tag:pagination]`, `[tag:query]` and
`[tag:total]`. If you remove `[tag:pagination]`, standard pagination will be added
after wrapping. Restore button returns delivery templates after
subsequently saving the settings.

PHP is not executed in these templates. For conditions, category fields and completely
project card, use an existing request or output hook.

### Cards from an existing request

The **Existing request templates** mode allows you to use a ready-made
card of news, article or product.

Important: the module does not execute the SQL conditions of the selected query. It only uses:

- **Main template** of the request as a list wrapper;
- **Element template** as a card for each document found;
- element caching settings and available request tags.

The IDs and their order come from the search index. Therefore, the conditions, sorting,
The category and limit of the request itself do not change the composition of the search results.

#### How to prepare a request

1. Go to **Content → Queries** and create a query, for example
   `Search result card`.
2. You don’t have to set conditions: they are not used for searching.
3. In **Main Template** place a container and `[tag:content]`.
4. In **Element Template**, insert the desired markup and document or field tags.
5. If necessary, place `[tag:pages]` in the main template.
6. Save the request.
7. Return to **Modules → Site Search**, select a withdrawal method
   **Existing request templates** and created request.

Minimal basic template:

```html
<div class="search-grid">
  [tag:content]
</div>
<div class="search-pages">[tag:pages]</div>
```

Example element:

```html
<article class="search-card">
  <a href="[tag:link]">
    <img src="[tag:rfld:image][img]" alt="[tag:doctitle]">
    <h2>[tag:doctitle]</h2>
    <div class="search-card-price">[tag:rfld:price][more]</div>
  </a>
</article>
```

The element uses standard query tags, including `[tag:docid]`,
`[tag:link]`, `[tag:doctitle]`, `[tag:excerpt]`, `[tag:rfld:alias][more]` and
conditional constructions. This allows you to use the same card that you already have
used in a catalog or news feed.

Available in the main template:

| Tag | Meaning |
| --- | --- |
| `[tag:content]` | Ready items in order of relevance. |
| `[tag:pages]` | Search pagination. |
| `[tag:doctotal]` | The total number of matches, not just the current page. |
| `[tag:doconpage]` | Number of elements displayed. |
| `[tag:pages:curent]` | Current page number. Spelling retained for compatibility. |
| `[tag:pages:total]` | Total number of pages. |

If `[tag:pages]` is missing, the module will add its pagination after the result
request. If the tag is present, pagination is completely controlled by the main request template.

PHP allowed in the administratively controlled request template is processed
the same `StoredPhpRuntime` as on regular pages of the site.

## Search areas

The area defines its own categories, algorithm, output and API fields. For example:

| Title | Code | Categories | Conclusion |
| --- | --- | --- | --- |
| News | `news` | News, Press Center | Request with news card |
| Articles | `articles` | Blog, Instructions | Request with article card |
| Products | `products` | Product headings | Request with product card |

An empty rubric set means all rubrics. An area can inherit the main request and
API field set or override them.

### Scope display conditions

A scope can have additional restrictions without SQL and without fixed field
IDs. Each condition stores a field alias, so it survives a site transfer as
long as the rubric keeps the same system field names.

To add a restriction:

1. Open **Scopes** and the required scope.
2. Under **Display conditions**, click **Add condition**.
3. Select a field. Its alias is shown next to its title.
4. Select a check and enter a value when required.
5. Save the search settings.

All rows must match. For example, a product scope may use:

| Field | Check | Value | Meaning |
| --- | --- | --- | --- |
| Price (`price`) | Number is greater than | `0` | Include only items with a positive price. |
| Hide (`noshow`) | Checkbox is off | — | Exclude manually hidden items. |
| Not in price list (`noprice`) | Checkbox is off | — | Exclude items absent from a price list. |

The aliases above are examples only. Another site selects its own fields. The
module does not require product-specific aliases and does not add these rules to
a clean installation.

Available checks cover empty values, checkbox state, text equality, and numeric
comparisons. For **Checkbox is off**, **Does not equal**, and **Is empty**, a
missing field also matches. This is useful when one scope combines rubrics with
slightly different field sets.

Document state is checked independently. Disabled, deleted, and non-searchable
documents stay out even when the search index is stale. Conditions are applied
before counting, so totals, suggestions, and pagination match the actual output.

The public page shows areas as radio buttons. Direct links:

```text
/search?q=кресло
/search?q=кресло&scope=products
/search?q=обзор&scope=articles&page=2
```

## Search form and hints

Available tags:

```text
[mod_search]
[mod_search:compact]
[mod_search:news]
```

- `[mod_search]` displays the normal form;
- `[mod_search:compact]` displays the compact version;
- `[mod_search:news]` assigns the shape to the area `news`.

The form has tooltips enabled. They refer to the address
`<URL поиска>/suggest?q=...&scope=...`. The base module returns the ID, signature and
URL. Installed modules can augment the response via a hook
`search.suggestions.projected`. The product module in the area `products` adds
name, article, image, current and old price, therefore from the same API
You can assemble a search with cards.

On the search page itself, JavaScript intercepts form submission, region change
and pagination. The result is replaced via the HTML API, the browser URL is updated via
History API. Without JavaScript, all that remains is a regular GET form and server-side pagination.

## JSON and HTML API

Permanent endpoint:

```text
GET /api/v1/search
```

Parameters:

| Parameter | Meaning |
| --- | --- |
| `q` | Search bar. |
| `scope` | Area code, optional. |
| `page` | Page starting from 1. |
| `per_page` | Page size from 5 to 100. |
| `format` | `json`, `html` or `both`. Default `json`. |

Example:

```text
/api/v1/search?q=массажный+стол&scope=products&page=1&per_page=12
```

JSON response:

```json
{
  "success": true,
  "query": "массажный стол",
  "scope": "products",
  "algorithm": "balanced",
  "pagination": {
    "page": 1,
    "pages": 3,
    "per_page": 12,
    "total": 31
  },
  "items": [
    {
      "id": 145,
      "title": "Массажный стол",
      "url": "/catalog/massazhnyj-stol",
      "price": 125000,
      "image": {
        "url": "/uploads/catalog/145/main.webp",
        "description": "Массажный стол"
      }
    }
  ]
}
```

`format=html` returns a completed results fragment with the selected handler
output. `format=both` adds this fragment to the `html` JSON response key.

The API is public and limited by a rate limiter. Do not add service or
confidential fields: the selected values can be read by the site visitor.

### Select API fields

In **Basic → Output and API → JSON API Fields** the response sources are selected. For
for each source are specified:

- key in JSON, for example `title`, `price`, `image`;
- the format in which the field will appear in the response.

System sources:

- ID, title, URL, announcement;
- date of publication and changes;
- Category ID;
- calculated relevance.

Below are the actual fields of the categories by their alias. If the same alias is in
several headings, it is represented by one source and takes the value of the current
document.

Formats:

| Format | Result |
| --- | --- |
| Automatically | The native field type value: string, number, array, or object. |
| Text | HTML is cleared; the array is serialized into a JSON string. |
| Number | Numeric value without decoration. |
| Yes/no | JSON `true` or `false`. |
| Media | A normalized image/file object or an array of objects. |
| How to store | The original value of the field from the database. Use for compatibility purposes only. |

To display the title, price and image:

1. Select **Title**, assign the key `title`, format **Text**.
2. Select the category field with alias `price`, assign the key `price`, format
   **Number**.
3. Select the field with alias `image`, assign the key `image`, format **Media**.
4. Add an ID or URL if required by the client code.
5. Save the settings and check the endpoint.

An area can have its own projection. For example, `news` returns the date and
announcement, and `products` - price, availability and images. Empty Area Projection
means inheritance of the main set.

### Own live search

For a dropdown list with price and image, use the JSON API:

```js
fetch('/api/v1/search?q=' + encodeURIComponent(query) +
  '&scope=products&per_page=8', {
  credentials: 'same-origin',
  headers: { Accept: 'application/json' }
})
  .then(function (response) { return response.json(); })
  .then(function (payload) {
    if (!payload.success) return;
    renderItems(payload.items);
  });
```

If the client needs a ready-made card, use `format=html`. For
simultaneously obtain structured data and ready-made markup, use
`format=both`.

## Dictionaries and typo correction

Search first executes the visitor's exact query. Only an empty result may be
retried with a conservative correction learned from indexed document titles and
their additional search names. The corrector handles a wrong keyboard layout,
`e/yo`, a missing or extra letter, and adjacent transpositions. Equally likely
matches are offered as links instead of being replaced at random. Product
codes, numbers and mixed letter-number identifiers are never corrected.

Use the **Dictionary** tab to map a visitor phrase to the canonical phrase.
A rule can be global or limited to one configured search scope. Rules can be
previewed, disabled, edited and deleted.

When the Products module is installed, a product editor also has a **Search**
section. It stores additional searchable names for that product only. These
names do not change the visible product title. After two characters, the field
suggests matching names already used by products and matching shared-dictionary
entries. A new value can still be entered manually. Rebuild the search index
once after upgrading; later document saves keep the typo vocabulary current.
Shared terminology remains explicit on the **Dictionary** tab; automatic typo
correction does not try to infer semantic synonyms.

## Algorithms and weights

Staff strategies:

- **Balanced** - words are combined using “OR”, exact matches and
  headings receive more weight;
- **Strict** - every word must be present;
- **Wide** — MySQL full-text weight and matches any word;
- **Titles only** - search by title and alias.

The exact match, title, and content weights are common to all areas.
The scope selects the algorithm but does not change these coefficients.

### Own strategy

Another module can register the algorithm in its `services.php`:

```php
use App\Modules\Search\AlgorithmRegistry;

AlgorithmRegistry::register(
    'editorial',
    'Редакционный',
    function (array $tokens, $query) {
        return array(
            'tokens' => $tokens,
            'operator' => 'and',
            'natural' => false,
        );
    },
    'Все слова обязательны; веса задаются настройками поиска.'
);
```

The handler returns a plan, not SQL. Valid keys: `tokens`, `operator`
(`and` or `or`), `natural`, `title_only`. SQL and parameters still form
search repository.

## Search hooks

The module registers stable events:

| Hook | Destination |
| --- | --- |
| `search.query.preparing` | Change row, area, page or limit to SQL. |
| `search.results.resolved` | Changing the full result after a search. |
| `search.output.rendering` | Changing the mode/query or completely replacing HTML before output. |
| `search.output.rendered` | Changing the finished output descriptor. |
| `search.results.projected` | Changing elements after JSON projection. |

Subscription of another module:

```php
'hooks' => array(
    array(
        'name' => 'search.query.preparing',
        'handler' => array(SearchSubscriber::class, 'prepare'),
        'priority' => 20,
    ),
),
```

Example handler:

```php
public static function prepare($event)
{
    $query = trim((string) $event->value('query', ''));
    $event->setValue('query', str_replace('ё', 'е', $query));
    return $event;
}
```

To completely replace HTML, the `search.output.rendering` handler can call
`$event->cancel()` and pass the string through `$event->setResult($html)`. For
adding JSON calculated keys change the array `$event->result()` to
`search.results.projected` and return the event.

## Performance and cache

- the index is separated from the working tables of documents;
- field values ​​for the API are loaded in one batch request onto the current page;
- search results are cached for 30 seconds;
- saving, deleting and reindexing reset the cache tag `search`;
- a request with cards receives IDs in order of relevance and does not repeat
  SQL search condition;
- mass reindexing resets the cache once per portion, not per document.

During installation, the module tries to add the InnoDB `FULLTEXT` index. If the server
MySQL does not allow such an index due to its parameters, the installation is still
ends and the search automatically uses the compatible comparison `LIKE`.
The functionality remains the same, but on a large base this mode will be slower.

## Diagnostics

**Nothing found after installation.** Run re-indexing and check
search sign for a document and its fields.

**The document is located by title, but not by field.** The document field must have
search is enabled. After changing the setting, rebuild the index.

**Card is blank or doesn't look right.** Check the main template and template
element of the selected request. The terms of the search query do not affect the search.

**Pagination is repeated twice.** Leave `[tag:pages]` only in the main
request template or remove it and use module pagination.

**There is no price or image in the JSON.** Add the appropriate alias fields to
API projection of the desired area. The indexing setting determines the search by field,
and the projection of the API is its presence in the response; these are different settings.

**The area does not find anything.** Check the code in `scope`, the selected categories and
algorithm. A strict algorithm requires the presence of every word.

**API responds with 422.** The string is shorter than the configured minimum size.

**API responds with 429.** Client exceeded request limit. For live search
use a delay after entry and cancel the previous `fetch`.

## Disable and delete

Disabling removes public routes and handlers, but preserves the index and
settings. Uninstallation removes the index table and all `search.*` settings.
Documents, categories, templates and the selected request are not deleted.
