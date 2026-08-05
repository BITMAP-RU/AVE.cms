# Modular system

An extension can only be an administrative one or a composite package with public
and administrative parts. It declaratively describes rights, routes,
migrations, settings, hooks, field types and interface contributions. Managed module
can be installed, updated, turned off, reinstalled and uninstalled through the panel
AVE.cms controls.

In this section, **AVE.cms** means the engine itself, and “control panel” means its
administrative interface. The name `adminx` is used as the directory name by
default, as well as in the namespace and technical API panels. Physical catalog
can be renamed.

Administrative module only:

```text
<admin-directory>/modules/Notes/
├── module.php
├── Controller.php
├── Model.php
├── migrations/
├── view/
└── assets/
```

Composite package that also changes the public site:

```text
modules/reviews/
├── app/
│   ├── module.php
│   ├── services.php
│   └── Fields/
└── admin/
    ├── module.php
    ├── Controller.php
    ├── view/
    └── assets/
```

## How the system finds modules

`ModuleManager` loads built-in panel modules from the catalog
`<admin-directory>/modules`, and
`PackageModuleRuntime` detects multipart packets in `modules`. Classes
connects the standard namespace-loader, then the descriptor registers the available ones
contracts in general registers.

After the first detection, serializable descriptors are stored in a protected
`storage/runtime/module-registry.json`. The registry checks the root directory and
automatically rebuilds the administrative section after renaming the panel.
Manifest with computed runtime content declares
`'registry' => array('dynamic' => true)` and downloads directly.

A managed module without a lifecycle record is only discovered. Until explicit
its migration settings, routes and interface are not active.

In the **Modules → Management** section, the area of each extension is marked with color:

- `Admin panel` - the code is located only in the panel directory and is not connected in the public;
- `Public` - the package provides only the runtime of the public site;
- `Public + admin panel` - a single package contains `app/` and `admin/`.

Therefore, a small number of folders in the root `modules/` is normal: pure
administrative extensions are physically located in
`<admin-directory>/modules/`, and composite packages are in the root `modules/`.

