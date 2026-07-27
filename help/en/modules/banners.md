# Banners

The module creates named advertising spaces and selects for each public
display one available banner. Images are not copied to the module directory:
Links to files in the shared media library are saved.

## Quick start

1. Open **Modules → Banners → Advertising Spaces**.
2. Create a place, for example `Site header`, with the code `header-main`.
3. Add a banner, select an image, a link and that ad space.
4. Insert a tag into a document, block, category or template:

```text
[mod_banner:header-main]
```

For compatibility, the location ID and the `[mod_banners:CODE]` form are supported, but
stable system code is more convenient: it does not change when moving content between
installations.

## Selection strategies

| Strategy | Behavior |
| --- | --- |
| By weight | The probability of selection is proportional to the weight of the active banner. |
| By chance | All available banners have the same probability. |
| Evenly | The banner with the lowest total number of impressions is selected. |

Before choosing, the status of the place and banner, the beginning and end of the period are checked,
impression limit and click limit. `0` in the limit field means "no limit".

## Public templates

The ad space template receives the tags:

```text
[tag:category-id] [tag:category-code] [tag:category-title]
[tag:banner-id] [tag:banner]
```

Banner template gets:

```text
[tag:id] [tag:title] [tag:image] [tag:webp] [tag:picture]
[tag:url] [tag:click-url] [tag:alt] [tag:target]
[tag:width] [tag:height]
```

To count the click, the link must use `[tag:click-url]`. `[tag:url]`
contains the final address and is intended for metadata or its own
JavaScript, but navigating directly to it does not increment the counter.

## API and hooks

`GET /api/v1/banners/{code}` returns the selected HTML in JSON and is suitable for
dynamic banner change without rebooting. The response is not cached, but successful
selection is considered a showing.

Filter `banners.rendering` receives an array:

```php
array(
	'banner' => $banner,
	'html' => $html,
)
```

The handler can modify `html` and return an array. Do not repeat
the select request inside this hook: the display is already committed.

## Delete

Deleting an advertising space cascades deletes its banners and statistics. Files in
`/uploads` and the thumbnails created for them remain in the media library. Uninstall module
deletes all three module tables.
