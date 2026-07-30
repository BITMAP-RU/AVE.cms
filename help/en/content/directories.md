# Dictionaries

← [Back to “How the site is assembled”](README.md)

A dictionary is a shared managed list of values for fields. It is not a category,
does not create documents, and has no URL, SEO metadata, templates, or publication
permissions.

## Choosing the right source

| Task | Recommended source |
| --- | --- |
| A small list used by one field | Field-local values |
| One list shared by several fields | Dictionary |
| A related entity with its own page and fields | Category documents |

## Creating a dictionary

1. Open **Categories and fields → Dictionaries**.
2. Click **Create dictionary**.
3. Enter a clear name. The technical code can be generated automatically.
4. Open the dictionary and add values.

Each value has a user-facing label, a stable stored key, a sort order, and an
active state. The label may be renamed without rewriting documents. Avoid
changing a key after it is used.

## Connecting a field

1. Open the category field builder.
2. Select a choice, dropdown, or multiple-choice field.
3. Set **Value source** to **Shared dictionary**.
4. Select the dictionary and save the category schema.

The document storage format does not change. Existing public templates therefore
continue to work.

A dictionary cannot be deleted while fields use it. Disabled values are removed
from new selections but remain readable in documents that already store them.
