# Directory

← [To the section “How the site is assembled”](README.md)

A catalog is a universal hierarchy over documents and fields. It is suitable not only
goods, but also news, knowledge base, services, specialists and other types
content.

## Creating a directory

When creating, select the category, title, alias fields and purpose. System
creates or uses a field of type `catalog` through which documents are linked
with tree sections.

- **Regular catalog** is available in core and does not include prices or cart.
- **Product catalog** appears only with the product module installed and
  includes its projection, remainders, variants and related instruments.

If the product module is not installed, the interface should not offer products
entities or an empty list of “product catalogs”.

## Product catalog overview

The scheduler refreshes the product quality summary hourly. Its latest snapshot
is shown on the dashboard, and an admin notification appears only when the
number of required issues grows. The first snapshot establishes the baseline.

**Catalog → Attributes → Overview** is the starting point for the product
subsystem. It does not create a second catalog or switch public output. It
connects the existing editors in one workflow:

1. **Attributes** define reusable product properties.
2. **Section sets** decide which properties are relevant in each branch.
3. **Colors and configurations** group separate product documents as variants.
4. **Shipping** stores one or more cargo places per product.
5. **Filters** define which attributes are exposed in each catalog section.
6. **Cards** and **Filter appearance** control Twig markup and CSS.

The status area shows which sections have an active set and which runtime modes
are enabled. Sections without a working set are listed with a direct editor
link.

Product and variant attributes are intentionally separate. A **Product
variant** attribute cannot be added to a section set or mapped to a legacy
rubric field. Its scope cannot be changed after use. This prevents a color or
configuration axis from becoming a property of every product in a section.

## Saved product filters

You can save the current set of filters in the product list and in the quality center.
This is convenient for constant queues: “no photo”, “package not filled”,
“disabled goods” or “TCR goods”.

Set up filters, click the button with a bookmark and a plus sign next to the title
block, enter a name. The finished set will appear in the drop-down list and in
will be used in the future with one choice. Resave with the same name
updates the set, the trash button deletes it.

Sets belong to the current employee and do not change the catalog, documents or
public site. Product list and quality center store their views
separately.

## Quick fill table builder

The **Products → Quick fill** page is no longer tied to one fixed group of
columns. Select **Configure table** to prepare a table for a particular task:
updating prices, checking stock, filling attributes, or preparing products for
publication.

The builder has three areas:

1. **Available data** contains core product data, product rubric fields, and
   native attributes. A column disappears from this list after it is selected.
2. **Table columns** contains the current columns and their order. Drag columns
   to reorder them or return an unused column to the available list.
3. **Column settings** controls the visible title, width, and inline editing.

A profile may contain up to 24 columns. Complex fields, images, and structured
values are read-only because they are safer to change in the full product
editor. Single-line fields, numbers, switches, and lists are saved one cell at
a time without reloading the page. If a field is not assigned to a particular
product rubric or attribute set, the table shows a dash and does not create an
unrelated value.

A saved profile can be:

- **personal**, visible only to its owner;
- **shared**, available to other administrators;
- **the default profile**, opened automatically for the current administrator.

A shared profile owned by another administrator cannot be overwritten
accidentally: editing it creates a personal copy. Filters, sections, and
pagination do not change the selected profile.

## Tree sections

The section stores the title, parent, position, activity, and related documents.
The order and level can be changed by dragging. Activity can be switched directly to
listing.

The related document is selected by live search. Use it when section
you need your own page, SEO and content. The disabled section remains in
admin, but should not participate in a regular public tree.

## Section fields

For each section, you can include document fields that the editor must
fill in this context. Fields are shown by category groups; selected
the lines are highlighted.

When creating a new section, the default fields from the directory settings are applied.
The presence of a field in a category and its inclusion in a section are different levels:

- the rubric determines what the document can, in principle, store;
- the section defines what is important for documents in this part of the catalog.

