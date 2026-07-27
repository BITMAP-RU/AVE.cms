# Import documents

← [Back to the “Modules” section](README.md)

Module `document_import` loads CSV, XLSX, XLS and XML into documents of the selected
rubrics It replaces the old universal import: data is saved via
standard `DocumentMutationService`, so alias fields are checked,
persistence hooks, auditing and flushing of associated cache.

## Quick start

1. Open **Modules → Import Documents** and create a profile.
2. Select a category and file format. For XML, specify a repeatable element, for
   Excel, if necessary, specify a sheet.
3. Click launch profile and upload a new source file.
4. Check the found columns and first rows.
5. Match the source columns with the document details and rubric fields.
6. To update existing documents, check one or more checkboxes
   search.
7. Start the import and wait until all portions are completed.

The file is not stored in the profile. The profile remembers the format, mapping, keys and
execution mode, and each time it starts, it accepts a new source.

## Source formats

| Format | Setting | Note |
| --- | --- | --- |
| CSV | delimiter or autodetection | The first line should contain the column names. |
| XLSX | name or sheet number | An empty value selects the first sheet. |
| XLS | name or sheet number | Read by the built-in PHPExcel library. |
| XML | repeatable element name | Child elements and attributes become columns. |

The maximum source size is 50 MB. The uploaded file passes
shared `UploadPolicy` and stored outside the public directory until completion or
deleting the launch.

## Mapping

Each rule associates one source column with one target. One goal is not possible
select twice. System details of the document and all relevant fields are available
selected category.

For a rule you can set:

- conversion: no change, trim spaces, integer, decimal,
  date, boolean, lower or upper case, JSON;
- default value for an empty column;
- mandatory meaning;
- a template in which `{Column name}` substitutes the data of the current row;
- participation in the search for an existing document;
- exact match or occurrence search.

To create a document, be sure to match the title. As keys
title, alias and category fields are acceptable. Multiple keys are combined
condition **I**: the document must match each of them. For stable
re-import, it is better to use an external ID, article or other unique
props, not the title.

## Launch mode| Option | Behavior |
| --- | --- |
| Create new | Creates a document when no key match is found. |
| Update found | Changes only the matched details of the found document. |
| Disable missing | After a successful pass, it turns off active documents in the category that were not in the source. |
| Stop on error | Aborts startup on the first erroneous line. |

Disabling absent people is allowed only when the key is configured. It begins
after a complete pass without any line errors, so a corrupted or incomplete file
should not massively turn off documents.

Import is performed in batches. The position is stored in the DB, so interrupted by the browser
the launch can be continued from the **Starts** tab. History shows what was created,
updated, missing and erroneous lines.

## Hooks

| Hook | Destination |
| --- | --- |
| `document_import.source.prepared` | The source has been disassembled, the profile and launch are known. |
| `document_import.row.normalized` | Allows you to change the values ​​of one row before matching. |
| `document_import.document.payload` | Changes the payload before saving the document normally. |
| `document_import.document.saved` | The document was successfully created or updated. |
| `document_import.run.started` | The launch is configured and has started processing. |
| `document_import.run.completed` | All lines and finalization have been processed. |
| `document_import.run.cancelled` | The user stopped the launch. |

Common document hooks `content.document.saving` and
`content.document.saved` are also executed because the module does not bypass
native save loop.

## Delete

Uninstallation removes profiles, history, errors, temporary sources and everything
module tables. Created or updated documents remain in their categories:
they are site content, not import service data.
