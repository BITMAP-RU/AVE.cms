# Control panel folder

← [To the “Installation” section](README.md)

For a clean installation, you can choose the name of the control panel directory. The field is already
filled with the value `adminx`; you can leave it unchanged or set your own,
for example `control` or `site_admin`.

The name must begin with a Latin letter, may contain Latin letters,
numbers, `-` and `_` and be no longer than 48 characters. System directory names
including `system`, `modules`, `setup`, `storage` and `uploads` cannot be used.

The installer performs the entire operation:

1. Checks that the selected directory is free and the site root is writable.
2. Renames the source directory `adminx`.
3. Writes the name to `configs/public.config.php` as `admin_directory`.
4. Returns the correct login link to the browser.

If the final stage of installation fails, the directory and configuration
return to their original state.

## Renaming after installation

Before changing, please back up your files and enable maintenance mode.
Then:

1. Rename the physical directory of the panel, for example `adminx` to `control`.
2. Set `configs/public.config.php` to the same name:

```php
'admin_directory' => 'control',
```

3. Open `/control/login` and check the input, interface resources, installation
   modules and a quick editing link on a public site.

The module registry itself will detect a change in the root directory and rebuild its
section. There is no need to remove it manually.

## Web server

The panel supplied `.htaccess` does not contain the fixed `RewriteBase`, therefore
on Apache it continues to work after renaming. With your own configuration
web server forward non-existent URLs within the selected directory to its
`index.php`, allow direct transfer of assets and prohibit the execution of others
Panel PHP files via HTTP.

A hidden name does not replace authorization and rights. AVE.cms additionally sends for
panel pages `X-Robots-Tag: noindex, nofollow, noarchive`.
