# Content model tools

← [Back to “How AVE.cms builds a site”](README.md)

## Directories

A directory is a regular rubric marked for reusable values: brands, cities,
authors, equipment types, and similar entities.

1. Open **Rubrics and fields → Directories** and create a directory.
2. Add the required value fields in the rubric editor.
3. Create directory documents.
4. Add a **Document from rubric** field to the working rubric and select the
   directory as its source.

Directory values retain the standard document API, permissions, revisions,
search, and import behavior.

## Bidirectional document relations

The source document keeps the selected IDs in its regular relation field.
AVE.cms updates a relation index and can therefore show both directions:

- **Links to** lists documents selected by the current document;
- **Linked from** lists documents which select the current document.

The reverse side is derived and is never written as a second field value.

## Computed fields

A computed field derives a stored, read-only value from other field aliases.

| Mode | Example |
| --- | --- |
| Text | `{{ brand }} {{ model }}` |
| Number | `(price * quantity) - discount` |

Number formulas allow field aliases, parentheses, and `+`, `-`, `*`, `/`.
They never execute PHP. Division by zero, an unknown alias, or invalid syntax
returns the configured fallback value.

The value is recalculated for preview and save. Existing templates, queries,
exports, and APIs receive the resulting ordinary field value.

## Field set library

A field set is a reusable schema for a common task such as an article, news
item, employee card, SEO metadata, or a compact product core.

- **Copy** creates independent fields.
- **Linked set** remembers its source and can be synchronized explicitly.

AVE.cms previews aliases and conflicts before applying a set. Existing fields
are not overwritten silently.

## Public compatibility

These tools do not switch public templates automatically. A directory remains
a rubric, relations and calculations remain fields, and a set only creates
ordinary fields. They can be introduced one rubric at a time.