## Section map| Page | What about |
| --- | --- |
| [module.php - descriptor](module-php.md) | All main manifest keys with examples. |
| [Module files](files.md) | `Menu`, `Permissions`, controllers, models, routes, Twig and assets. |
| [Operations and states](operations.md) | Installing, updating, enabling, reinstalling, uninstalling and deleting files. |
| [ZIP packages](packages.md) | Archive structure, restrictions and secure installation via the control panel. |
| [Remote directory](repository.md) | Connecting a signed source, publishing and installing modules via HTTPS. |
| [Migrations](migrations.md) | Table placeholders, ledger, idempotency and schema deletion. |
| [UI Contributions](contributions.md) | Overview of menus, header actions, dashboard widgets and notifications. |
| [Localization](localization.md) | Module XML dictionaries, Twig, JavaScript and translation of menu items. |
| [Dashboard Widgets](dashboard-widgets.md) | Descriptor, provider, Twig, grid, order and visibility. |
| [Actions in the header](header-actions.md) | Button, dropdown, modal window, rights and Ajax. |
| [Notifications](notifications.md) | Common bell elements, counters and provider performance. |
| [Built-in Help](help-viewer.md) | How the module reads the catalog `help/`, builds navigation and full-text search. |
| [Site readiness](site-readiness.md) | Launch profiles, reports, manual checklist and extension of checks by the module. |
| [Interactions](interactions.md) | General runtime of ratings, reactions and votes, channels, APIs and hooks. |
| [Antispam](antispam.md) | Form security profiles, challenge, CAPTCHA and connecting your own checks. |
| [Ratings](ratings.md) | Object rating widget, template tags and vote API. |
| [Comments](comments.md) | Discussions, pre-moderation, three templates, API and anti-spam. |
| [Polls](polls.md) | Single and multiple voting, archive, tags and API. |
| [Galleries](galleries.md) | Media collections, grid, slider, thumbnails and template tags. |
| [Site search](search.md) | Index, scopes, query output, JSON/HTML API, live search and hooks. |
| [Product quiz](quiz.md) | Step-by-step catalog selection and its public page template. |
| [Public widgets](public-widgets.md) | Exchange rates, subscription, and active-theme Twig overrides. |
| [Banners](banners.md) | Advertising slots, rotation strategies, scheduling, templates and statistics. |
| [Q&A](faq.md) | Collections of FAQs, rich-text answers, templates, JSON API and Schema.org. |
| [Related content](related.md) | Relevance, circular inference, manual connections, templates, queries and APIs. |
| [Contact Forms](contacts.md) | Forms, fields, public insertion, request history and responses. |
| [Cart, delivery and payment](commerce.md) | Public cart pages, Twig templates, delivery services and payment gateways. |
| [RSS Feeds](rss.md) | Public addresses of channels, categories, fields and issuance restrictions. |
| [Login via VK ID and Yandex](oauth-providers.md) | Callback URL, secrets, registration and account linking. || [Login via SMS via SMSC](smsc-auth.md) | API key, one-time codes, restrictions and phone accounts. |
| [Personal Tools](personal-tools.md) | Todo, Kanban, notes and reminders. |
| [404 errors](not-found.md) | Logging non-existent URLs and creating redirects. |
| [SEO audit](seo-audit.md) | Rules for document verification and secure filling of metadata. |
| [Performance](benchmark.md) | Synthetic check of the database, file system and CPU. |
| [File Security](file-security.md) | Standard, scan profiles, quarantine, rights and notifications. |
| [Demo site](demo-site.md) | Reversible addition of theme and demo content. |
| [Import documents](document-import.md) | CSV, Excel and XML profiles, field mapping and document re-updating. |
| [Migration from old AVE.cms](legacy-migration.md) | Preflight, batch transfer with ID saving, reconciliation and import of uploads. |
| [Content Packs](content-packages.md) | JSON transfer of categories, fields, queries, documents and blocks between sites. |
| [Scheduler](scheduler.md) | Registry of background tasks, cron schedule, HTTP tick and launch log. |
| [Health Monitoring](system-health.md) | Disk, MySQL, background tasks, external services and snapshot history. |
| [Reviews](reviews.md) | Reviews and ratings of properties, pre-moderation and company response. |
| [Pop-ups](popups.md) | Campaigns, A/B options, display scripts and leads. |
| [Exchange with 1C](commerceml.md) | CommerceML profiles, field mapping and import log. |
| [Traffic sources](traffic-analytics.md) | UTM campaigns, external transitions, landing pages and CSV. |
| [Search terms](search-analytics.md) | Visitor demand, zero output and work notes. |
| [A/B tests](experiments.md) | Variants of blocks and elements, stable distribution and conversions. |
| [Reliable events](reliable-events.md) | Signed webhook outbox, retries, and delivery history. |
| [Cache warmup](cache-warmup.md) | Controlled background warming of published pages. |
| [Crossposting](crossposting.md) | Telegram, VK, Zen, and custom gateways with templates, scheduling, and deduplication. |
| [System comparison](environment-comparison.md) | Core, modules, migrations, database schema, settings, themes, and templates without exporting secrets. |
| [Step by step: your module](tutorial.md) | Mini-module from scratch to working screen. |

## Installable AVE.cms modules

