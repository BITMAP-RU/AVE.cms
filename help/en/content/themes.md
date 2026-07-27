# Public site themes

The **Themes** section manages public design files without uploading via FTP:
CSS, JavaScript, images, SVG, fonts and Twig views. In
in the **Settings** tab you can also select who collects the common page: native
AVE.cms templates or file Twig theme wrapper.

## Where are the topics

Each theme has a separate directory:

```text
templates/<код-темы>/
├── theme.json
├── css/
├── js/
├── images/
└── fonts/
```

The active theme is selected in the **Themes** section. The directory code consists of lowercase
Latin letters, numbers, `_` and `-` and starts with a letter.

Service directories `include`, `templates`, `lang`, preview and hidden directories
files are not shown in the file manager. This separates static assets from
compiled templates and other internal theme files.

## Connecting CSS and JavaScript

The **Connections** tab edits `theme.json`. In it you can:

- select CSS files and set `media`;
- select JS files and include `defer`, `async` or `type="module"`;
- change the order of files by dragging and dropping;
- indicate the name and version of the theme.

The main website template uses tags:

```html
<head>
  [tag:theme-styles]
</head>
<body>
  [tag:maincontent]
  [tag:theme-scripts]
</body>
```

One single file can be obtained regardless of the registry:

```text
[tag:asset:images/logo.svg]
```

The engine checks for the existence of the file and adds a short SHA-1 version to the URL.
After saving the file, the browser receives a new address like `?v=...`, so
old CSS or JavaScript does not remain in the cache.

New tags are explicit. Creating or activating a theme does not add them to
existing template and does not change the current public appearance of the site.

## Storefront settings

A theme can declare its own editable settings in `theme.json`. For
Each theme they automatically appear in the **Themes -> Settings** tab.

This way you can edit contacts, signatures and collection limits without changing the files.
The composition of the fields belongs to the theme: after switching the theme, Adminx will show the settings
new design, and the meanings of the previous theme will be saved under its own
keys.

A scalar value can also be displayed in a native template, query or block:

```html
<a href="[tag:theme-setting:retail_phone_href]">
  [tag:theme-setting:retail_phone]
</a>
```

The tag only reads the parameter declared by the active theme and safely escapes
him. For standard fields `retail_phone` and `wholesale_phone` additionally
Keys with the suffix `_href` are available.

A field of type `section_order` controls sections of the file Twig wrapper only.
The order of the native page is changed in the template of its category: blocks are clearly visible there,
requests and modular tags from which the page is assembled. Section switch is not
deletes her documents or goods.

## Native mode and theme wrapper

In the **Settings** tab there are two ways to build a public page:

- **Native AVE templates** - general site template, categories, queries, blocks and
  navigation assemble the page in a standard way. This is the main and recommended
  mode;
- **Twig theme wrapper** - the file from `page_shell` controls the overall structure
  pages. The mode is available only if such a file is declared in `theme.json`.

The switch does not disable the design. Connected CSS/JS, images and
Module view overrides work in both modes. He changes
only a way to assemble a common page.

```json
{
  "presentation_mode": "native",
  "page_shell": "views/storefront/page.twig",
  "page_shell_mode": "replace"
}
```

After saving, the public cache is cleared automatically. Thanks to this you can
prepare a Twig wrapper in advance, test it, and then return to native
templates with one switch.

## Editing and revisions

CSS, JavaScript, JSON, XML, SVG and text files open in the code editor.
The previous version is retained before overwriting. The **Revisions** tab allows you to:

- view the contents and author of the change;
- restore the selected version;
- delete one revision or the entire history of a topic.

Binary images and fonts are not copied to the revision table. For them
backup use theme export.

## Creation and ZIP

The **Create** button generates a theme skeleton with `css/app.css`, `js/app.js` and
`theme.json`. **Export** collects only allowed public theme files.

The imported ZIP must contain `theme.json`:

```json
{
  "format": "ave-theme-v1",
  "name": "Название темы",
  "version": "1.0.0",
  "styles": [
    {"file": "css/app.css", "media": ""}
  ],
  "scripts": [
    {"file": "js/app.js", "defer": true, "async": false, "module": false}
  ]
}
```

The archive can contain one common root directory or files directly in
root Symbolic links, absolute paths, `..`, PHP executables,
Hidden files and unknown extensions are rejected before being written. SVG is cleared from
scripts and event handlers.

## Separation of responsibilities

| Problem | Where to configure |
| --- | --- |
| Contacts and design options | **Themes -> Settings** |
| Native page section order | **Categories and fields -> category template** |
| Links in menu and footer | **Navigation** |
| HTML shell, `head`, header and footer | **Templates** |
| Product card and catalog filter | **Catalog -> Cards / Filter Templates** |
| Cart, checkout and buyer's account | **Orders -> Templates** |
| CSS, JS, images, fonts | **Themes** |
| HTML of one document type | **Categories and fields** |
| Reused fragment | **Blocks** |
| Native CSS/JS installable extension | Inside the **Module** package |

Modular assets remain with the module owner. Do not move them to the topic if
they are required for the module to work. The theme can only override their external
view with your own CSS included later.

File Twig wrapper `page_shell` useful for batch demos ready
file themes and special integrations. For a regular content site
Preferably a native composition from a site template, categories, queries, blocks
and navigation.

A dotted Twig representation of a complex module is connected only through its
registered tag. More details: [Dot Twig components](twig-components.md).
