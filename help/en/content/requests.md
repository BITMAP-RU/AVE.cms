# Requests

← [To the section “How the site is assembled”](README.md)

A request is a saved selection of documents and the rules for its presentation. He's coming up
for news, articles, cards, related materials and other lists.

## Parts of a request

| Part | Destination |
| --- | --- |
| Headings and conditions | Define result documents. |
| Sorting | Determines the order of the result. |
| Quantity and pagination | Limit output and create list pages. |
| Element template | Renders one document. |
| Basic template | Wraps pre-made elements and pagination. |
| Outcome Contract | Explicitly lists system properties and fields available to structured consumers. |
| Cash | Saves the result for a specified time. |

The request is inserted using alias:

```text
[tag:request:latest_news]
```

## Normal and advanced modes

The editor opens in **normal mode**. For most lists of this
enough: here are the name, category, number of materials,
sorting, pagination, templates and conditions.

**Advanced mode** is needed when you configure cache, integration, old
PHP template, Native executor or exact data contract. If the meaning of the parameter
is unclear, leave it unchanged and return to normal mode.

The switch only changes the form's appearance. It does not remove hidden settings or
changes public page. The selected mode is remembered in the current browser.

Simple operation:

1. Select a category.
2. Specify the quantity of materials and sorting order.
3. Add conditions if you need to select not all documents in the category.
4. Check the single material template and the overall list template.
5. Run a preview and only then save the request.

## How to show the result

In normal mode there is a separate block **“How to show the result”**. It allows
view the same selection in several views:

| View | What is it suitable for |
| --- | --- |
| **Data Cards** | Check all selected properties of each material. |
| **Compact List** | Quickly review the names and a few basic meanings. |
| **Table** | Compare the same properties of several materials across columns. |
| **JSON** | Validate structured data for APIs and integrations. |

The selection is saved with the request, but only applies to the preview in
admin. To apply it, click **"Update"** in the preview block.

The public site continues to use the saved templates `main` and `item`.
Switching cards, list or table does not change the HTML of the site. To change
public markup, click **"Edit site template"**: the editor will go
to advanced mode and opens the current templates. This division allows
check data without the risk of accidentally changing a working page.

## Conditions and groups

A simple flat list is not sufficient when the filter parts need to work with
different logic. Use groups and specify `AND`/`OR` explicitly:

```text
Опубликован
И
(
  Рубрика = Новости
  ИЛИ Рубрика = Статьи
)
И
(
  Тег = Важное
  ИЛИ Просмотры > 1000
)
```

Design the expression first, then create the groups. Move a condition
between groups changes the meaning of the sample even with the same values.

The `AND` condition should narrow the current set, `OR` - add an alternative inside
of your group. After making a change, be sure to check the actual result and not
just maintaining the form.

### How to read one line of a condition

The editor reads each line from left to right:

```text
Поле документа → сравнение → откуда взять значение → значение
```

For example, the line `Price → greater than or equal to → parameter min_price → number`
means: the visitor can send `min_price=1000`, after which the request will be left
documents with a price starting from 1000. If `min_price` is not transmitted, this is optional
the string is not included in filtering.

1. **Field**—document data that is being checked.
2. **Comparison** - checking rule: equals, contains, greater than, included in the list and
   so on.
3. **Source** - a ready-made editor value or a parameter of the current page or
   forms.
4. **Value** - the editor itself suggests a suitable element for the field type:
   radio button, list, number, date, or selection of a related document.

Old operator codes (`==`, `%%`, `FRE`) are kept for compatibility, but in
The editor displays human-readable names. Free for new conditions
No SQL or PHP required.

### How groups work

The switch in the group header applies to **all immediate elements
this group**:

- **AND** — all lines and nested groups must match;
- **OR** - one matching line or nested group is enough;
- A nested group is a group of parentheses whose contents are evaluated separately.

For example, the expression `Show on main = Yes AND (Type = News OR Type = Article)`
is collected from the root group **AND** and the nested group **OR**. If you leave
everything is in the same group, the meaning will be different.

### How to choose a comparison

| Comparison | Practical meaning |
| --- | --- |
| **Equal / Not equal** | Checks the full value of a field. |
| **Contains / does not contain text** | Searches for a fragment within a string. For an exact element of a compound field, use `\|value\|` processing. |
| **Starts / doesn't start with** | Checks the beginning of a text value. |
| **More/Less** | Compares the normal stored value. |
| **Number more/less** | Uses a numeric field index and is suitable for price, weight, quantity and other numbers. |
| **Included / Not included** | Compares a value against multiple valid options. |
| **Range** | Checks the lower and upper bounds. For a filter from a form, select range processing. |
| **Legacy SQL** | Left for old projects. Do not create new rules through it. |

