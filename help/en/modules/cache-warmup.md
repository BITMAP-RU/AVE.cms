# Automatic cache warmup

The `cache_warmup` module opens public pages in advance so the first visitor
does not wait for expensive cache generation.

The queue is updated after a full cache flush, saving a published document, a
daily task, or a manual rebuild. A full plan includes the home page, all
published documents, and every active product feed. At about 80% of its own
TTL, the module directly and forcibly refreshes the feed's internal cache, so
a marketplace crawler does not pay for cold generation. Protected feeds work
the same way, but their tokens are neither stored nor shown in the queue. A
feed with TTL `0` is warmed again only by a full plan.

**URLs per run** limits server load. A value of `10–15` suits a typical site:
Scheduler processes another small batch every minute instead of opening the
whole site at once.

After three failures, a URL waits for a new full plan. The history retains its
HTTP status, response time, and error message. A terminal failure after the
third attempt is also shown in control-panel notifications.

The base URL may be left empty to use the Scheduler request host. Production
sites should set their canonical HTTPS URL. The outbound HTTP client validates
DNS and rejects private or reserved targets.

Automatic processing requires the [Scheduler](scheduler.md). No public CLI is
required.
