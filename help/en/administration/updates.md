# AVE.cms updates

The **System → Updates** section installs signed kernel patches without
copying the complete assembly over the working site.

## Source connection

For a clean installation, just select **Official Channel AVE.cms**. Address and
the public key is already in the assembly; there is no need to search and copy them.
The system only checks for patches and never installs them
automatically.

If the source has been disabled or changed, open the **Source** tab and click
**Restore official**. The current URL and key will be replaced with trusted ones
values from the installed assembly.

For a third-party source, its publisher must provide a single JSON profile
or separately HTTPS URL `index.json` and public RSA key. The public key is not
is a password: it allows you to verify the directory signature. The private key is always
remains with the publisher.

1. Open the **Source** tab.
2. Enable checking of the remote directory.
3. Specify the HTTPS URL of the file `index.json`.
4. Paste the RSA public key of the selected publisher.
5. Save the settings and click **Check**.

The public key is not a secret. The private key is never loaded onto
website. The kernel update key is separate from the module directory key.

## Installation

Select the cumulative patch and click the prepare button. Before confirming files
sites do not change. One patch installs all fixes from the minimum
the specified build before the current release; no intermediate updates are needed.
AVE.cms will check the signature, build range, server requirements and write permissions.

If the wrong patch is selected, click **Cancel** before running. Downloaded ZIP and
temporary files will be deleted; This action does not affect the site or database.

After launch, a backup copy is performed, the public site is temporarily closed,
file replacement, migration and proofreading. The stages are short AJAX-
requests; You can open the tab again and continue the unfinished task.

If the patch contains migrations, a copy of the database is created in chunks. On large sites this is
may take a noticeable amount of time, but aborting the request does not start the process over again.

## What files are replaced

The kernel files included in the patch are replaced with the latest published version without
reconciliation with the old checksum on the server. Their current state is first saved
to the rollback log. The signature and checksum verify the integrity of the new archive, and the number
build determines whether the update is applicable.

Database settings, public templates, media, runtime data and core modules are not patched
are overwritten. New schema migrations are performed within the same job; after
updates do not need to run kernel migrations separately.

## RollbackThe **Rollback** button returns the files and, if migrations have already started, the database.
During the update, the browser also saves an emergency recovery link.
It is used only when the panel does not open after replacing files,
and stops working after normal completion.

Do not delete `storage/updates/` or copy another assembly on top of the site until
the job was not completed or was not rolled back.
