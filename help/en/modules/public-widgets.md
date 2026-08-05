# Public widgets

← [Back to Modules](README.md)

The private `widgets` module contains small public blocks for exchange rates
and email subscription. It is not included in the regular core distribution
and is installed separately.

After installation open **Modules → Widgets**. Saving creates an override under
the active theme's `views/widgets_public/` namespace; returning to fallback
restores the module view.

The exchange template receives `usd` and `eur`. The subscription template
receives `csrf`, `message`, and `success`. Delivery, rate limiting, and email
validation remain enforced by the module runtime.
