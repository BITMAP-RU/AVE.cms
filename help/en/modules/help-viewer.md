# Built-in help

Module `help` shows Markdown documentation from the root directory `help/`
in the AVE.cms control panel. The source materials remain regular files:
they can be edited in the repository, viewed without a database, and delivered
separately from the module code.

## Installation and access

The module is located in `modules/help` and is controlled through the “Modules” section. After
installation, it adds a separate “Help” item and the right `view_help`.
Uninstallation removes the right and the interface, but does not delete the directory `help/`.

The user sees the section only if he has the `view_help` right. Editing
there are no files from the panel: the directory intentionally works in read-only mode.

## Material structure

Each Markdown file becomes a separate material. `README.md` sets
directory page, the remaining files become child items:

```text
help/
├── README.md               # обзор справки
└── example/
    ├── README.md           # страница раздела
    ├── installation.md
    └── settings.md
```

The title is taken from the first `# H1`. If not present, the file name is used.
Folders and materials are sorted naturally, and the current document section
automatically expands in the left tree.

## Help for installed modules

The `help/` directory contains the complete documentation set, including guides
for optional modules. This is useful for development and for distributing the
documentation as a self-contained package, but it does not overload the control
panel: navigation and search show a module-specific guide only when that module
is installed.

A disabled but installed module remains available in Help. After uninstalling
the module, its page automatically disappears from navigation and search.
General guides covering module development, ZIP installation, repositories,
hooks, and interface extensions are always available.

## Links between materials

Relative Markdown links are written as usual:

```markdown
[Настройки](settings.md)
[Назад к разделу](README.md)
[Другой раздел](../core/auth.md)
```

When outputting, the module converts them to the addresses of the current directory of the control panel.
Therefore, renaming `adminx/` does not require changing the documentation. External
HTTP links open in a new tab.

## Supported markup

The renderer supports headings, paragraphs, links, images, highlighting,
line code, fenced code blocks, lists, quotes, delimiters and tables.
For code blocks, the language and copy button are shown.

Raw HTML is escaped and not executed. This protects the panel from accidental
inserting a script into documentation. For a complex interactive page you should
create a regular modular Twig template rather than putting HTML into Markdown.

## Search and cache

The search takes into account the title, subtitles and full text. Everything must match
query words; matches in titles receive more weight. Live results
appear only after entering two characters.

The index is stored in the standard AVE.cms file cache. On request, the module compares
paths, modification time and size of `.md` files. After changing the document index
it is rebuilt automatically; there is no need to manually clear the cache.

## Security restrictions

- only regular `.md` files inside `help/` are indexed;
- symlinks and hidden paths are skipped;
- the user selects a document only from a ready-made index;
- `../` and any path outside `help/` return 404;
- permission is checked for both the page and the JSON search.