If the result is not as expected, first check not the template, but the field type and
format of the stored value. Number, text and compound delimited value
are compared differently.

### Condition value

The value has three sources:

| Source | When to use |
| --- | --- |
| **Ready value** | The query always compares the field with the selected or entered value. |
| **URL or form parameter** | The filter receives its value from the address of the visitor's page or form. |
| **Compatibility code** | Old condition with PHP. Shown in advanced mode and not needed for new requests. |In the **Ready value** mode, the appearance of the field depends on its type:

| Field type | What does the editor show |
| --- | --- |
| Checkbox | Two clear buttons **Yes** and **No**. |
| List | Ready-made options from field settings; You can select several to compare with the list. |
| Number | Numeric input without increasing and decreasing arrows. |
| Date | Calendar; the old storage format is saved automatically. |
| Related Document | Search by ID, name and address, taking into account the source headings of the field. |
| Plain text | Input line; for the “included in the list” operator, values ​​are specified separated by commas. |

After selecting a field, the list of comparisons is also shortened. For example, at the checkbox
Only “equal” and “not equal” remain, and the number has numeric comparisons and
ranges. An existing non-standard statement is not removed when the form is opened:
it can be checked and replaced manually.

For a public parameter, specify its name. Below the line
the editor immediately shows the final rule with the usual phrase: which field
it compares where the value comes from and what happens to it. For example:

> The "Catalog" field contains the parameter "catalog" converted to
> `|value|`. An empty parameter does not participate in filtering.

The service view can be expanded separately:

    [tag:cond:catalog]

This is not a regular wildcard tag, nor is it a string substitution. Edit it
no manual required. It tells the compiler that the value is safe
read from parameter `catalog`.

In normal mode, the editor selects processing by field type. Change it manually
possible in **Advanced** mode.

| Processing value | What does the engine get |
| --- | --- |
| Take the text as is | One text value. |
| Take an integer | One number without a fractional part. |
| Take a decimal | One number with a fractional part. |
| Take list | Multiple values, any of which can produce a match. |
| Take range from/to | An array with keys `min` and `max`; empty border is not applied. |
| Wrap value: `|value|` | The exact value inside a composite field, for example Section ID `95` becomes `|95|`. |
| Wrap list: `|value|` | Multiple exact values ​​inside a compound field. |
| Substitute a constant if filled | While the parameter is empty, there is no condition; when filling, the specified constant is compared. Only in this mode the constant field is shown. |

An empty optional parameter completely excludes the condition from the tree. Therefore
do not add delimiters or SQL around the tag manually: the storage method is specified
option **How to read**. Alias fields are substituted when selecting a source, but it
can be replaced if the public URL historically uses a different name, e.g.
oldprice for the old field.The directory creates the same conditions, rather than a separate filter that bypasses queries.
Single values, checkboxes, multiple selections, and ranges remain elements
general AND/OR tree and are edited in the query.

### Example: directory partition

A product request condition usually looks like this:

1. Field - **Directory**.
2. Comparison - **contains**.
3. Source - **URL or form parameter**.
4. The parameter name is `catalog`.
5. Processing - **wrap value: `|value|`**.

When the value is `catalog=95`, the engine looks for the exact marker `|95|` in the catalog field.
This prevents the `9` section from accidentally being the same as the `95` section. If the parameter
missing or empty, the condition does not return a false result, but completely
skipped. The remaining active query conditions continue to work.

## Element template

The template gets the current document and its fields. Typical element:

```html
<article class="teaser">
  <h2><a href="[tag:link]">[tag:doctitle]</a></h2>
  <div>[tag:rfld:excerpt][0]</div>
</article>
```

Open the **Tags** button in the **Template for one material** editor. First
the **Category Fields** group is built according to the selected request category. In it next to
The name and field type shows the finished tag:

```text
[tag:rfld:title][0]
[tag:rfld:image][0]
```

If possible, the system alias of the field is used, and if it is not there, the ID is used. Suffix
`[0]` includes a regular public field template for a list box. For the picture inside
preview tag, you can replace it with `[img]`, for example:

```text
[tag:c760x460:[tag:rfld:image][img]]
```

The field group is not shown in the general template palette: the current one is not there yet
document, and finished cards are inserted with the tag `[tag:content]`. Don't do it
a separate SQL query for each list element.

## Basic template

The main template receives the already assembled elements and is responsible for the container,
header and pagination. Do not duplicate the markings of one card in it.

Check the regular and page results separately: pagination may be different
URL and active state.

## Result contract

The contract is not responsible for searching documents or for HTML. He lists the data
which are allowed to be passed to the API, preview or renderer:

