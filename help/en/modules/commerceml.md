# Exchange with 1C (CommerceML)

The `commerceml` module accepts standard 1C HTTP exchange and stream parses
XML via `XMLReader`. To work, you need the **Products** and **Scheduler** modules.

## Safe launch

1. Create a profile and select a product category.
2. Compare the external ID, article, price and balance with the real fields of the category.
3. Leave **Create unknown products** turned off.
4. Download the test XML and check the log.
5. After verification, allow creation; Include publication of new products separately.

Exchange address: `/exchange/1c/<profile-code>`. Each profile has its own
login and password. The password is stored in a secure secret vault and is not displayed in
panels. Names of uploaded files are normalized, only XML is allowed, parsing
executed with `LIBXML_NONET`.

First, the product is searched by the saved external ID link, then by the article field.
An unknown item is either skipped or created in the selected category. All
runs save the number of processed, created, updated and errored
positions.

Filter `commerceml.document.payload` allows you to change `payload` before
saving the document. Events `commerceml.item.failed` and
`commerceml.import.completed` are suitable for notifications and integration log.
