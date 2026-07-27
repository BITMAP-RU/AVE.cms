# Demo site

← [Back to the “Modules” section](README.md)

Package `demo_site` installs the demo theme, related content, and
working modules. Its purpose is to show the assembly of the AVE.cms page on a live
example, without mixing the demo with the kernel and a clean install.

The clean `core` archive is always the baseline. There is no separate engine
bundle with demo content: one `demo_site` ZIP embeds verified archives of every
required module and is installed through the regular Modules section.

After installation, the finished site is available at `/demo`. Home page
the project is not replaced. The link to the demo and the complete installation are shown in
module section.

## What is installed

The package creates a website template, two categories, fields, documents, a two-level menu,
system unit and media files. Additionally, it installs and enables the following modules:

- search, questions and answers, galleries, banners and polls;
- ratings, comments, interactions and antispam;
- related materials, RSS feeds and contact forms.

If a required module is missing, it is installed from the package. An equal or
newer installed version is reused. An older version is updated through its
normal migrations. Embedded ZIP files are removed after installation.

Poll, gallery, FAQ, banner, rating, comments and similar materials are displayed
working module tags rather than static HTML layouts. Search indexes and
similar materials are built immediately after the creation of documents.

## Updates

Updating the module does not create another copy of the demo data. It
resynchronizes the package-owned site template, rubric and request
presentation, system blocks, navigation, and demo media files.

Document field values are not overwritten. Text and data changed through the
document editor remain intact, while damaged or outdated presentation is
restored to the current package version.

## Soft delete

Each entity created by the package is recorded in its own ledger: template,
category, category field and document. Removal is based only on this list and not
tries to guess demo data by name or alias.

In the module section you can separately check and delete:

- topic;
- demo content;
- the whole package.

Before confirmation, the server rebuilds the operation composition and issues a short-lived
preflight token. The token is associated with a user, transaction type and exact hash
ledger. If the composition has changed between viewing and confirmation, deletion will be
rejected.

Entities are deleted in dependency order: module data, documents, fields,
categories, media and templates.
If the demo template is referenced by external content, the links are translated to
base template rather than remain damaged.

Connected modules are not uninstalled when deleting the demo: after launching the site in
they may already contain real comments, votes and appeals. Are deleted
Only collections, profiles and records created by the demo package.

The rights `view_demo_site` and `manage_demo_site` share viewing of the composition and
deletion. The current theme and content of the package may change between builds; ledger
and preflight are a stable boundary for a safe life cycle.

## Ready-made sites

The same scheme is not only suitable for demos. In one ZIP you can put a ready-made
news site, blog, corporate site or industry directory: package announces
dependent modules, creates a content structure via `ContentPackageWriter`,
records its entities in the ledger and shows the installation result to the person.

To connect modules, use `PackageModuleProvisioner`. Don't call
SQL migration of another module directly: provisioner retains the standard lifecycle,
order of dependencies and state of the module registry.
