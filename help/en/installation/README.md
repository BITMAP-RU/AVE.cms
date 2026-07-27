# Installing AVE.cms

AVE.cms is installed via the secure page `/setup/`. The installer creates
a minimal working system, and additional features are added after
first entry.

## Requirements

- PHP 7.3 or later with `mysqli`, `mbstring`, `json` and `openssl`;
- MariaDB/MySQL with InnoDB and `utf8mb4`;
- empty database or free table prefix;
- the right to write to `configs/`, `storage/`, `tmp/` and `uploads/`;
- GD with WebP is recommended for working with images, but does not block installation.

## Installation order

1. Unpack the regular core archive into the root directory of the site, keeping hidden
   files including `.htaccess`. For compact delivery, simply unpack
   external installer archive: at first there will be only `index.php` in the root,
   `.htaccess` and `setup/`, and the wizard will check and deploy the nested runtime.
2. Create an empty database and a database user with rights to create and modify
   tables.
3. Open the root address of the site. If there is no AVE.cms configuration
   will redirect the browser to `/setup/`.
4. Check the environment diagnostic results.
5. Specify the server, port, database name, user, password and table prefix.
6. To re-install on a test bench, you can enable **Clear tables
   prefix before installation**. You will need to manually enter the exact prefix and
   Confirm the permanent deletion of the corresponding tables and views.
7. Set the site name, name, login, email and administrator password. Password
   must contain at least 8 characters. Change the folder name if necessary
   control panels; the default is `adminx`.
8. Click **Install AVE.cms**, then open the control panel.

With a compact installation, a separate progress is displayed before the main form
unpacking. Each file is checked against `PACKAGE-MANIFEST.json`, and the attached
`setup/runtime.zip` and its checksum are deleted only after a complete check.
If unpacking is interrupted, open `/setup/` again: the process will continue with
saved step.

More information about choosing an address and renaming after installation: [Panel folder
control](admin-directory.md).

## What is being created

- core tables AVE.cms in InnoDB;
- one system administrator;
- system settings, constants and basic rights;
- main HTML template with `[tag:maincontent]`;
- one category, title fields and richtext, published main page with
  a message about successful installation and a 404 service page.

Public users are not created. Registration and password recovery
turned off. Products, orders, payment methods, forms, RSS and other modules are not
are included in the core archive: their ZIP packages can be downloaded and installed separately in
section **System → Modules**.An extended bundle assembly may already contain verified Help and
"File Security". Availability of files does not include the module: after installation
AVE.cms it is displayed in the **System → Modules** section with the status “Available” and
is established by a separate conscious action of the administrator.

## Restart protection

After successful installation, `storage/installed.lock` is created. The installer also
checks for the presence of system users in the selected prefix. Therefore simple
opening `/setup/` cannot overwrite the working system.

For an informed clean reinstall, use a new base or free prefix,
remove `configs/db.config.php` and `storage/installed.lock`, then open again
`/setup/`. If you need the same prefix, enable its clear mode in the form
installer It only deletes tables and views of the form `<prefix>_*`;
objects with other prefixes remain untouched. Do not use this mode on
production site without a verified backup.

## Protect the download directory

As shipped, `uploads/.htaccess` prevents Apache from executing and directly serving
PHP-like files. Nginx doesn't read `.htaccess`, so the equivalent rule is
you need to add to the server configuration before the general PHP handler:

```nginx
location ~* ^/uploads/.*\.(php[0-9]*|phtml|phar)(\..*)?$ {
    return 404;
}
```

After changing the configuration, check that access to any
`/uploads/example.php` returns `403` or `404` and the images continue
served as static files.

The root `.htaccess` also closes the sources of module packages. For Nginx
you need an equivalent: allow only statics from three asset-roots, and that’s all
return the rest under `/modules/` as 404:

```nginx
location /modules/ {
    return 404;
}

location ~* ^/modules/[A-Za-z0-9_-]+/((app|admin)/)?assets/.+\.(css|js|mjs|svg|png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|eot|mp4|webm)$ {
    try_files $uri =404;
}
```

The second regex-location takes precedence over the regular prefix-location. Don't add `^~`
to `location /modules/`, otherwise it will also close allowed assets.

## After the first login

1. Check the site's home page and control document.
2. Specify the basic settings and website address.
3. Install only the modules needed by the project.
4. Set up roles and give employees the minimum necessary rights.
5. Delete or replace the control document after creating the actual content.
