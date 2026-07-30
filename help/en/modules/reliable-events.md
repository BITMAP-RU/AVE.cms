# Reliable outbound events

The `reliable_events` module delivers signed webhooks without making a network
request during document saving.

## Purpose

This is neither an administrator audit log nor a cron run log. The module
connects AVE.cms to external services such as a CRM, a messaging bridge,
analytics, or a custom integration.

The engine or another module emits a named event. `reliable_events` stores it,
matches active subscriptions, and sends signed JSON to every recipient. A
temporarily unavailable service therefore does not block document saving or
discard the event.

## How it works

1. AVE.cms writes the document and event in one database transaction.
2. After commit, the event remains in the outbox.
3. Scheduler claims a small batch.
4. The recipient receives a signed HTTP POST.
5. The result is stored in the delivery history.
6. Temporary failures are retried with increasing delay.

An unavailable integration does not make an editor wait for an HTTP timeout.

## Scheduler integration

The module registers two tasks in the [Scheduler](scheduler.md):

- `reliable_events.deliver` runs every minute, claims up to 20 ready events,
  sends webhooks, and schedules temporary failures for retry;
- `reliable_events.cleanup` runs daily at 03:40 and deletes successfully
  delivered events older than 30 days.

The **Process queue** button runs one batch immediately. It is useful for
testing but does not replace the server-side Scheduler tick. Scheduler task
runs are shown in Scheduler, while recipient HTTP responses are shown on this
module's **Delivery attempts** tab.

## Subscription

In **Modules → Reliable events**, specify a title, event code, HTTPS endpoint,
retry count, timeout, and secret. A code can be exact, such as
`content.document.published`, or use a group mask such as
`content.document.*`.

Document events:

- `content.document.created`;
- `content.document.updated`;
- `content.document.published`;
- `content.document.unpublished`.

A module can emit its own event:

```php
use App\Modules\ReliableEvents\Dispatcher;

Dispatcher::emit('my_module.completed', array(
    'operation_id' => 15,
    'result' => 'success',
), array(
    'aggregate_type' => 'operation',
    'aggregate_id' => 15,
));
```

An event is queued only when at least one matching active subscription exists
at emit time. A subscription created later does not receive past events: the
outbox is a delivery queue, not a complete audit journal.

## Signature

The JSON envelope contains `id`, `event`, `occurred_at`, and `data`. The
signature header is `X-AVE-Signature: sha256=<value>` and signs
`<timestamp>.<raw JSON body>` with HMAC SHA-256.

The receiver should verify the signature, timestamp age, and Event ID
uniqueness. Successful recipients are not called again. A stopped event can be
returned to the queue manually.
If a delivery worker terminates unexpectedly, an expired lock is reclaimed by
the next worker run.

Automatic delivery requires a configured server-side
[Scheduler](scheduler.md) tick.
