# Health Monitoring

`system_health` monitors the running status of the project after launch. This is not
duplicate of site readiness module: readiness checks configuration before
publication, monitoring regularly checks the infrastructure.

A standard photo includes:

- disk filling;
- MySQL availability and response time;
- size of SQL error log;
- scheduler task errors;
- configured public HTTPS services.

External URLs are checked without following redirects; local and private addresses
cannot be added. The disk full threshold and history storage period are set in
**Settings** tab. When the scheduler is installed, the scan runs every
15 minutes, but it can always be done manually.

Additional verification is added by the `system_health.checks` filter. Handler
returns `status` (`passed`, `warning`, `failed`), `message` and optional
`metric`. After the snapshot, `system_health.completed` is triggered.
