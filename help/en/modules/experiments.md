# A/B tests

Module `experiments` distributes visitors between options, applies
option to a public page and counts impressions and conversions. The appointment is stable:
the visitor receives one experiment option for the entire cookie period.

## Goals of the experiment

| Goal | Target Key | Behavior |
| --- | --- | --- |
| API or tag | Not required | The variant receives its own component or tag `[mod_experiment:code]`. |
| Regular block | Block ID | The module automatically wraps the block result. |
| System unit | System unit ID | The module automatically wraps the result of the system block. |
| CSS selector | For example, `#buy-button` | The option is applied to found DOM elements. |
| Price | CSS price selector | Only the displayed price text changes. |

The **Price** mode does not change the price of the product, cart, order or payment. For the test
calculation price requires a separate project processor
`experiments.assignment.resolved` and server verification at all stages of the order.

## Options

The experiment contains at least two options. Weight specifies the relative proportion, and
coverage - the percentage of visitors participating in the test. Payload option supports:

- `control` - leave the original content;
- `replace` - replace the contents of the target;
- `before` and `after` - insert HTML next to the target;
- replacing text, adding a CSS class and links;
- displayed price value.

You can leave the experiment as a draft, run it, pause it, or end it.
The start and end period are optional.

## Inserting into a template

```text
[mod_experiment:promo_button]
```

A stable slot is created in place of the tag. The option is loaded upon receipt
pages, so a common full-page cache does not mix variants of different
visitors.

To automatically record a conversion, add an attribute to a button or link:

```html
<button data-experiment-conversion="promo_button">Отправить</button>
```

An arbitrary event is specified by the second attribute:

```html
<a data-experiment-conversion="promo_button"
   data-experiment-event="checkout_started">Оформить</a>
```

From JavaScript, the event can be sent manually:

```javascript
window.AVEExperiments.convert('promo_button', 'form_sent');
```

## Public API

```text
GET  /api/v1/experiments/{code}
GET  /api/v1/experiments/assignments?codes=promo_button,hero_title
POST /api/v1/experiments/event
```

The last endpoint accepts `code` and `event`. The variant ID from the client is not
accepted: the server recalculates the destination and writes the event only to
this option. For one visitor, the uniqueness of an event is considered once a day,
however, the total number of events is also maintained.

##Hooks

###`experiments.assignment.resolved`

Called after the server has selected an option. Available in context:

- `experiment` - complete record of the experiment;
- `variant` - selected option;
- `visitor_hash` - anonymized hash of the visitor.

The result of the event is the destination array. The handler can return
a modified array, for example for design integration with a service layer.

###`experiments.event.recorded`

Called after an event is recorded. The context contains `experiment`, `variant`,
`event_code` and flag `unique`.

## Cache and privacy

In HTML, only the same slot and active configuration are cached for all
goals. The assignment is requested by the client via the API. Random seed is stored for a year
in cookie `ave_experiment_seed` with `HttpOnly`, `SameSite=Lax` and `Secure` on HTTPS.
IP and original seed are not saved in statistics.

## Permissions and deletion

- `view_experiments` - viewing experiments and results;
- `manage_experiments` - creation, launch, statistics reset and deletion.

Uninstallation removes experiments, variants, statistics, deduplication
visitors and module settings.
