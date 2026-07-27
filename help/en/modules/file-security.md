# Module "File Security"

← [Back to the “Modules” section](README.md)

The `file_security` module monitors the integrity of source codes using SHA-256 and performs
heuristic checking and protects user downloads. Definitely first
create a reference from a known trusted version of the project.

Rights are divided by risk level:

- `view_file_security` - reports, findings, widget and notifications;
- `run_file_security` — start and stop the scan;
- `manage_file_security` - standard, exceptions, settings and quarantine.

Quick, system and full scans are available. Auto scan in progress
short web portions and can be disabled separately. Completing the check and
new findings are available to modules via hooks
`file_security.scan.completed` and `file_security.finding.detected`.

JSON content packages are a separate trusted format and can store code
rubrics and blocks as text. For source `content_packages` single marker
built-in PHP does not block loading, but dangerous command signatures, dynamic
include and obfuscations continue to be checked. This exception does not apply to
media, form attachments, module archives and imported tables.

First launch, profiles, actions with findings, quarantine and data deletion in detail
are described in the [File Security Guide](../security/README.md).
