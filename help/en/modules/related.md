# Similar materials

Module `related` selects other published ones for the current document
documents. It's not just for products: profiles can be configured separately for
news, articles, blog posts, help pages and any of your own categories.

The module does not impose a card. The result is output using our own HTML templates
profile or is transferred to an existing AVE.cms request, where they continue to work
regular templates for fields and site cards.

## Quick start

1. Install the module and open **Modules → Related Content**.
2. On the **Index** tab, click **Reindex**.
3. Open the created profile `default` and select a strategy and categories.
4. Insert a category, document or block into the template:

```text
[mod_related:default]
```

5. Open a real document containing keywords or tags and check
   result.

After the initial build, the index is updated automatically when you save and
deleting a document. Repeated full indexing is needed after bulk import
directly into the database or changes to the rules of a large number of documents.

## Strategies

| Strategy | How documents are selected |
| --- | --- |
| Relevance | By common keywords, tags and, if included, title words. |
| Ring | The following documents in the given order; at the end of the list, the selection continues from the beginning. |
| Hybrid | First, relevant documents, then the missing quantity is collected by the ring. |

Active manual connections always come first if enabled in the profile **Take into account
manual connections**. They take up space in the overall limit and are not duplicated in
automatic part of the result.

### Relevance and weights

The index stores three independent sources:

| Source | What is indexed | When useful |
| --- | --- | --- |
| Keywords | Phrases from `document_meta_keywords` separated by comma, semicolon or newline. | The editor explicitly describes the topic of the document. |
| Tags | Values ​​`document_tags`. | The site already has a unified thematic dictionary. |
| Title | Meaningful words with a length of four characters after normalization. | Keywords and tags are not filled in for all documents. |

The weight specifies the relative strength of the source from `1` to `20`. If the source matches
the current document and the candidate source weights are multiplied. For example,
the match of two key phrases with weight `8` is stronger than a random common word
header with weight `2`.

Heading is turned off by default: common words may produce noise. Its reasonable
include for old content where keywords and tags are not completely filled out.

### Ring selection

The ring does not select random documents. It goes further from the current document
according to one of the stable orders:

- **Document position** - manual position, then date and ID;
- **Publication date** - from new materials to old ones;
- **Document ID** — creation order.

When the list ends, the module continues from the beginning. Current document and
records already selected are excluded. This mode replaces the old ring mode
system unit and is useful for “Next materials” or uniform linking.

## Profile area

An empty list of categories allows documents of any type as a result. Selected
categories limit only similar materials, and the original document may
belong to another category. For example, a profile with the “Articles” section can be
insert catalog categories into the template and receive only useful articles.The **Current rubric only** switch additionally requires that the candidate
belonged to the same category as the open document. This is convenient for news
tapes. For recommendations between different content types, leave it turned off.

The selection never includes the current document, deleted documents, those that have been turned off, or those that have not yet been
published and already expired documents, as well as the system 404 page.

## Manual connections

On the **Manual connections** tab you can select:

1. profile;
2. the source document on the page of which the selection is performed;
3. similar document;
4. order;
5. communication activity.

Both documents are selected by live search by ID, title or alias. One document
cannot be associated with itself, and the same pair within the same profile cannot
duplicated. The disabled connection remains, but does not affect the public result.

## Module call tags

### Current document

```text
[mod_related:default]
```

`default` - stable system profile code. ID is acceptable instead of code, however
Code is more portable between installations.

### Explicit document

```text
[mod_related:default:125]
```

The second argument is the ID of the source document. This call is useful in a block or
external template where there is no current page context.

### Compatible with old module

```text
[mod_moredoc]
```

The Legacy tag calls the `default` profile for the current document. This is transitional
synonym: new templates should be written using `[mod_related:default]`, since
it clearly captures the profile.

## Output methods

### Built-in templates

In this mode, the profile stores a list wrapper, one card, and an optional
HTML for empty result. AVE.cms does not include the public CSS module:
classes, grid and adaptability are determined by the theme of a particular site.

### Existing AVE.cms request

Select **Existing request** and specify a request with ready-made cards. Module
passes it the already selected IDs in the calculated order. Request conditions
do not select documents; its main template, element template,
fields, system blocks and cards of the current topic.

