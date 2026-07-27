# Search queries

The `search_analytics` module complements the standard `search` module with demand analytics.
It shows what visitors were searching for, which phrases returned empty results, and which
topics require new content or expansion of the range.

## Data collection

After generating the output, `search` calls hook `search.results.resolved`.
The module records the normalized phrase, search area and number of found
results. IP, session and personal data of the visitor are not saved.

For each “phrase + region” pair the following is maintained:

- total number of searches;
- number of searches without results;
- last known number of results;
- first and last search;
- daily statistics for filtering by period.

If a previously empty query starts returning results, it automatically receives
state "Solved".

## Working with zero output

In the **Modules -> Search queries** section you can filter only empty ones
requests, select the search area and period. The following states are available for each phrase:

- **Disassemble** - a solution is required;
- **Solved** - content or product has already been added;
- **Ignore** - the phrase does not apply to the site.

An administrative note is convenient for recording what needs to be added or what
the search rule should be changed. Unparsed null queries are output to
panel notifications and in the dashboard widget.

The storage setting applies to daily granularity. Aggregated card
phrases are saved until the history is cleared or the module is removed.

## Dependency and rights

The module requires `search` installed and enabled.

- `view_search_analytics` - view the report;
- `manage_search_analytics` - statuses, notes, settings and clearing history.

When uninstalled, both analytics tables and settings are deleted.
