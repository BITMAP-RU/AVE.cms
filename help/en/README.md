# AVE.cms - documentation

Reference book on engine components. Sections are arranged in folders - one at a time
component (or group) for description.

The name of the system is **AVE.cms**. In the documentation the words "control panel" and
“admin” refers to the administrative interface of AVE.cms. `adminx/` - his name
default technical directory and part of the internal API names, not the title
separate product. The directory can be renamed during installation or later.

## Sections

| Section | Description |
| --- | --- |
| [Installation](installation/README.md) | Clean installation of AVE.cms, requirements, first login and restart protection. |
| [System Management](administration/README.md) | Map of the core sections of the panel: dashboard, users, settings, database, events and operation. |
| [How the site is assembled](content/README.md) | Templates, categories, fields, documents, menus, queries, blocks and a universal catalog: connections and order of public rendering. |
| [Kernel and Request Lifecycle](core/README.md) | Initialization (`App::init`), routing, controllers, authorization, sessions/CSRF, rights. |
| [Modular system](modules/README.md) | Control Panel Modules and AVE.cms Composite Packages: manifest, install, update, uninstall, ZIP, migrations and UI contributions. |
| [Document fields](fields/README.md) | Field architecture, settings, storage, templates and development of your own type. |
| [Media and Thumbnails](media/README.md) | File browser, preview generation, size modes, WebP and derivative file cleaning. |
| [Hooks and Events](hooks/README.md) | Subscribing to kernel events, contexts, hook directory, data modification and debugging. |
| [Public Debug](debug/README.md) | Secure Debug Toolbar on the site, its tabs, access and own calls `Debug::panel()`. |
| [HTTP API](api/README.md) | Tokens, JSON API documents, request and response formats, errors and security. |
| [Working with the database](database/README.md) | Facade `DB`, query placeholders, reading results, CRUD, transactions, query cache, table name resolvers. |
| [File Security](security/README.md) | SHA-256 benchmark, heuristic analysis, download protection, quarantine and automatic checks. |
| [Framework helpers](helpers/README.md) | 26 static helpers: strings, arrays, HTTP request/response, validation, files, security, date/date, etc. |

## Agreements

- The code is compatible with **PHP 7.3** (without arrow functions, `??=`, property typing, etc.).
- Kernel classes and installed modules are connected using a standard bootloader; manual `require` are not needed for classes.
- Almost all helpers are **static** (`Helper::method()`), they do not store state.
- Build paths to application files relative to `BASEPATH`, without being tied to
  the location of the project on a specific server.The documentation describes the supported engine contracts. Non-public details
implementations may change between versions.