The general product heading field does not need to be included in all sections. Narrow property,
for example "Pump type", assign only to those sections where the editor really
must fill it out. The Feature Wizard uses these assignments and does not
extends the field to other categories. Preparing a new circuit does not remove
source fields and document values.For an initial check, open **Products → Characteristics → Putting things in order**.
The screen will show fields that are assigned too broadly, none of them are filled in
section product or create a filter without a useful selection. These are the hints: system
does not remove anything automatically until the administrator checks the meaning of the field.

## Filters

The filter is based on a real category field or a registered characteristic.
It has activity, order, method of comparison and conditions of application.

Do not create duplicates `weight_1`, `weight_2` just for the sake of independent filters.
Use a single semantic field and link it to the required sections and
filters. This makes it easy to import, API, and migrate your project.

Filter conditions must take into account the data type. The number is compared as a number,
list - by value key, relationship - by ID of the related entity. After change
conditions, rebuild the index if the screen prompts you to do so.

Product filtering has three separate levels:

1. **Products → Attributes** defines the data that products may store.
2. **Products → Filters** selects the attributes shown in each section, their order, and whether they use a range, checkboxes, a select, or a search input.
3. **Products → Filter appearance** edits the shared Twig markup and CSS.

A section must have an attribute set before its filters can be selected. Saving
the filter list rebuilds that section's index. The public site remains unchanged
while the section's filter runtime is **Legacy**.

### From an attribute to a public filter

Use the five setup steps in order:

1. Create a reusable property under **Products → Attributes → Attributes**. Its
   stable code, such as `seat_width`, becomes the public query parameter name.
2. Add the property to the relevant **Attribute sets**. A set controls the
   product form and does not expose anything publicly by itself. Create groups
   such as "Main parameters", "Dimensions", and "Functions" inside the set,
   then assign each attribute to the appropriate group. Group and item order is
   used by the public product page.
3. Under **Assign to sections**, connect the set to the required catalog branch.
4. Under **Section filters**, enable properties, drag them into order, and choose
   a control type.
5. Under **Filter appearance**, edit shared Twig markup and CSS. Appearance does
   not change product selection rules.

A disabled filter keeps its order, label, unit, and control type. It can be
prepared in advance and enabled later; only enabled rows are read publicly.

### Groups on the product page

The public product view model exposes two compatible structures:

- `characteristics` is the existing flat list;
- `characteristic_groups` contains ordered groups with an `items` array.

The standard product template renders a group heading followed by its
attributes. Ungrouped values appear under "Main characteristics". If a product
belongs to several sections, the primary section's set wins, followed by the
configured section order.

```twig
{% for group in characteristic_groups %}
  <h3>{{ group.name }}</h3>
  {% for item in group.items %}
    <div>{{ item.name }}: {{ item.value }} {{ item.unit }}</div>
  {% endfor %}
{% endfor %}
```

### Product public templates

Open **Products → More sections → Page templates** to edit all 19 public product
views: catalog navigation and listings, cards and selections, product details
and variants, comparison, bundles, and product-demand pages.

Each view has one active source. The module fallback keeps the public feature
working when a theme has no override. A file under
`templates/<theme>/views/products_public/` takes priority when it exists and
the namespace is enabled in `theme.json`.

When no override exists, the editor displays the module fallback as a starting
point. **Create in theme** writes the fixed view path, enables the
`products_public` namespace when necessary, records theme revisions, and
invalidates the public presentation cache. **Return to module template**
removes the override while preserving its last content in theme revisions.

Expand **Template data** to see the variables accepted by the selected Twig
view. Clicking a variable inserts its expression into the editor. Validate the
Twig source before saving and check a live product page on desktop and mobile,
because a saved override becomes active immediately.

### Control types

- **Automatic** uses a safe default: range for numbers, a yes checkbox for
  booleans, checkboxes for choices, and a search input for text.
- **Checkboxes** allow several values and are suited to features, colors, and
  compact option lists.
- **Select** allows one value and keeps long option lists compact.
- **Range** compares numeric minimum and maximum values as numbers.
- **Search input** matches part of a stored value and is mainly intended for
  text.

Drag the handle on the left to reorder filters. The same handle supports the Up
and Down arrow keys. No position number has to be entered manually.

### Public selection rules

