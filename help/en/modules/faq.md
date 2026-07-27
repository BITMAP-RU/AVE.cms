# Questions and answers

The module stores independent collections of frequently asked questions. The collection can be opened
as a separate public page, insert into any document or template and
get via JSON API. Published questions can automatically generate
structured data `FAQPage`.

## Quick start

1. Open **Modules → Questions and Answers**.
2. Create a collection and set the stable system code, for example `delivery`.
3. Save the collection, add questions and format the answers in a rich-text editor.
4. Insert the collection into a document, block, category or template:

```text
[mod_faq:delivery]
```

The tag accepts a code or ID, but the code is more convenient when transferring data between
installations. The default public page will be available at
`/faq/delivery`, and the archive of all published collections is at `/faq`.

## Output modes

| Mode | Behavior |
| --- | --- |
| Accordion | The response is opened by the visitor through the native element `details`. |
| Only one is open | Opening the next question collapses the previous one. |
| Revealed list | All answers are immediately open and accessible without interaction. |

The **Schema.org** switch adds `FAQPage` JSON-LD. They only get into it
published issues of a published collection. The response HTML is removed from
structured data, but is retained in the normal public output.

## Templates

The collection template gets:

```text
[tag:id] [tag:code] [tag:title] [tag:description]
[tag:mode] [tag:count] [tag:items] [tag:schema]
```

The question template gets:

```text
[tag:id] [tag:collection-id] [tag:question] [tag:answer]
[tag:group] [tag:open]
```

`[tag:group]` and `[tag:open]` contain ready-made HTML attributes for standard
`details`. If the markup is being completely replaced, these tags may not be used.

The archive uses two templates. His shell gets `[tag:items]` and
`[tag:count]`, and one collection in the archive - `[tag:id]`, `[tag:code]`,
`[tag:title]`, `[tag:description]`, `[tag:count]` and `[tag:url]`.

## API and hook

`GET /api/v1/faq/{code-or-id}` returns a collection and an array of published
questions. Endpoint is suitable for your own accordion or live interface,
which doesn't use server-side HTML.

Filter `faq.rendering` receives an array:

```php
array(
	'collection' => $collection,
	'html' => $html,
)
```

The handler can modify `html` and return the entire array. The original answer counts
trusted content of the editor, therefore the right to manage the FAQ should be granted
Only employees who are allowed to post HTML.

## Delete

Deleting a collection cascades deletes all its questions. Uninstall the module completely
deletes both tables and FAQ data. Export the content you want before deleting
package.