The table below covers standard non-commodity modules. Product packages, price import,
widgets and “Document Operations” are deliberately not included in this list.| Code | Region | Basic rights | Guide |
| --- | --- | --- | --- |
| `benchmark` | Panel | `view_benchmark`, `manage_benchmark` | [Performance](benchmark.md) |
| `interactions` | Public + panel | `view_interactions`, `manage_interactions` | [Interactions](interactions.md) |
| `reviews` | Public + panel | `view_reviews`, `manage_reviews` | [Reviews](reviews.md) |
| `antispam` | Public + panel | `view_antispam`, `manage_antispam` | [Antispam](antispam.md) |
| `banners` | Public + panel | `view_banners`, `manage_banners` | [Banners](banners.md) |
| `comments` | Public + panel | `view_comments`, `manage_comments` | [Comments](comments.md) |
| `contacts` | Public + panel | `view_contacts`, `manage_contacts`, `reply_contacts` | [Contact Forms](contacts.md) |
| `commerce` | Public + panel | `view_orders`, `manage_orders`, `refund_orders` | [Cart, delivery and payment](commerce.md) |
| `demo_site` | Public + panel | `view_demo_site`, `manage_demo_site` | [Demo site](demo-site.md) |
| `document_import` | Panel | `view_document_import`, `run_document_import`, `manage_document_import` | [Import documents](document-import.md) |
| `legacy_migration` | Panel | `view_legacy_migration`, `run_legacy_migration`, `manage_legacy_migration` | [AVE.cms migration](legacy-migration.md) |
| `content_packages` | Panel | `view_content_packages`, `export_content_packages`, `import_content_packages` | [Content Packs](content-packages.md) |
| `faq` | Public + panel | `view_faq`, `manage_faq` | [Q&A](faq.md) |
| `galleries` | Public + panel | `view_galleries`, `manage_galleries` | [Galleries](galleries.md) |
| `polls` | Public + panel | `view_polls`, `manage_polls` | [Polls](polls.md) |
| `ratings` | Public + panel | `view_ratings`, `manage_ratings` | [Ratings](ratings.md) |
| `related` | Public + panel | `view_related`, `manage_related` | [Related content](related.md) |
| `file_security` | Public + panel | `view_file_security`, `run_file_security`, `manage_file_security` | [File Security](file-security.md) |
| `help` | Panel | `view_help` | [Built-in Help](help-viewer.md) |
| `kanban` | Panel | `view_kanban`, `manage_kanban` | [Personal Tools](personal-tools.md#канбан) |
| `notes` | Panel | `view_notes`, `manage_notes` | [Personal Tools](personal-tools.md#заметки) |
| `notfound` | Panel | `view_notfound`, `manage_notfound` | [404 errors](not-found.md) |
| `reminders` | Panel | `view_reminders`, `manage_reminders` | [Personal Tools](personal-tools.md#напоминания) |
| `rss` | Public + panel | `view_rss`, `manage_rss` | [RSS Feeds](rss.md) |
| `search` | Public + panel | `view_search`, `manage_search` | [Site search](search.md) || `seoaudit` | Panel | `view_seoaudit`, `manage_seoaudit` | [SEO audit](seo-audit.md) |
| `site_readiness` | Panel | `view_site_readiness`, `run_site_readiness`, `manage_site_readiness` | [Site readiness](site-readiness.md) |
| `scheduler` | Public + panel | `view_scheduler`, `run_scheduler`, `manage_scheduler` | [Scheduler](scheduler.md) |
| `system_health` | Panel | `view_system_health`, `run_system_health`, `manage_system_health` | [Health Monitoring](system-health.md) |
| `popups` | Public + panel | `view_popups`, `manage_popups`, `manage_popup_code` | [Pop-ups](popups.md) |
| `commerceml` | Public + panel | `view_commerceml`, `manage_commerceml` | [Exchange with 1C](commerceml.md) |
| `traffic_analytics` | Public + panel | `view_traffic_analytics`, `manage_traffic_analytics` | [Traffic sources](traffic-analytics.md) |
| `search_analytics` | Public + panel | `view_search_analytics`, `manage_search_analytics` | [Search queries](search-analytics.md) |
| `experiments` | Public + panel | `view_experiments`, `manage_experiments`, `manage_experiment_code` | [A/B tests](experiments.md) |
| `reliable_events` | Public + panel | `view_reliable_events`, `manage_reliable_events` | [Reliable events](reliable-events.md) |
| `cache_warmup` | Public + panel | `view_cache_warmup`, `manage_cache_warmup` | [Cache warmup](cache-warmup.md) |
| `crossposting` | Public + panel | `view_crossposting`, `manage_crossposting` | [Crossposting](crossposting.md) |
| `environment_compare` | Panel | `view_environment_compare`, `run_environment_compare` | [System comparison](environment-comparison.md) |
| `todo` | Panel | `view_todos`, `manage_todos` | [Personal Tools](personal-tools.md#todo) |
| `vk_oauth` | Public + panel | `view_vk_oauth`, `manage_vk_oauth` | [Login via VK ID](oauth-providers.md#vk-id) |
| `yandex_oauth` | Public + panel | `view_yandex_oauth`, `manage_yandex_oauth` | [Login via Yandex](oauth-providers.md#яндекс) |
| `smsc_auth` | Public + panel | `view_smsc_auth`, `manage_smsc_auth` | [Login via SMS via SMSC](smsc-auth.md) |

## Key principles

- Control panel only - `<admin-directory>/modules/<Name>`, public part
  and panel - one package in `modules/<code>`.
- Rights, menus, routes, migrations, hooks and settings are declared declaratively.
- The Managed module requires explicit installation; the presence of a folder does not mean activation.
- Disabling saves data and shared contracts, but removes runtime contributions.
- Twig-namespace of the administrative module matches its `code`.
- The module should not require a CLI for installation or daily production work.
- Browser confirmations are performed via a technical API
  `Adminx.Confirm`, Ajax responses - through the general control panel contract.

Before installing a third-party package, check its source code and make a backup:
The ZIP module is executable PHP code and can contain database migrations.