The attribute code becomes a GET parameter. Examples:

```text
?seat_width[]=40&seat_width[]=45
?motor_count=2
?load_capacity[min]=100&load_capacity[max]=180
```

Several values of the same filter use **OR**. Different filters use **AND**. In
the example, the result must have seat width 40 or 45 **and** two motors.

Before these inputs are applied, the engine already limits products by catalog
section, product rubric, publication state, and static conditions of the
assigned request. Each active filter then adds a parameterized condition. The
administrator does not write SQL on the filter screen.

Native values are read from `catalog_attribute_filter_index`, which stores the
section, product, attribute, and normalized value. It is updated after product
save/import and rebuilt for a section when its filter list is saved. Documents'
JSON values are therefore not scanned on every public request.

Facet counts respect the section and the other active conditions while omitting
the filter whose options are currently being counted. Listing and facet results
are cached for ten minutes and invalidated by the section tag when product data
changes.

Runtime is selected per section: **Legacy** keeps the old fields and conditions,
**Shadow** builds and compares the new index without changing the site, and
**Native** enables the new attributes and configured filter order.

### Check before Native

Under **Products → Attributes → Assign to sections**, first assign a set and
leave both modes in **Legacy** state. Refresh button builds shadow
index, and the report button compares the old and new contours without changing the site.

The report checks not only the total number of products. For each value it
compares the old signature with the permanent key of the directory, compares the number
goods and, for numerical characteristics, minimum and maximum limits.
Rows with discrepancies are shown separately. The **Native** switch will become
available only after complete verification of the selected section. Revert to previous output
You can switch this section back to **Legacy** at any time.

## Values in document

The `catalog` field stores the selected items in a normalized JSON format. When
When opening the document, the values must be restored in the switches. Old
string values are converted by the compatibility layer, but the new code must
work with JSON.

## Public output

The public directory uses a partition tree, document request, and active
filters. The content card itself is configured separately from the system blocks,
because different sites may have different cards and conditions.

For a news catalog, do not include a product index. For a product project
the document remains the data source, and the product table is fast
projection for prices, balances, options and filtering.

The image limit in the card settings applies only to compact
listings. The detail page gets the full gallery from the document's media field,
therefore additional photos do not require re-saving the item or
directory reindexing.

### Product selections

In **Products → Collections** managed product feeds are created for the main,
sections and content blocks. The template stores only a short tag, for example:

```text
[mod_product_list:new]
[mod_product_list:sale]
[mod_product_list:popular]
```

Title, signature, “Show all” link, number of cards, catalog section,
The price range and availability can be changed in the selection settings. Edit site template
no need after that.

There are two filling methods:

- **Only manually selected** – only designated products are displayed;
- **Automatic sourcing** - manually assigned products come first, and
  empty seats are filled with new products, promotions, popular or all
  suitable goods.

Products are added by live search by name, article or ID. The order changes
by dragging. The same product cannot be added twice. Switched off, removed or
Products that do not match the selection filters will not be returned to the site.

The old extended syntax is retained for compatibility:

```text
[mod_product_list:sale:8:125,42,310]
```

It temporarily overrides quantity and manual priority right in the tag. For
new pages, use a short tag and a selection screen: this way the composition can
change the editor without access to HTML.

A promotional product is considered to be one whose price is shown, the current price is greater
zero, and the old price is indeed higher than the current one. Old discount service field
in itself no longer makes the product promotional.

If the old price is not filled in, the regular product card shows the settlement
the comparative price is 25% higher than the current one. This is just a showcase:
the calculated value is not written to the document or index and does not add the item to
shares section. The promotional badge is still managed by a separate attribute
discounts. The behavior is turned on and off in the product catalog settings:
**Catalog → desired catalog → Product card → Estimated old price +25%**.

Product projection is updated automatically after a general save event
document. Therefore, the editor, JSON API, document import and
CommerceML: Integrations should not write directly to the product index table.
Permanently deleting a document also deletes its packaging, payment features,
characteristics and relationship with a group of options; the remaining group automatically
gets a new main variant. Moving to the trash can does not delete this data.
so that the product can be restored.

