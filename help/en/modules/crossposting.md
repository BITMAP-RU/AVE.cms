# Cross-posting

The `crossposting` module publishes AVE.cms documents to Telegram, VK
communities, Zen through a bridge, and custom HTTPS webhooks. Network requests
run in the background and never block document editing.

## Setup

1. Install and enable **Scheduler** and **Cross-posting**.
2. Add a channel and test its connection.
3. Create a publication template.
4. Create a rule that selects a rubric, template, channels, and delivery time.
5. Publish a test document and inspect the **Queue** and **Attempts** tabs.

The **New publication** action queues one document manually. An empty date
means now; a date in the future creates a scheduled job.

## Templates

Available native tags include:

- `[tag:document:title]`;
- `[tag:document:excerpt]`;
- `[tag:document:url]`;
- `[tag:document:published]`;
- `[tag:document:tags]`;
- `[tag:document:meta_description]`;
- `[tag:field:alias]` for a rubric field.

An image field is selected by alias. Preview renders the current form against
a real document before the template is saved.

**Plain text** safely escapes markup characters. **Safe HTML** preserves the
formatting supported by the gateway; Telegram accepts emphasis, code, and links.

## Rules and statuses

A rule can run manually, immediately after publication, or at the document's
publication date. After an already delivered document changes, the rule can do
nothing, update the remote post, or create a new one.

A template marked **For all gateways** can be reused. Create separate templates
and rules when Telegram, VK, and Zen need different copy or images.

Every document revision, rule, channel, and scheduled time has a stable
idempotency key. Repeated saves and parallel scheduler runs therefore do not
create duplicate posts.

**Published** means the platform accepted a post and returned its identifier.
**Verified** means a gateway additionally confirmed that the remote post still
exists. Subscriber reads are not reported as delivery.

The Scheduler runs `crossposting.publish` every minute. Temporary failures use
exponential retry delays and remain available for manual retry.
Permanently failed publications also appear in the control-panel notifications.

## Custom gateways

Another module can register a factory from its `services.php` through
`GatewayRegistry::register()`. The gateway implements `GatewayInterface`, uses
`OutboundHttpClient`, and receives public settings separately from protected
secrets. The built-in webhook also supplies a stable `Idempotency-Key` and an
optional HMAC SHA-256 signature.
