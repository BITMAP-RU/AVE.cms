# Popups

`popups` manages pop-up campaigns, A/B variations and applications.
The campaign specifies page paths, activity period, re-display frequency, and
one of the scenarios: delay, scroll percentage, or attempt to leave the page.

The HTML of options A and B is stored separately. The share of option B is adjusted in
percent; one visitor consistently receives the same option. Available in HTML:

| Tag | Meaning |
| --- | --- |
| `[tag:csrf]` | CSRF token of public form. |
| `[tag:campaign-id]` | Campaign ID. |
| `[tag:variant-id]` | ID of the selected option. |

The form inside the option may contain `name`, `email`, `phone` and additional
fields. For the response status, add an element with `data-popup-status`. Show,
conversion and application are counted separately. The frequency is stored in the visitor's browser.
Impressions and conversions require a signed variant assignment and are counted
only once per visitor per day.

`manage_popups` covers lead processing. Editing public HTML or automatic
injection requires `manage_popup_code` and password re-authentication because
that markup executes on public pages.

Automatic insertion before `</body>` can be turned off in the module settings.
Then the module does not change the public HTML.
