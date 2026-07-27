# SEO audit

← [Back to the “Modules” section](README.md)

Module `seoaudit` checks published documents and groups signals by
metadata, URL, indexing and service settings. Main audit works
read-only and calculated based on the current database.

## What is being checked

- availability, approximate length and duplicates `title` and `description`;
- repetition of title in description and HTML inside description;
- empty, incorrect, long or uneven alias;
- capital letters and underscores in URLs;
- `noindex`, `nofollow` and expired publication period;
- exclusion of a document from internal search;
- lack of keywords for internal search.

Title lengths of 10–65 and description 50–160 characters are guidelines and not
guaranteed by search engine requirements. Service comments do not reduce
SEO score. The interface list is limited to 300 problem documents, but summary
counters are calculated over the entire tested set.

## Autocomplete

For a document, you can first open a proposal without an entry, then fill out
metadata. The module writes only empty `description` and `keywords` and not
grinds hand work. Batch operation processes up to 200 documents per
launch; If there is any leftover, you can repeat it.

After application, the associated document caches are cleared. Before bulk filling
check the sentences on several documents of different headings.

Rights:

- `view_seoaudit` — report and main widget;
- `manage_seoaudit` - proposal and record of metadata.

The module does not send data to external SEO services and does not check positions,
search engine indexing, sitemap or actual HTML after client-side JavaScript.
