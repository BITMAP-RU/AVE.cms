# Bulk editor for documents and products

Open **Documents → Bulk editor** to update documents through the same native
mutation service used by document editing, JSON API, imports, and product quick
editing. A product remains a document in a commerce rubric, so product indexes
are refreshed by normal document hooks.

A product can have a different **Document title** and storefront title. The
document title belongs to the primary AVE.cms record, while the public title is
read from the field assigned in the product catalog settings. After selecting
a rubric, the bulk editor marks these fields as **storefront: product title**,
**storefront: current price**, and so on. Select the marked field when changing
public data. Saving it rebuilds the product projection and invalidates the
related public cache.

The editor can fill empty values, set, clear, find and replace, move documents
to another rubric, publish or unpublish, and recalculate computed fields,
snapshots, caches, and module indexes.

Always run **Preview changes** first. The server freezes the exact ID list for
two hours and displays up to 20 before/after examples. A run is limited to 5000
documents and advances in chunks of 20. It can be stopped, and an error in one
document does not discard successful documents.

Every changed document receives a revision and passes validation, rubric code,
document hooks, snapshot generation, and index refresh. During a rubric move,
only fields with the same non-empty alias and compatible type are preserved.
