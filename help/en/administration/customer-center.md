# Customer center

## Search, saved views, and CSV

`Ctrl+K` finds public accounts by name, login, email, phone, company, or ID.
Customer Center search and segment filters can be saved as a personal view. The
**CSV** button on the Users tab exports the current search result in chunks and
protects spreadsheet applications from formula-like values.

The customer center is available under **System -> Site users**. It does not
create a second person registry. A public site account remains the primary
record, while installed modules contribute related information.

The card can show account contacts, orders, contact form submissions,
favorites, viewed products, login identities, manager notes, and calculated
segments. Missing optional modules simply leave their sections empty.

Segments identify new, buying, repeat, high-value, inactive, and no-order
accounts. They are filters only and do not change permissions or trigger
automatic marketing actions.

Manager notes are private to the control panel. Duplicate merge moves orders,
profile values, engagement data, external identities, and notes to the target
account, then disables the source account. Accounts linked to control-panel
access cannot be consumed. A conflicting identity provider also blocks the
merge to avoid losing a login method.

## Login and account templates

The **Account pages** tab covers the main login, registration, and profile
pages. Its **Service routes and fragments** block also exposes the header user
panel, system messages, phone login, OAuth provider buttons, and connected
account controls.

A fragment is rendered only when its provider is installed and enabled.
Resetting a customized Twig template returns to the active-theme file or the
built-in system fallback.
