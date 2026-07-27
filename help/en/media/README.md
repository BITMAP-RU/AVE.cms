# Media and Thumbnails

The **Media** section manages files inside `/uploads`: downloads, folders,
renaming, deleting, converting images and automatic
miniatures. To view, you need the `Media: view` right, for changes -
`Media: file management`.

SVG and other active web formats are not accepted in custom storage:
when opened directly from a site's domain, they are capable of executing embedded code.
Store vector icons of the theme in its own assets, and for the media library
export the image to PNG or WebP. Already existing SVGs inside
`/uploads` are sent only with protective headers and as an attachment.

## What files does the system create?

AVE.cms distinguishes between the original and four types of derivative files:

| View | Location | Destination |
| --- | --- | --- |
| Automatic thumbnail | `th/` next to the original | Quickly display an image of a given size on the site and in the panel. |
| WebP copy | Next to the original, with the same name | An alternative format for the `<picture>` tag and other responsive loading options. |
| Editor's copy | `_derivatives/` | The individual result of cropping, resizing, or formatting. |
| Temporary result | `_derivatives/_preview/` | Live editor preview; old temporary files are deleted automatically. |
| Rough document upload | `.drafts/` | A temporary folder closed from the media browser until the document is successfully saved. |

The directories `th` and `_derivatives` are service directories and are not shown as
regular file browser folders. Do not add raw materials or
reference temporary files from `_preview`.

## New document files

The new document does not yet have an ID, so media fields do not load files immediately into
destination folder. AVE.cms gives the editor a one-time download session and temporarily
places files in `/uploads/.drafts`. The session is tied to the employee, document and
specific field. The folder is not shown in the **Media** section.

If saving is successful, the system receives the document ID and transfers the files to the folder
fields, changes the paths in the value and only then commits the document. If there is an error
validation, database or category code, the transfer is rolled back. Unused drafts
are deleted automatically after 48 hours upon subsequent openings of the editor.

The final path is set separately for each file field in the constructor
headings: **Type settings → Document file folder**. For example:

```text
articles/doc_%id
```

Substitutions available:

| Substitution | Meaning |
| --- | --- |
| `%id` | ID of the saved document. |
| `%rubric_id` | Category ID. |
| `%rubric_alias` | Rubric alias. |
| `%field_id` | Category field ID. |
| `%field_alias` | Field alias. |
| `%Y`, `%m`, `%d` | Year, month and day of saving. |

The path can be written with or without `/uploads`. Empty setting gets
safe standard `documents/%id/%field_alias`. Design `..` and path to
service `.drafts` are prohibited.

The **Load** button creates a new file and includes it in the transactional transfer.
The **Select file** button refers to an already existing media library object: this
the file remains in its folder. When transferring a JPG or PNG, the adjacent WebP copy
the same base name is transferred along with the original; automatic thumbnails
will be rebuilt using the new URL.

## Automatic generation

The thumbnail is created lazily - on the first HTTP request to its URL. Until the first
The file on the disk may not open. For example, for the source:

```text
/uploads/catalog/table.jpg
```

size `c320x320` gets permanent URL:

```text
/uploads/catalog/th/table-c320x320.jpg
```

When the first request is made simultaneously, the generation is protected by a lock: several
visitors do not create one thumbnail in parallel. The finished file is sent with
`ETag`, `Last-Modified` and browser cache.

If the source changed later than the thumbnail, it will be recreated the next time
handling. Changing the generator version, JPEG quality or progressive mode
also invalidates old automatic thumbnails in the affected folder.

## Size modes

The size is written as `<mode><width>x<height>`, for example `c320x320`.
The width and height must be between 1 and 4096 pixels, and the resulting area must not be
more than 4,000,000 pixels.

| Code | Behavior | When to use |
| --- | --- | --- |
| `c` | Fills the entire size, maintaining proportions; the excess is cut off in the center. | Square cards and avatars. |
| `f` | Fits the entire image into an accurate canvas; empty space is filled with white. | Catalogs where the item cannot be cut. |
| `t` | Proportionally fits the image into the area in legacy mode without centering it with white fields. | Compatible with existing templates. |
| `r` | Stretches the image exactly to the specified width and height. | Only when a change in proportions is permissible. |
| `s` | Performs smart crop to exact size. | Covers with automatic cropping. |

You can add a final `r` to the `c`, `r` and `s` modes, for example
`c480x320r`: the generator will allow you to swap the width and height depending on
from the orientation of the original.

For new templates, `c` for cards and `f` for images are usually sufficient,
which need to be shown in full. `r` mode should not be used for photographs
goods and people.

## Use in templates

In the PHP field template, use the field context rather than collecting the path manually:

```php
<?php $image = $template->first(); ?>
<?php if (is_array($image) && !empty($image['url'])): ?>
  <img src="<?= $template->escape($template->thumbnail($image['url'], 'c320x320')) ?>"
       alt="<?= $template->escape(isset($image['description']) ? $image['description'] : '') ?>">
<?php endif; ?>
```

The following tag is available in stored document, query and block templates:

```text
[tag:c320x320:/uploads/catalog/table.jpg]
```

For the module code, use the standard URL generator:

```php
use App\Frontend\Media\ThumbnailUrl;

$url = ThumbnailUrl::make(array(
    'link' => '/uploads/catalog/table.jpg',
    'size' => 'c320x320',
));
```

