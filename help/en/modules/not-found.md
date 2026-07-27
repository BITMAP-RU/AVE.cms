# 404 errors

← [Back to the “Modules” section](README.md)

Module `notfound` records real public 404 responses: requested URL,
referral source, number of repetitions and time of last access. He helps
distinguish lost internal links from accidental crawling.

## Working with the log

1. Open **Modules → 404 Errors**.
2. Filter out unsolved lines and sort the work by number of repetitions.
3. For a valuable old URL, find an existing document and create a redirect.
4. Mark the unnecessary address as resolved or delete it.
5. Clear resolved entries periodically.

Creating a redirect adds a standard redirection rule and marks the event
decided. Don't redirect all unknown URLs to the main URL: it hides
errors, creates uninformative responses and interferes with search engines.

Rights:

- `view_notfound` — view the log;
- `manage_notfound` - redirects, statuses and cleaning.

To create a redirect, `manage_documents` is additionally required, since
the rule is written to the URL history of the selected document.

Only the final public 404 is included in the list, not every intermediate search
route. Uninstallation deletes the log; created regular redirects remain
part of the content and are not deleted along with the module.
