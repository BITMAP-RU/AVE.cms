# Manage field types

← [To the section “Document fields”](README.md)

## Installed, enabled and in use - different states

| State | What does |
| --- | --- |
| Installed | The class is present in `FieldRegistry`, existing data can be read. |
| Available for creation | The type is enabled in setting `rubrics.enabled_field_types`. |
| Used | `rubric_fields` has at least one field with this code. |

Disabling it in the control panel only changes the second state. This is a safe way
shorten the list for content managers without breaking the existing site.

## Removing a modular type

Uninstallation of a module is blocked if at least one category uses its type.
Correct order:

1. Find type fields in **Categories and fields → Field types**.
2. Decide the fate of each field: leave the module, delete the field along with the data
   or perform a separate migration to another compatible type.
3. Check public templates and API consumers.
4. Only after zero use uninstall the module.
5. You can physically delete a package only after uninstallation and only when
   `package.removable = true`.

A simple change of `rubric_field_type` in the database is not a migration: the value format,
settings, numeric index and patterns may vary.

## Type update

You can change it reversibly:

- signatures and hints;
- visual formatting;
- new settings with safe default;
- support for a new input format while maintaining the old reading.

Requires migration:

- renaming `code()`;
- scalar ↔ JSON;
- change JSON keys;
- change of storage units;
- changing the meaning of the numerical index;
- deleting a previously supported list value.

After updating the type, resaving all documents should not be mandatory
for normal reading. If required, the module must provide managed
web operation with batch processing and progress; production should not depend
from CLI.
