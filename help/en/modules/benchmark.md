# Performance

← [Back to the “Modules” section](README.md)

Module `benchmark` performs a short synthetic database check,
file system and CPU. It helps to compare the environment before and after the migration or
configuration changes, but does not replace load and acceptance testing.

## How to use

1. Install the module and open **Settings → Performance**.
2. Make sure that no heavy service operations are being performed at this time.
3. Run the scan and wait until the current page completes.
4. Compare individual metrics and environments, not just the overall score.

The check sequentially measures writing, reading, and updating temporary rows in
DB, working with temporary files, mathematical and string CPU operations.
The test table is cleared and the temporary directory is deleted. External sites, mail and
public documents are not affected. One launch is allowed at a time.

## Result and history

The report contains the duration of the stages, the final estimated score and a snapshot
environment: PHP and database versions, memory limit, OPcache, GD and WebP support. History
You can delete one run at a time or clear it completely. Home widget shows
last result for users with permission `view_benchmark`.

Rights:

- `view_benchmark` — view section, history and widget;
- `manage_benchmark` - start checking and deleting history.

When uninstalling, the history and service table of the module are deleted.