The method returns the URL or `false` if the source is not available, is located outside
`/uploads`, the size is incorrect or is not in the white list. Don't create
directory `th` and the file name yourself: its name is specified by the system setting
and may change.

The remote `http(s)` source is supported only through a secure downloader.
The host must resolve exclusively to public IP, ports are limited to `80/443`,
each redirect is checked again, and the connection is bound to the checked one
DNS address. The response ends at 25 MB; JPEG, PNG, GIF and WebP are accepted no more than
12,000 pixels on the side and 50 megapixels. Localhost, private/reserved networks, metadata
endpoints, URL with login/password and non-standard ports return placeholder.
Don't load remote images yourself from field-type or module.

## Limit available sizes

The parameters are located in **Settings → Constants → Thumbnail Generation**:

| Constant | Destination | Default |
| --- | --- | --- |
| `THUMBNAIL_DIR` | The name of the service folder for automatic thumbnails. | `th` |
| `THUMBNAIL_SIZES` | Mandatory white list of allowed sizes. | `t128x128,f128x128,c320x320,f480x360,t450x450` |
| `JPG_QUALITY` | JPEG thumbnail quality. The actual range of the generator is 40–100. | `90` |
| `JPG_PROGRESSIVE` | Progressive JPEG. | Included |
| `THUMBNAIL_IPTC` | Adding basic IPTC data to JPEG. | Off |
| `THUMBNAIL_CACHE_LIFETIME` | Browser cache life in seconds. | `1209600` |

Generation is only allowed for values ​​from `THUMBNAIL_SIZES`. Unknown
the preset receives `404` before reading the source and starting GD. Empty or damaged
runtime setting is rolled back to the minimum system set `t128x128`,
`f128x128`, `c320x320`, `f480x360`, `t450x450`; constant editor doesn't allow
explicitly save an empty or invalid list.

Open the constant `THUMBNAIL_SIZES` and click **Collect**. The panel will check
server theme, core and installed modules files, then text values
current database tables. The result will be entered into the field, but will be applied only after
clicking **Save**.

The collector finds literal values ​​like `c320x320`. Size which code
collects dynamically from individual variables, you need to add it manually. Repeatedly
run the collection after installing a module, migrating a theme, or changing templates in
DB. Sizes removed from the list are no longer generated; already created files
can be removed using the **Clear preview** button.

Historical generation via `?thumb=...` is disabled. Requests with this parameter
and address `/inc/thumb.php` correspond to `404`; use a deterministic URL,
`ThumbnailUrl::make()` or `thumbnail()` field context.After changing `THUMBNAIL_DIR`, the old directory is not automatically migrated.
First update the templates and check the write permissions, then delete the old ones
service folders manually if they are no longer used.

## Management in the file browser

There are two clearing options available on the **Media** page:

- the **Clear preview** button in the header deletes automatic thumbnails of the current
  folders;
- a button with the same icon next to a folder clears the preview directly in the selected one
  folder.

Originals, adjacent WebP files and copies from `_derivatives` are not
are deleted. The required automatic thumbnails will reappear the next time
handling. Cleanup is applied only to the selected folder, and not recursively to
entire tree `/uploads`.

For a single image, open its card. The editor allows you to choose
frame, size, mode, format and quality, see live result and save
new copy in `_derivatives`. The source is not overwritten.

## WebP

The **In WebP** button creates a file with
same base name:

```text
/uploads/catalog/table.jpg
/uploads/catalog/table.webp
```

The button is only available if the GD library can read and write WebP.
The existing WebP copy is not overwritten. To replace it, delete the old copy
and perform the conversion again.

Example output with fallback JPG:

```php
<picture>
  <source srcset="<?= $template->escape($template->webp($image['url'])) ?>"
          type="image/webp">
  <img src="<?= $template->escape($image['url']) ?>" alt="">
</picture>
```

The `webp()` method only builds the expected path. Before making such a conclusion, make sure that
The WebP copy is actually created, or form `<source>` in the field type after
file check.

## Renaming and deleting

When you rename a JPG or PNG, the panel also renames the adjacent WebP copy
with the same base name. Before the operation, the conflict of the new name is checked.
Automatic thumbnails and editor copies for the previous name are cleared.

When you delete the original JPG or PNG, the following are automatically deleted:

- his miniatures from `th`;
- related editor results from `_derivatives`;
- a neighboring WebP copy with the same base name;
- thumbnails of this WebP copy.

Removing a standalone WebP does not remove the JPG or PNG: WebP can stand alone
original. Deleting an entire folder deletes all of its contents, including
service derivatives.

## If the preview is not created

Check in order:

1. The source exists inside `/uploads` and is opened directly.
2. The size has a format like `c320x320`, does not exceed 4096 pixels on the side
   and 4,000,000 pixels in area.
3. The size is in the mandatory list `THUMBNAIL_SIZES`.
4. The PHP process can create folders and files inside the source directory.
5. GD supports source and result format; WebP requires a separate one
   WebP support in GD.
6. After replacing an image, clear the preview of its folder if the time of change
   the source was saved by an external tool and did not become newer than the thumbnail.

If the source is missing, the generator tries to use
`/uploads/images/noimage.png`. Add this file to your project if your theme needs it
single placeholder.