- document system properties: ID, title, alias, teaser, status and dates;
- selected column fields;
- stable system keys and alias fields.

For example, for a news list, a title, alias, teaser, date and field with
cover. The price and balance will not be included in such a contract if they are not selected.

An empty old contract automatically receives a secure base set
system properties. The field selection is saved with the request and transferred
content package. A deleted or foreign field is discarded when saving.

The first iteration contract does not change the tags and HTML of the existing site. Public
the output is still executed by the templates `main/item`, marked in the editor as
**Legacy renderer**.

Nearby you can select the preview renderer:

- **Data cards** are convenient for visual checking of selected properties;
- **JSON** shows the same contract in native format.

The selection is saved to the request, but does not switch the public page. Therefore
you can check the future API format without changing the cards and site listings.

## Execution mode

The request has three executor modes. They only change getting the list
documents. Templates `main/item`, card layout and public URLs remain
the same.

| Mode | What's going on |
| --- | --- |
| **Legacy** | The old selection works with all historical PHP and SQL capabilities. This is the default safe state. |
| **Shadow** | The public site continues to use Legacy. In the control panel, the same request is additionally executed by the Native executor and the results are compared. |
| **Native** | The list is selected according to a parameterized plan without PHP execution from the condition values. Old templates continue to render the result. |

Native cannot be selected immediately. First save the request, enable **Shadow** and
start preview. The system will compare:

- total number of documents;
- ID of documents in the window being checked;
- order of documents;
- current version of conditions, groups, categories and sorting.If everything matches, the **Native confirmed** status will appear and the mode will become
available. The confirmation is related to the plan hash. Changing the condition, group,
heading or sorting automatically removes it; after saving you need to do it again
start comparison.

The order must end in a unique order for automatic confirmation
system field **ID**. The same publication date, position or price may be
on several documents. Without the final ID, MySQL has the right to rearrange such
lines after clearing the cache, even if the composition of the list has not changed. In this case
The control panel will show **Order not fixed** and save Shadow/Legacy. It protects
pagination, menus and cards from imperceptible rearrangement.

For text sorting, equality is determined by the same collation base as in
real `ORDER BY`. For example, `MODEL-X` and `Model-X` are usually considered one
meaning. In the diagnostics they will be shown as two source texts, but the reason
will remain the same: such lines need a separate final order.

In the order designer, click **"Lock by ID"**. The system will add ID
last level in ascending order. If necessary, the direction can be changed.
This does not replace previous levels: date, price or position remains the main one
Typically, ID only stabilizes rows with the same value. Random
order with a completed ID key is added automatically as invisible
final sign; there is no need to add it manually.

Auto-confirmation compares up to 5,000 entire documents. Larger sample
is marked as incomplete and remains on Legacy, even if its beginning is the same.

Native uses declarative HTTP parameter sources: optional
integer, decimal, string, list, range, composite and
a constant based on the presence of a parameter. PHP is not executed in this case. Audit checks
state without parameters, each parameter separately and their combination; for test
the real value of the corresponding field is taken when available.

The request will remain on Legacy if it uses arbitrary `FRE`, `ANY`, arbitrary PHP,
`[field]`, random sorting without a permanent key or raw `USER_WHERE` passed when called,
`USER_FROM`, `USER_JOIN`, `ORDER`, `SQL_QUERY`. The control panel shows specific
reasons. These possibilities are not interpreted approximately.

Declarative range and multiple filter can store historical code
operator `FRE`, but inside they no longer have free SQL: Native builds
parameterized comparison by stored type.

Even in Native mode there is a protective fallback: unsupported runtime
option or unconfirmed version of the plan is executed through Legacy. For
a quick manual rollback is enough to return the **Legacy** mode.

### Bulk checkOn the request list page, open **Native Check**. This page is needed
To safely navigate an existing site:

1. **Check All** moves requests to Shadow. Recognized old conditions
   the typed description is written next to the PHP source, but the public HTML
   continues to be compiled by Legacy executor.
2. The control panel sequentially compares up to 5000 documents of each request, without
   creating a simultaneous load on the database. If the composition of the Legacy sample coincides
   all parameter states, PHP is replaced by a declarative tag and Legacy
   is checked again against the hash of the full list of IDs. If the value is different
   automatically returned.
3. The table separately shows exact match, order mismatch,
   loose sorting and legacy code.
   For an order mismatch, the first differing position, ID from
   Legacy and Native and actual sort key values. For example, the same
   date or price immediately shows that the filter matches, but the request is missing
   only the final ID.