### Product Quality Center

The **Products → Quality** tab collects work queues for already built
commodity index. It helps you find products:

- without price, section, photograph or article;
- without a brief announcement and SEO description;
- with incomplete packaging with delivery calculation included;
- with zero balance;
- with an outdated product projection;
- with duplicate articles, legacy attribute remnants, native filter gaps, or invalid variant groups.

Click the required queue card - the table will immediately be filtered by this issue.
Search, product status, number of lines and pagination work without rebooting
pages. The edit button opens the original product document.

The **Readiness** indicator takes into account only the data without which the product cannot be
it is normal to sell or process: price, section, photograph, article,
completed packaging and current index. Announcement and SEO are shown as recommendations,
because in existing projects their role can be played by a separate field
rubrics A zero remainder is also a condition, not an error.

Quality Center doesn't fix anything automatically. After saving the document
its product projection will be updated by a regular system event. If she changed herself
field layout or external migration was performed, use point update
index or general restructuring on the products tab.

### Appearance of filters

In the product module, open **Products → Filter appearance** to change the Twig markup
and CSS filters. The draft can be checked on the real partition without affecting
website. After publishing, select the view in the settings of the desired directory.

The **Current Public Output** mode retains the same markup. Mode
**Preview** also does not change anything in the public. Mode only
**Published Twig** includes a new template, and if there is an error, the system
returns to the previous conclusion.

The quantity of products is transferred to the template in `option.count`. Standard class
counter - `.filter_checkbox-quantity`; its appearance can be changed in CSS
presentations. The behavior of options with a zero result is also selected there:
show, disable or hide.

The Twig technical contract is described in
`docs/development/catalog-filter-templates.md`.

### Cards in different sections of the site

In **Catalog → Cards** the “Use Contexts” block manages cards in
favorites, viewed items, search results, and related content.
First assign the published view and leave the mode
**Preparation without publication**. After checking, switch only the required context
on **Central Card**. The remaining locations will continue to use the same
markings

In Twig, `context.code` is available: `catalog`, `favorites`, `viewed`, `search` or
`related`. Through it you can show different commands without copying everything
template. The complete contract is described in
`docs/development/catalog-card-templates.md`.

### Colors and product options

The module immediately adds two characteristics with the **Product Variant** area:
`variant_color` (“Color”) and `variant_configuration` (“Optional”). They are nothing
are not automatically added to existing products. Then open **Products →
Option groups**, collect products of the same model into a group and assign each
the required values. Use color only in groups of those sections where products
really differ in color; the characteristic itself does not become mandatory for
the entire catalogue.

If necessary, in **Catalog → Characteristics** you can create other axes,
such as size or material. U type
“One option” or “Several options” add a general list in advance: signature,
stable key and, if necessary, HEX color. Then in all groups it is selected
the same entry, and the renaming does not have to be repeated for each product.

When one finish combines two colors, use the split-square button next to the
first swatch and choose the second color. Product cards, filters, and the variant
selector display the result as a diagonally split square. This represents one
fixed color combination. Create separate product variants when customers must
choose each color independently.

If the list of values ​​is empty, the editor leaves manual entry of the label and color. This
Compatibility mode for existing groups, not the recommended setup method
new colors and sizes.The same list is used for the usual product characteristics. In the product card
a signature is selected, but a permanent key is stored. Renaming a signature or
color does not require going through all products. Old cards where she was written down
signature, continue to open; a known signature is automatically associated with
directory, and the unknown one is marked as the previous value.

The key is blocked after the first save. This is done intentionally: it can be
store products, filters and external integrations. The signature and HEX color can be
correct at any time. Deleting a value that is already in use is also blocked.

Each option remains a separate document with its own URL, article number, price,
remainder and images. In the general listing, the group is shown as one card,
and on the product page the standard switch uses native values. Old
groups linked to category fields continue to work; this method is hidden in
compatibility block.

The appearance of the switch is set in **Products → Groups of options**:

- **Cards** - the same view, where each option comes with one point;
- **By characteristics** - separate rows for color, size, material or configuration.