If the request is deleted or unavailable, the module safely uses the built-in
profile template.

## Container template tags

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Numeric profile ID. |
| `[tag:code]` | Stable system profile code. |
| `[tag:title]` | Profile name, for example “Related Articles”. |
| `[tag:description]` | Profile description. Special characters are escaped. |
| `[tag:count]` | Actual number of cards. |
| `[tag:items]` | Glued HTML of all cards. This tag should be left in the shell. |

Example:

```html
<section class="related-content" data-profile="[tag:code]">
	<h2>[tag:title]</h2>
	<div class="related-content__grid">[tag:items]</div>
</section>
```

## Card template tags

| Tag | Meaning |
| --- | --- |
| `[tag:index]` | Card number from `1` in the current result. |
| `[tag:docid]` | ID of the found document. |
| `[tag:rubric-id]` | ID of its category. |
| `[tag:url]` | Public URL of the document. |
| `[tag:title]` | The title of the document, escaped for HTML. |
| `[tag:description]` | Meta description; in its absence, a teaser is used. HTML is removed. |
| `[tag:date]` | Date of publication in the format `dd.mm.yyyy`. |
| `[tag:datetime]` | Publication date in `yyyy-mm-dd` format for `<time datetime>`. |
| `[tag:views]` | Internal document view counter. |
| `[tag:image]` | Ready `<img>` of allowed size or an empty string. |
| `[tag:image-url]` | The URL of the document's first source image. |
| `[tag:thumb]` | URL preview from the selected system preset. |
| `[tag:score]` | Numerical relevance weight; for manual communication it has a high service priority, for a ring it is equal to `0`. |
| `[tag:matches]` | Number of common normalized features. |
| `[tag:source]` | Result source: `manual`, `relevance` or `ring`. |

`[tag:image]` is convenient for a regular card. If the theme uses `picture`,
assemble it yourself from `[tag:image-url]` and `[tag:thumb]`.

Example:

```html
<article class="related-card" data-source="[tag:source]">
	<a href="[tag:url]">[tag:image]</a>
	<div>
		<time datetime="[tag:datetime]">[tag:date]</time>
		<h3><a href="[tag:url]">[tag:title]</a></h3>
		<p>[tag:description]</p>
	</div>
</article>
```



## JSON API

```http
GET /api/v1/related/{profile}/{document}
```

Example:

```text
/api/v1/related/default/125
```

The answer uses the general contract `{success, data}`. `data` contains the profile,
Source document ID and array `items`. The element has
`document_id`, `rubric_id`, `title`, `url`, `description`, `published_at`,
`views`, `image`, `thumb`, `score`, `matches` and `source`. The API uses the same
publishing restrictions and order that server tag but does not return HTML
template.

## Hooks

###`related.results.resolved`

Called after selecting and loading document data, before HTML:

```php
array(
	'profile' => $profile,
	'document' => $currentDocument,
	'items' => $items,
)
```

The handler can filter, pad, or reorder `items`, then
should return the entire context array.

###`related.rendering`

Called after an inline template or an existing query:

```php
array(
	'profile' => $profile,
	'document_id' => $documentId,
	'items' => $items,
	'html' => $html,
)
```

Change `html` if the integration module needs to wrap the result or completely
replace view.

## Cache and index

The profile result for a document is cached for 15 minutes. The cache is completely cleared
when saving or deleting a document, changing a profile, manual communication and complete
reindexing. Therefore, an outdated card should not remain after work in
panels.

The index stores only normalized features, ID and category. Full details
cards are read from current documents when resolving the result. Uninstall
module deletes profiles, index and manual connections completely.

## Practice profiles

### Similar news

- strategy: **Hybrid**;
- heading: “News”;
- only current category: enabled;
- keywords `8`, tags `6`, title disabled;
- limit: `4`.

### Continue reading

- strategy: **Ring**;
- headings: “Articles” and “Blog”;
- current category only: disabled;
- order: date of publication;
- limit: `3`.

### Product analogues

- strategy: **Relevance**;
- product headings;
- keywords and tags;
- manual connections are included for precise fastening of analogues;
- output through a request with a reference product card.