4. **Pin Order** processes only unpinned requests. For everyone
   of these and queries with a difference in order, the system completely compares the previous one
   Legacy order first with `Id ASC`, then with `Id DESC`. Suitable direction
   is saved only if all IDs match exactly. After recording is executed
   repeated control reconciliation; for any difference, the settings and the previous mode
   are automatically restored.
5. **Enable confirmed** changes executor only for requests with current
   hash, complete match and unique final order. Query conditions
   without stable sorting can already be translated into tags, but the request itself
   will remain in Shadow/Legacy.
6. **Return Legacy** rolls back all requests in one action. Hashes check
   are saved, but after changing the request they will still require a new reconciliation.

If no direction ID reproduces an existing output, the request
remains in Shadow/Legacy. This is a normal result: the system does not try
"approximately" correct the order and does not change the public page.

Changing the mode clears the cache of settings and request elements. Therefore, control
After activation, check the pages again, and not using the old cached one
HTML.

## Data preview

The **Update** button executes the saved selection, does not run templates
`main/item` and at the same time makes a shadow comparison of Legacy/Native. Therefore
the result shows data and compatibility, not site design.

Available in preview:- total number of documents found;
- first 5, 10 or 20 results;
- SQL time;
- category, number of groups and active conditions, sorting and publishing
  restrictions;
- only properties selected in the contract;
- SQL for a user with the right to manage queries.
- state of the Native plan, reasons for incompatibility and different IDs.

If you have changed the category, sorting, or basic query parameters, first
keep the shape. The selection of contract fields is transferred to preview immediately and can be
checked before saving.

Each document shown has already gone through the entire condition tree. To check
excluded material, find it by ID, title or alias in the line **Why
Did the document hit or miss?** and click **Explain**.

The right panel shows separately:

- category, status, deletion and publication date;
- the result of each AND/OR group;
- actual field value and expected value;
- the final answer of the real executor.

The free condition `FRE` can contain PHP, SQL or runtime parameters. Inspector
does not execute such code repeatedly and honestly marks the condition as undefined,
but the total “included / not included” is still obtained from the real sample.

## Sorting

Order is collected from top to bottom. The first rule is the main one, the second
is used when the first matches, the third - when the first two match.

Example:

```text
1. Дата публикации — по убыванию
2. Название документа — по возрастанию
3. ID документа — по возрастанию
```

So new materials are shown first, materials with the same date
are ordered by title, and ID makes the result stable for pagination.

Click **Add Level**, select a document system property or field
rubrics and set the direction. Lines can be rearranged beyond the left marker.
The same field cannot be added twice; no more than eight levels are available.
Random order is used alone and cannot be combined with other rules.

After selecting it, the **Order Key** appears. Empty key stirs
materials again, like the previous `RAND()`. The same numeric key always gives
the same order and is suitable for pagination: moving to the next page does not
mixes materials already shown.

For example, the key `2026` can be left constant, but for daily selection
change it with a planner once a day. You don't need to write SQL for this.

For large lists, choose fields with a single value type and a numeric index.
No SQL required. The **"Pin by ID"** button adds a secure
the last level and prevents elements from being rearranged between pages.

Old queries are opened in the new designer without manual migration. Their
historical sequence “category field → system field → ID”
is saved. After saving, the full order is written to JSON, and the old ones
the columns continue to be populated for compatibility with legacy integrations.

The **Pagination template** field shows real templates from the settings by name and
ID. A deleted or non-existent template cannot be saved: first select
existing or create a new one in the pagination settings.

## Cash

The `0` lifetime disables the long-term result cache. The big deadline is coming
stable lists, but delays the appearance of new documents. Saving
document clears associated keys where the dependency is known; design
external data may require explicit invalidation.

Before outputting the list, the engine itself collects document IDs and batch prepares
their JSON snapshots and fields. Therefore the element template can continue to use
regular `[tag:doc:*]` and `[tag:rfld:*]`: transfer fields to SQL query for the sake of
no speed required. If the snapshot doesn't exist yet, the first page will create one; further
a ready-made file is used. Saving the document updates the snapshot automatically.

Don't enable shared cache for result that depends on user, session
or parameters not provided in the key.

## External and AJAX call

These flags allow the request to be received outside of the normal page. Only turn them on
for the actually used endpoint and check the rights, input parameters and
caching. The internal tag `[tag:request:alias]` does not need them.

## Request verification1. Select the minimum outcome contract.
2. Run a structured preview and check the first documents.
3. Check the number of results without cache.
4. Check each condition group separately.
5. Check the empty output and one element.
6. Check the first, second and last page of the pagination.
7. Check the activity and date of publication of documents.
8. After enabling the cache, repeat editing one document.
9. Leave Shadow mode on real traffic, then enable Native separately
   for each confirmed request.