You can also enable price, stock, and article display there. New installations
default to **By characteristics**. If an administrator explicitly saved the
**Cards** mode earlier, an update keeps that choice.

The next setting controls the compact variant preview in product cards. It can
be disabled or limited to one through five values before the **more N** link.
Colors are shown as swatches and other attributes as compact text buttons. A
group card uses the minimum group price (with **from** when prices differ) and
asks the buyer to select a concrete variant instead of adding an arbitrary
primary product to the cart.

Each item remains a regular link to a separate product. Therefore, the URL, cart, price and article are always
refer to the actual chosen option. If the group's characteristics are not yet filled in, the engine will show
old cards instead of an empty block.

In a matrix, the axes are dependent. First, the color is selected, then the configuration
are checked specifically for the selected color. If, for example, there is a blue product
as standard, but there is no blue product with pedal, “With pedal”
will remain visible but inaccessible. The engine does not replace it with a random product
different color.

The public model `VariantRepository::forProduct()` exposes `items`, `attributes`,
`count_label`, and the prepared `matrix`.
The system templates are in `modules/products/app/view/variants.twig` and
`modules/products/app/view/variant-matrix.twig`. Keep project-specific markup
in the active theme overrides `views/products_public/variants.twig` and
`views/products_public/variant-matrix.twig`. Open that directory directly from
**Products → Variant groups → Variant templates**. The built-in theme editor can
edit `.twig` files. The `products_public` namespace must be listed in
`theme.json` under `view_overrides`. Assigning attributes alone does not enable
a new catalog card template.

Custom product-card templates can use:

- `item.variants_count` — total products in the group;
- `item.group_price_min` and `item.group_price_max` — group price range;
- `item.group_in_stock` — whether at least one variant is in stock;
- `item.variants.items` — products with URL, article, price, stock, image, and attributes;
- `item.variants.matrix.axes` — prepared selection axes and options;
- `item.variant_settings.card_preview_limit` — configured preview limit.

### How to quickly fill out options

Go to **Products → Option Groups** and select a group. In the table
**Option matrix** each row corresponds to a separate product:

1. Fill in the color, configuration or other differences.
2. Check item number, price, old price and availability.
3. Click Packaging Status to add boxes, weights, and dimensions.
4. To change the name, URL or the entire gallery, open the product with the button
   pencil.Changes to cells are saved automatically. Separate button to save all
There is no need to click on the tables. If the value is not saved, the cell will revert to
previous condition and will show the reason.

For repeating colors and packages, first create a master list of values
in **Catalog → Characteristics**. Then there will be a clear choice in the matrix, and not
handmade text. Manual mode is reserved for old groups and gradual transition.

The matrix does not create a copy of the product: the regular editor, import and API work with those
the same prices, balances and articles. Public appearance also does not change from appearance
this table in the control panel.

After consciously switching the section filters to **Native** mode, their options
also taken from the directory. A custom Twig filter template gets
`option.value`, `option.label`, `option.count`, `option.selected` and
`option.swatch`. An empty `swatch` means a plain text value. Before
switching the public directory and its old filters does not change.

### Freight packages

The weight and dimensions of the package are set in the product editor using a separate delivery profile.
You can add multiple boxes and a number of identical spaces. This data is not
need to be duplicated with characteristics `weight`, `length`, `width`, `height`.

The service `App\Content\ProductShipping::profile($productId)` returns `packages`,
summary `summary` and characteristic `complete`. To calculate the order it is used
`packagesForQuantity($productId, $quantity)`: number of seats multiplied by
quantity of goods. Public design does not automatically output packaging; website
only shows it where the template or module explicitly uses this service.
Delivery methods are configured under **Shop → Settings → Delivery**.
Their checkout markup is edited under **Shop → Settings → Templates**.

### Connections and kits

In **Products → Links and kits** you can link two products as compatible,
mandatory, accessories, analogues or related purchases. If the connection works
in both directions, turn on the corresponding switch: create a second record
no need.

