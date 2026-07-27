# Remote module directory

← [To the “Modules” section](README.md)

AVE.cms can receive a list of modules from the system owner's website. Server
directory remains a regular static HTTPS directory: it does not need PHP, database
data or a separate API.

## Connection for the site owner

In the clean installer, select **AVE.cms Official Channel**. Directory URL and
The public key is already included in the assembly, so copy them from the site or from
no instructions required. The connection does not install modules automatically:
it only shows available signed packages.

If the official source has been changed, open **Modules → Catalog → Source**
and click **Restore**. For another publisher, insert the one you received from them
JSON profile during installation or fill in the URL and key manually in this panel.
The public key is not a secret.

## Connection in the panel

1. Open **System → Modules → Management**.
2. Click **Source**.
3. Enable the remote directory.
4. Specify the full HTTPS URL of the file `index.json`.
5. Paste the publisher's public RSA key and save the settings.

After checking, the mark **Signature will appear in the “Modules Catalog” block
verified**. The update button re-gets the list and the install button
downloads the selected package and transfers it to the standard ZIP installer.

The public key is not a secret. The private key is not loaded into the panel and
The AVE.cms web server should not be accessed.

## What is being checked

Before executing the code, the system sequentially checks:

- HTTPS and public address of the source;
- RSA-SHA256 signature of the entire directory;
- compatibility of PHP and AVE.cms versions;
- size and SHA-256 of the downloaded ZIP;
- the same host for the directory and archive;
- official `AVE-MODULE.json` and SHA-256 of each file inside the package;
- secure ZIP structure and unpacking restrictions.

Installation requires **Manage Modules** and **Install Module Code** rights.
The event is recorded in the audit.

## Publishing your catalog

Commands are executed in the owner's working copy, not on the production site.
The private key is created once:

```bash
php tools/generate-module-repository-key.php
```

Collect the ZIP of the required modules using a regular builder, then create a directory:

```bash
php tools/build-module-package.php modules/comments
php tools/build-module-repository.php
```

The finished static folder is located in
`storage/releases/module-repository/`. Upload its contents to an HTTPS host:
first `files/*.zip`, after checking the files - `index.json` last,
for example in `/updates/modules/`. The URL for the panel will look like this:

```text
https://example.org/updates/modules/index.json
```

Nearby is automatically created
`storage/releases/module-repository-host.zip` with the same folder, if more convenient
download and unpack one archive via the hosting panel.

The public key is located in `public-key.txt` inside the finished folder. To Settings
The contents of the file are inserted, not the path to it. Private key
remains in `storage/secrets/module-repository-private.pem` and is not included in
publication. Keep it in a secure backup copy: you can’t live without it.
release the next update to the same trusted directory.

To transfer both sources to the consumer in one insert, create a profile:

```bash
php tools/build-distribution-profile.php \
  --name="Мой канал AVE.cms" \
  --core-url=https://example.org/updates/core/index.json \
  --module-url=https://example.org/updates/modules/index.json \
  --output=storage/releases/my-repository-profile.json
```

Pass the generated JSON, but never pass the PEM private keys.

For the next publication, rebuild the modified ZIP, re-run
`build-module-repository.php` and replace the static files on the host. Generator
selects the latest semantic version of each `code`. In the name of the published
ZIP is part of SHA-256, so different contents of the same version receive different
URL and does not collide with the old hosting/CDN cache. Don't rename the ZIP
manually and do not publish the new `index.json` before the files specified in it.

### Checking files on hosting

The collector places the page `check.php` next to `index.json`. After downloading
repository, open the folder address or directly:

```text
https://example.org/updates/modules/check.php
```

The page will first check the digital signature of the directory, then the browser will take turns
will download each ZIP with cache bypass and compare the actual size and SHA-256 with
signed values. This way, both an underloaded FTP file and
an outdated copy of the CDN. The red line needs to be reloaded; `index.json`
Replace with the latter and only after a completely green check. Private key
the page is not needed and is not uploaded to the hosting.
The new repository build contains only the ZIPs listed in its signed
`index.json`. Therefore, the archive for hosting does not include outdated versions of one
module version. If the old `index.json` is cached for a long time on the hosting or CDN,
its archives can be temporarily stored separately for the lifetime of the cache. New
`index.json` always load last.

## Updating an installed module

If a newer version is published in the directory, one operation replaces the folder
module completely and immediately performs its new migrations. Check old files against
server is not required: the latest signed version of the package takes precedence.
Old files are temporarily stored in staging until the new one is successfully verified
`module.php`. A migration ID that has already been applied will not run a second time.

AVE.cms patches are installed by a separate update subsystem. Modular
The installer does not have the right to replace kernel files.
