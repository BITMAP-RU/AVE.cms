# Install module from ZIP

← [To the “Modules” section](README.md)

The control panel accepts an administrative module or a composite
public/admin package AVE.cms.
After checking, the archive is unpacked into the standard directory and installed immediately.

The module archives are distributed by the system owner. Requires two to download
individual rights: managing modules and installing executable code.

## Archive building

Don't create the ZIP manually. The regular collector adds the required manifest and
checks the archive after packaging:

```bash
php tools/build-module-package.php modules/reviews
php tools/build-module-package.php adminx/modules/Notes
```

By default, the result is stored in `storage/releases/modules/`. The way is possible
override with the parameter `--output=...`.

## Valid structure

Control panel only:

```text
notes.zip
└── Notes/
    ├── module.php
    └── ...
```

Composite package:

```text
reviews.zip
└── reviews/
	├── AVE-MODULE.json
    ├── app/
    │   ├── module.php
    │   └── ...
    └── admin/
        ├── module.php
        └── ...
```

A self-contained site package may also embed verified dependency archives:

```text
site-package.zip
└── site_package/
    ├── admin/
    ├── app/
    └── packages/
        └── modules/
            ├── search-1.2.4.zip
            └── contacts-1.0.4.zip
```

Do not add these archives manually. Declare module codes in
`package.embedded_dependencies` in the admin `module.php`. The standard builder
will package the dependencies, calculate SHA-256 checksums, and emit an
`ave-module-package-v2` manifest.

An archive without an external folder is allowed. There must be exactly one at the root
`module.php` or one `admin/module.php`, as well as `AVE-MODULE.json`.

For a composite package, the folder name must match `code`. The code starts with
lowercase Latin letter, contains only lowercase letters, numbers and `_`, its
maximum length is 64 characters.

## Required properties

```php
'lifecycle' => array('managed' => true),
'admin_extension' => array(/* метаданные раздела */),
```

`lifecycle.adopt_existing` is prohibited: it is intended for controlled use only
moving existing functionality. The new archive code must not match
code of an already detected module.

## Security restrictions

| Limit | Meaning |
| --- | --- |
| Format | ZIP only |
| Archive size | Up to 25 MB |
| Files and directories | Up to 1500 |
| One unpacked file | Up to 25 MB |
| Total unpacked size | Up to 100 MB |

The installer does not allow absolute paths, `..`, control characters, duplicates,
symbolic links and suspicious compression ratio. Required
PHP extension `zip`; catalogs `<admin-directory>/modules` and `modules` must
be writable by the web process. In the build command above, `adminx` stands for
project source directory; the installed system determines the actual directory
panels automatically.

Before executing `module.php`, the installer checks the format, owner, code, version,
package type, full list of files and SHA-256. Extra, missing or changed
the file stops the installation. Checksum protects integrity, but archive still
must come through the owner's trusted channel.

## Installation order

1. Open **System → Modules**.
2. Select the installation from the archive and download the ZIP.
3. After success, check the version and dependencies.
4. Assign rights to roles and fill in the module settings.
5. Check administrative and public routes.

For a self-contained package dependencies are processed before the root
lifecycle. Missing dependencies are installed, older versions are updated, and
equal or newer versions are reused. Removing the root package does not uninstall
shared modules because they may already contain user data.

If there is an error before the manifest check, the temporary and target folders are deleted. If
manifest has already been checked and some of the migrations have been completed, the package remains in place:
eliminate the cause and repeat normal installation.

ZIP is executable PHP code. Install a package only from a trusted one
source and make a backup before migrating existing data.

To publish multiple official ZIPs over HTTPS use
[remote signed directory](repository.md).