A bundle combines at least two products and stores the quantity of each item.
Select one primary product and mark other items as required or gifts. A complete
bundle may apply a percentage or fixed discount. Public checkout and
manager-created orders use the same promotion calculation.
The name, price, balance and images are always read from the products themselves, so
After changing the product, the kit does not need to be re-saved.
The bundle table shows how many orders used each bundle, the total discount
granted, and the most recent usage date.

The public site does not change after creating links. The output must be explicitly pasted into
product page template:

```text
[mod_product_relations]
[mod_product_relations:compatible]
[mod_product_relations:required]
[mod_product_relations:accessory]
[mod_product_relations:alternative]
[mod_product_relations:together]
[mod_product_bundles]
```

An unqualified tag first displays relationships that the administrator has manually specified.
If a product does not yet have manual links, the module automatically selects products from
the same directory sections. Therefore, the “Similar Products” block can be immediately added to
a general template for a product category, and gradually clarify important connections in the panel.
Tags with a specific type only display manual links of the selected type and not
replace them with automatic selection.

Related products use the same center cards as the catalog. Button
kit adds each item to the regular cart with the current price. If the product
disabled, deleted or does not have a price field configured, an unavailable kit is not
is displayed.

The technical description and JSON API are in
`docs/development/product-relations.md`.

### Product comparison

Open **Products → Compare**, enable the feature and select:

- address of the public page;
- general site template for this page;
- how many products can be compared at the same time;
- whether it is necessary to hide identical values ​​first;
- how many days the link sent to the buyer works.

Enabling a feature does not in itself add buttons to cards. This is intentional:
Different sites have different designs and team locations. Cards are available in the Twig template
`item.comparison.enabled`, `item.comparison.selected`,
`item.comparison.url` and `item.comparison.max`.

Example button:

```twig
{% if item.comparison.enabled %}
  <button type="button"
          data-product-compare="{{ item.id }}"
          data-selected="{{ item.comparison.selected ? 1 : 0 }}">
    Сравнить
  </button>
{% endif %}
```

The engine itself adds and removes products using AJAX. Link to page with counter
can be placed in a general template with the tag `[mod_product_compare]`.

The table includes the article, price, availability and native characteristics of the product.
Characteristics are compared by system code: identical fields of different
products will appear on the same line, even if the signature was later corrected.
A disabled or deleted product automatically disappears from the comparison.

The **Copy link** button saves a set of products, but does not copy their prices and
descriptions. Therefore, the person who opens the link will see the current data. The link duration is set
administrator

Page layout can be overridden in the theme with files
`views/products_public/comparison.twig` and
`views/products_public/comparison-mini.twig`. Full API and template contract
are described in `docs/development/product-comparison.md`.

### Arrival, pre-order and price request

Open **Products → Cases**. One section contains:

- queue of customer requests;
- sending notifications of receipt;
- switches for available scenarios;
- text of consent;
- admission letter template;
- connection to a public page and personal account.

The feature is disabled by default. After turning it on, it also does not insert anything into
card automatically. Add to the product category template:

```text
[mod_product_demand]
```

The form itself will determine the current product. If the buyer is logged in, name, email and phone number
will be taken from the profile. The guest enters an email or phone number manually. Repeated
a request from the same person for the same product updates the record, so the queue
is not filled with duplicates.

For your own Twig template, cards are available:

- `item.demand.restock` - the product is missing and you can subscribe;
- `item.demand.preorder` — pre-order allowed;
- `item.demand.price` — price request allowed;
- `item.demand.api_url` — JSON API address.

When a positive balance appears, subscriptions go into the state
**Item arrived**. Notifications can be sent with one button all ready
queue or to a separately selected buyer. Entries without email remain in the queue
for manual call.

The logged in customer sees the page `/personal/requests` and can cancel
unfinished appeal. The full API and state descriptions are in
`docs/development/product-demands.md`.

## Safe change

1. First change the structure and fields in the test section.
2. Check to save the document and reopen the editor.
3. Rebuild the index and compare products, individual values, and ranges.
4. Check the selection, reset and reselection of each filter.
5. Check the page without results and pagination.
6. Only after this transfer the settings to the remaining sections.
