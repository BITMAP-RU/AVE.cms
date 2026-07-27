# System event directory

← [To the section “Hooks and events”](README.md)

`HookCatalog` stores stable extension points, their purpose, context and
source. Modules can add definitions via `hook_definitions`; runtime
also marks the dynamic hooks that are actually called.

Unless otherwise noted in the table, the context is `App\Common\LifecycleEvent`.

## System and modules

| Event | Destination |
| --- | --- |
| `system.initialize` | The system runtime has been initialized. |
| `module.registered` | Descriptor is registered; context - string code. |
| `module.installed` | Installation is complete. |
| `module.updated` | The update is complete. |
| `module.enabled`, `module.disabled` | The activity state has changed. |
| `module.uninstalling`, `module.uninstalled` | Before and after uninstallation. |

## Public HTTP request

| Event | Destination |
| --- | --- |
| `frontend.request.received` | Public request received. |
| `frontend.runtime.ready` | The configuration and public modules are loaded. |
| `frontend.route.resolved` | The route and page context are defined. |
| `frontend.response.rendering` | The answer is prepared before the final release. |
| `frontend.response.rendered` | The final HTML is generated. |

## Documents and categories

| Event | Destination |
| --- | --- |
| `content.document.loading`, `content.document.loaded` | Before and after reading the document. |
| `content.document.saving`, `content.document.saved` | Before and after saving; `DocumentSaveEvent`. |
| `content.document.created`, `content.document.updated` | Create/Update; `DocumentSaveEvent`. |
| `content.document.published`, `content.document.unpublished` | Change of publication; `DocumentSaveEvent`. |
| `content.document.deleting`, `content.document.deleted` | Before and after removal. |
| `content.document.snapshot_built` | The resulting JSON snapshot has been rebuilt. |
| `content.rubric.loading`, `content.rubric.loaded` | Before and after reading the rubric. |
| `content.rubric.schema_impact` | Complements the list of dependencies before restoring the rubric schema; context is an array. |

Filter `content.rubric.schema_impact` receives `rubric_id`, `field_ids`, descriptions
fields in `fields` and array `items`. The module adds records with keys to `items`
`key`, `title`, `meta`, `details` and returns the entire context. Analysis is nothing
changes in the database and does not have to perform heavy recalculations: its result is included in
recovery confirmation fingerprint.

The engine itself always adds JSON API documents to the analysis: reading and writing it
use alias, ID, type and value structure of the field. For requests separately
conditions, sorting, template tags and the result contract are checked. From
installed subsystems, catalogs, product options and product feeds are taken into account.

## Queries, templates and blocks| Event | Destination |
| --- | --- |
| `content.query.loading`, `content.query.loaded` | Reading an AVE request. |
| `content.query.rendering`, `content.query.rendered` | List/query rendering. |
| `content.template.loading`, `content.template.loaded` | Reading the page template. |
| `content.template.rendering`, `content.template.rendered` | Executing the page template. |
| `content.block.rendering`, `content.block.rendered` | Compatible block call via old tag `block`. |
| `content.sysblock.loading`, `content.sysblock.loaded` | Reading a block from a single ledger. |
| `content.sysblock.rendering`, `content.sysblock.rendered` | The main block rendering loop. |

## Fields

| Event | Destination |
| --- | --- |
| `content.field.registry` | The type registry has been assembled. |
| `content.field.normalizing`, `content.field.normalized` | Normalization of value. |
| `content.field.validating`, `content.field.validated` | Server-side validation. |
| `content.field.saving`, `content.field.saved` | Recording a value. |
| `content.field.rendering`, `content.field.rendered` | Public conclusion. |

The exact set of keys is described in [Field Hooks](fields.md).

## Users

| Event | Destination |
| --- | --- |
| `auth.user.registering`, `auth.user.registered` | Before and after registering as a public user. |
| `auth.user.authenticated` | Successful login. |
| `auth.user.logged_out` | Exit. |
| `auth.phone.code_sent` | The code has been sent to the SMS provider; The provider code and masked phone number are available, but not the code itself. |

##Commerce

Commerce events are available when the appropriate package is installed.

| Event | Destination |
| --- | --- |
| `commerce.cart.changed` | The contents of the cart have been changed. |
| `commerce.order.creating`, `commerce.order.created` | Before and after creating an order. |
| `commerce.order.status_changed` | The order status has been changed. |
| `commerce.payment.completed` | Payment confirmed. |
| `commerce.delivery.quoted` | Delivery estimate received. |

## Product catalog

| Filter | Destination |
| --- | --- |
| `catalog.product_cards.rendering` | Passes the output location, product IDs and source HTML. The processor can return the center cards and `handled=true`. |

Filter context: `context`, `product_ids`, `items`, `html`, `handled`. Commodity
the module captures output only for contexts explicitly set to
central card. If at least one document is missing from the product index,
the original HTML is preserved.

## Cash

| Event | Destination |
| --- | --- |
| `cache.invalidating` | Before deleting a key, tag, or entire cache. |
| `cache.invalidated` | After cleaning is complete. |

## Uploading files

These hooks use an array, not `LifecycleEvent`.| Event | Destination |
| --- | --- |
| `file.upload.validating` | Filter before saving. Gets `allowed`, `reason`, `temporary_path`, `original_name`, `size`, `destination`, `source`. |
| `file.upload.stored` | The file is saved; gets the resulting `path`, original name, size and source. |

The kernel always rejects server service files, ambiguous names, executable
and active web formats, including SVG. For known sources are also checked
valid extensions and content signatures. The module can additionally
tighten the rules: for this
the filter returns the entire array with `allowed=false` and clear text in `reason`.
The kernel cannot return a prohibited format via a hook.

## How to find out the actual context

The directory describes the context class and the purpose of the event. Specific Keys
`LifecycleEvent::data()` depend on the operation. On the test bench, turn on
public debugger, open the **Hooks** tab and map the event to the tabs
document, SQL and timeline. Do not display the entire context to the visitor and do not log
authorization secrets.
## Product requests

After creating a receipt notification, pre-order or price request, the module
`products` calls `catalog.product.demand.created`. Event suitable for
connecting CRM, messenger or your own notification channel.

The context contains `type`, `document_id`, and `public_user_id`. Contact details
are not intentionally transferred to the public directory of hooks: the module with the corresponding
rights to read the full record via `DemandRepository`.

The personal account is expanded with filters `auth.account.links` and
`auth.account.overview`. The first one adds links, the second one adds short cards to
overview page.
