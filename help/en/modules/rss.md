# RSS feeds

← [Back to the “Modules” section](README.md)

The `rss` module publishes documents of the selected category in RSS 2.0 format. Channel
has a permanent human-readable address `/rss/<alias>` and does not require inserting a tag
to the site template.

## Channel setup

1. Open **Modules → RSS Feeds** and create a feed.
2. Set a title, unique alias and description.
3. Select a source category.
4. Select the title field or standard document title.
5. Select the description field or turn off the item description.
6. Specify the number of elements and the allowed length of the description.

Alias ​​can contain Latin letters, numbers, hyphens and underscores. Old
the address `/rss/rss-<id>.xml` is redirected to the new URL. The response has a type
`application/rss+xml` and public cache-control for five minutes.

The channel contains available published documents of the selected category. Links
are built using public URLs of documents. Length limit `0` retains text up to
the standard “more” separator, if present.

Rights:

- `view_rss` — view the list and settings;
- `manage_rss` - creating, changing and deleting channels.

Uninstallation deletes the channel table, but not documents and categories.
