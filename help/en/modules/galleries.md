# Galleries

← [Back to the “Modules” section](README.md)

Module `galleries` collects images from the shared media library into managed collections.
Files are not copied: the gallery stores the URL, captions, order and output settings.
Grid and slider, WebP and enabled thumbnail presets are supported.

## Insertion and public page

```text
[mod_gallery:3]
```

Number — ID of the published gallery. Used for a standalone page
URL `/galleries/<alias>`; the basic part can be changed in the settings. On the page
Pagination `gpage` works, and the insertion tag displays the entire collection.

## Gallery template tags

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Gallery ID. |
| `[tag:title]` | Escaped name. |
| `[tag:description]` | Ready description or empty line. |
| `[tag:mode]` | Mode `grid` or `slider`. |
| `[tag:columns]` | The configured number of columns. |
| `[tag:items]` | Collected images in a given order. |
| `[tag:count]` | The number of elements of the current output. |
| `[tag:pagination]` | Public page navigation. |

## Image tags

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Gallery item ID. |
| `[tag:index]` | Index in the current issue, starting from `0`. |
| `[tag:source]` | URL of the source file for the lightbox. |
| `[tag:thumb]` | URL of the thumbnail of the selected preset. |
| `[tag:webp]` | The URL of the associated WebP source or an empty string. |
| `[tag:picture]` | Ready `<picture>`/`<img>` with lazy loading. |
| `[tag:title]` | Escaped header. |
| `[tag:alt]` | Alt-text; if empty, title is used. |
| `[tag:description]` | Ready signature or empty line. |
| `[tag:link]` | Additional design element reference. |

The standard lightbox uses `data-gallery`, `data-gallery-items`,
`data-gallery-item`, `data-gallery-open` and `data-gallery-index`. For completely
In your own JavaScript, these attributes can be replaced along with the theme's assets.

The thumbnail is built only through an enabled preset. If the generator cannot
create it, the renderer safely uses the source URL. Created for WebP
separate `<source>` only when the associated file exists.
The new gallery by default receives the first preset from the system white list;
after changing the list, unavailable old values are normalized by migration
module.

## API and hook

```text
GET /api/v1/galleries/3
```

JSON contains collection settings and elements: `file_url`, `webp_url`, title,
alt, description, link and status. HTML is not imposed on the API, so the endpoint
suitable for your slider or mobile client.

The filter `galleries.rendering` receives the array `gallery` and the finished `html`.
Deleting a gallery clears its entries, but does not delete the sources and thumbnails of the general
media libraries.
