# Field type reference

← [To the section “Document fields”](README.md)

AVE.cms shows all registered types in the section **Categories and fields →
Field types**. There the type can be enabled or hidden from the new categories constructor.
Disabling does not remove the handler or break already created fields.

## Recommended types

These types are for new categories. They combine several narrow legacy-
types, store settings in JSON and have predictable formats for APIs, filters and
public templates.

| Code | What does | How is it different from the old types |
| --- | --- | --- |
| `content` | Plain text, Tiptap or CodeMirror depending on the mode setting. | Replaces the separate selection between `multi_line`, `richtext` and `code`; The mode can be configured without changing the field type. |
| `number` | Number, amount, percentage, magnitude or rating; supports unit, precision and numeric index. | Replaces `single_line_numeric*`; stores one canonical number, and the format refers to settings. |
| `date_time` | Date or date and time with a custom public format. | One contract instead of scattered options `date`; stores Unix timestamp. |
| `period` | The beginning and end of an event or other time period. | Two related dates are stored as one structured value. |
| `choice` | Single or multiple choice with stable keys and signatures. | Replaces `drop_down`, `drop_down_key`, `multi_select` and checkboxes; the signature can be changed without changing the saved key. |
| `contact` | Email, phone or URL with security verification and secure public link. | Validation and output method are part of the site type, not the site template. |
| `color` | HEX color with visual selection and manual input. | Normalizes the value and does not require a regular text field with its own validation. |
| `range` | Lower and upper limit with unit of measurement. | The boundaries are stored together in JSON and are available as a single value. |
| `dimensions` | Length, width and height with a common unit of measurement. | Does not require three separate fields or a delimited line. |
| `packages` | Sortable list of packages: dimensions and weight of each package. | Supports any number of boxes and stores them in a structured manner. |
| `address` | A structured address with optional coordinates. | Parts of the address are available separately, but belong to the same field. |

The **New** label in the panel means the recommended native contract, not what
experimental type. For a new project, enable only the types you need first
from this table.

## Text and simple values

The following types are retained for compatibility with existing categories and data.| Code | Destination |
| --- | --- |
| `single_line` | Short line. For new fields, use `content` in plain text mode. |
| `single_line_numeric` | One legacy number. For new fields use `number`. |
| `single_line_numeric_two` | Two numeric parts separated by `|`. A suitable structural type is preferred. |
| `single_line_numeric_three` | Three numeric parts separated by `|`; for dimensions use `dimensions`. |
| `multi_line` | Full size legacy rich text. For new fields use `content`. |
| `multi_line_simple` | Rich text of medium height. Height is now setting `content`. |
| `multi_line_slim` | Compact rich text. Height is now setting `content`. |
| `richtext` | Formatted HTML. For new fields, use `content` in Tiptap mode. |
| `code` | Source code from CodeMirror. For new fields, use `content` in code mode. |
| `checkbox` | One boolean value. Used when a separate switch is needed. |
| `date` | Legacy date or date-time. For new fields use `date_time`. |
| `link` | URL or relative link without modes `contact`. |

## Lists and options

| Code | Destination |
| --- | --- |
| `drop_down` | One option from the list of legacy values. |
| `drop_down_key` | One option is where the key and visible signature are stored separately. |
| `multi_select` | Multiple values ​​from a given set. |
| `checkbox_multi` | Several options shown by checkboxes. |
| `multi_checkbox` | Multiple legacy selection with JSON/serialize compatibility. |
| `multi_list` | Repeatable parameter/value pairs. |
| `multi_list_single` | Repeatable single-column list. |
| `multi_list_triple` | Repeatable list in three parts. |
| `multi_links` | Repeatable links with captions. |

The new `choice` is preferable to the old choices: it stores stable
the key is separate from the signature and works the same in single and multiple
modes. Repeatable arbitrary records remain a task of types
`multi_list*`, not `choice`.

## Media and files

| Code | Document value | Destination |
| --- | --- | --- |
| `image_single` | `url`, `description` | Single image: Load or select from media browser. |
| `image_multi` | List `url`, `description` | Regular sortable gallery. |
| `image_mega` | List `url`, `title`, `description`, `link` | Expanded gallery with metadata and link for each image. |
| `download` | `url`, `title` | One file to download. |
| `doc_files` | List `name`, `description`, `url` | Multiple document files. |
| `youtube` | URL/ID and video parameters | Embedded YouTube video. |
| `text_to_image` | Text | Legacy type that turns text into an image when output. |Media types read old strings and PHP `serialize`, but on new save
write JSON. Public templates receive the same structure regardless of
of the historical format in which the meaning lies.

Each type `image_single`, `image_multi`, `image_mega`, `download` and
`doc_files` there is a setting **Document file folder**. It determines the final
new download path and supports `%id`, `%rubric_id`, `%rubric_alias`,
`%field_id`, `%field_alias`, `%Y`, `%m`, `%d`. While the ID of the new document is unknown, the file
stored in a closed draft and transferred transactionally after saving.
For details, see [Media and Thumbnails](../media/README.md#файлы-нового-документа).

## Document links

| Code | Destination |
| --- | --- |
| `tags` | A set of document field tags. Not to be confused with document system tags. |
| `doc_from_rub` | Selecting one document from one or more categories. |
| `doc_from_rub_all` | Automatic work with all documents of the selected category; saved for legacy scripts. |
| `doc_from_rub_check` | Manual multiple selection of related documents. |
| `doc_from_rub_search` | Multiple selection of documents through search. |
| `analoque` | Ordered analogues or documents sold together. |
| `teasers` | An ordered list of documents to display as teasers. |
| `catalog` | Linking a document to sections of the catalog designer. |

## How to switch from the old type

Do not change `rubric_field_type` directly in the database. Even similar fields may differ
value format, index and public template.

| Was | Typically used for new field |
| --- | --- |
| `multi_line`, `multi_line_simple`, `multi_line_slim`, `richtext`, `code` | `content` with the desired editor mode and height |
| `single_line_numeric` | `number` |
| `single_line_numeric_three` for dimensions | `dimensions` |
| Set of individual box fields | `packages` |
| `drop_down`, `drop_down_key`, `multi_select`, `checkbox_multi`, `multi_checkbox` | `choice` |
| Two separate start and end fields | `period` |
| Normal string for email, phone or URL | `contact` |

Existing legacy fields do not need to be migrated just for the sake of the new editor:
they are full registered types. Migration is needed when
requires a new structured format, a single API contract or simplification
set of category fields.
