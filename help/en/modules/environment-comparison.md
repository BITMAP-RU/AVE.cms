# Comparing local and production systems

The module compares two AVE.cms installations before deployment or an update.

1. Install the module on both installations.
2. Download a JSON snapshot from the production control panel.
3. Open the module locally, upload that snapshot and run the comparison.
4. Review core files, modules, migrations, database schema, settings, themes and
   templates.

For core files, modules, and themes the report identifies the exact changed or
missing path. The snapshot stores only the path and SHA-256, never file content.

The snapshot contains no documents, personal data, passwords, tokens, keys or
database connection details. Non-secret setting values are represented by hashes,
so the report can detect a change without exposing the value.
