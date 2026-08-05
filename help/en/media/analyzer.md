# Media analyzer

Open **Media → Analyzer** to inspect `/uploads` and match files against explicit
references in documents, fields, rubrics, templates, blocks, navigation,
requests, settings, theme sources, and installed modules.

The scan is read-only. It never deletes, renames, or recompresses a file.

The run also rebuilds the compact usage map shown on file pages and the
file-name index used by admin global search. Deletion does not trust the age of
that map: it checks the selected path against current references. Run the full
analyzer after a bulk FTP upload or a backup restore; ordinary admin uploads and
renames update search immediately.

| Group | Meaning |
| --- | --- |
| Unused | No explicit reference was found. This is a review candidate, not an automatic deletion decision. |
| Duplicates | Multiple files have the same size and SHA-256 content hash. |
| Missing originals | Stored data points to a missing file, or a generated preview has no source image. |
| Missing previews | No generated thumbnail was found. This can be normal with lazy generation. |
| Large | The file is at least 5 MB or an image side is at least 4000 px. |
| Invalid paths | A reference contains unsafe segments or points outside `/uploads`. |
| All files | A complete usage map with sizes and image dimensions. |

The usage map shows the entity type, ID, source column, and a link to the
relevant panel section. Dynamically assembled URLs cannot always be discovered,
so always review an “unused” file before deleting it.

Run the scan from **Media → Analyzer → Run scan**. The latest report is stored
in `storage/reports/media-audit.json`.
