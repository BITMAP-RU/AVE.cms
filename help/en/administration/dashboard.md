# Dashboard and interface

← [To system management](README.md)

A dashboard is a working summary, not a separate source of data. His cards and tables
collected from core providers and contributions from installed modules.

## What to show

For a regular website, general indicators are useful:

- active documents and recent changes;
- active public users;
- 404 and SQL errors;
- cache and diagnostic status;
- notifications requiring action.

Product orders, balances and payments should not appear without established
commerce modules. The widget of a missing or disabled module should not leave
empty area in the grid.

## Interface setup

In **Settings → Interface** you can change the visibility and order:

- left menu items;
- dashboard widgets.

The setting is applied on top of the module registry. It does not delete the partition or change
rights: the hidden item must still be protected by a permission check on the route.

If a module adds a widget, header action, or notification, the contribution is declared
in `module.php`, and the user setting only determines its position and
visibility. More details: [Module contributions to UI](../modules/contributions.md).

## Notifications

The bell combines core events and module notifications. The element must have
action link, stable ID and access right. DB errors, new requests and
new orders may use the same interface but are shipped differently
providers.

A large calculation cannot be performed every time a panel page is opened. For
counters, use a fast aggregate, index or short cache.
