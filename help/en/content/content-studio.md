# Content Studio: from category to publication

← [To the section “How the site is assembled”](README.md)

Content Studio is a common AVE.cms workflow for content design,
filling out documents and preparing public samples. This is not a separate module and
not a new database. It combines already familiar entities: categories, fields,
documents, queries, templates and modules.

This chapter is intended for a person who has opened the panel for the first time and does not yet know
where to start. Read the general model first, then run the tutorial scenario
and only after that proceed to advanced settings.

## 1. Simple model

Imagine a regular news site:

1. **Category** “News” determines what data each news has.
2. **Fields** specify the image, announcement, main text and source.
3. **The document** stores the specific news and the values ​​of these fields.
4. **Category template** turns the data of one news into HTML pages.
5. **Request** receives several news items for the main page or archive.
6. **Query template** turns each news item found into a card.
7. **Site template** adds a general header, navigation and footer.

For articles, an employee directory, a catalog of services or goods, the principle is the same.
Only the fields, rules, and public markup are different.

```text
Рубрика
  -> определяет форму и правила
    -> документ хранит данные
      -> шаблон рубрики показывает одну страницу
      -> запрос получает список страниц
        -> шаблон запроса показывает карточки
```

## 2. What to configure where

| Problem | Section |
| --- | --- |
| Add a new material type | Categories and fields |
| Change the composition or order of fields | Category form designer |
| Create news, page or product | Documents |
| Set up URLs for all materials like | Category |
| Change HTML of one page | Category template |
| Get list of materials | Requests |
| Change card in list | Request template or module renderer |
| Add section tree and filters | Catalog |
| Connect external behavior | Module and hooks |

Don't add the entire page's HTML into a document field. The field stores the content and
the template is responsible for the markup. Then the design can be changed for everyone at once
documents without rewriting every entry.

In the list of categories, the **Materials** column links the structure with the actual work:

- click the number of documents to open a list of already created materials
  only this section;
- click the button with a sheet and a plus to immediately create the material of the selected
  type;
- in the **Template** column its name is shown, and the internal number is left
  small official signature.

This way you don’t need to remember the category ID or re-select it in the section
documents. Buttons take into account user rights: the creation is not shown to those
who is only allowed to view.

## 3. First type of content

Let's say you need to create an articles section.

1. Open **Content → Categories and Fields**.
2. Create the “Articles” section.
3. Add the fields `lead`, `cover`, and `content`.
4. For `lead`, select multiline plain text.
5. For `cover`, select a single image.
6. For `content`, select text type with rich editor.
7. Place the fields in the form designer.
8. Set the width: the image can take up 4 columns, and the announcement can take up 8.
9. Set up a URL pattern such as `articles/%Y/%m`.
10. Save the rubric and check its schema revision.

The document system name already exists. Do not create a second field `title`,
unless you need a separate heading with a different meaning.

### Portable field set

If a similar structure is needed in another project or category, do not create each
field again. Open **Category Fields** and use the **Export** and
**Import** under the list of ready-made sets.

The export creates a small JSON file. It includes:

- groups and their order;
- names, system aliases and field types;
- width, placement, prefix and suffix;
- default value, search and numeric index;
- settings and validation rules for a specific field type.

If the old field does not yet have a system alias, export does not change it in the database, but
creates a name like `field_<ID>` for the transferred file. Preview separately
shows the number of such fields. In the new section, replace if necessary
technical aliases that are understandable before you start filling out the documents.The file intentionally does not include documents and their meanings, PHP code for the category, public
templates, rights, URLs and physical media files. The form conditions are also not transferred:
they refer to the internal field IDs of the original category and should already be collected
after importing by fields of the new project.

Before use, select one of two modes:

- **Copy** creates an independent circuit. Subsequent changes to the original set
  they don't affect her. This is the best option for one-time preparation or transfer between
  projects.
- **Linked** remembers the version of the set and whether its alias matches the real ones
  rubric fields. This option is convenient when one field standard is used
  in several sections.

A typical application or import is done like this:

1. Select JSON or drag it into the window.
2. AVE.cms will check the format, version and availability of field types.
3. The preview will show the total number of fields, how many will be created, how many have already
   whether there are any conflicts.
4. A field with the same alias and the same type is not created or changed again.
5. The same alias with a different type is considered a conflict and blocks import.
6. After confirmation, only missing groups and fields are created, and the schema
   rubric receives a new revision.

The associated set shows a separate status bar in the constructor.
The update is not applied automatically: first click the check button and
Read a summary of the new, changed, and removed form fields.

To release a new version of the imported set, download the JSON again from
with the same code and select **Linked** mode. The current section is running immediately
secure synchronization, and the status appears in other related categories
**There is an update**. Reimporting does not mark changes as applied until
The fields of the current category are indeed not updated.

When synchronizing, a three-way comparison is used: AVE.cms knows the old one
set version, new version and current rubric field. Unchanged setting
receives the new set value, and the locally corrected signature, width, or
the setting is saved. If the set has removed a field, the documents value is not
deleted: the field goes into the list of available ones and ceases to be part of the connection.
Changing the type of filled field is blocked and requires a manual decision.

The delete link command also does not delete fields or data. She just turns them
into completely independent fields of the current section.

A security fingerprint is used between verification and confirmation. If another
the administrator managed to change the composition of the fields, the import will stop and ask again
open preview. Existing import document values are never
overwrites.JSON has a versioned format `ave.cms/field-set`, so regular JSON from
another program cannot be accidentally mistaken for the rubric scheme. Maximum size
file - 256 KB, one set can contain up to 50 groups and 500 fields.

### Conditions of the form

A form condition controls when a specific field or entire group is visible
document editor and whether they can be changed.
For example, the Event Date field can only be shown when the field
“Material type” is set to “Event”.

#### To put it simply

A rubric is a template for a form, and its fields are questions that it answers.
editor. Previously, the form always showed all the questions in a row. Now she can
react to already selected answers:

- for news, do not show product characteristics;
- for the event, open the date, place and registration form;
- after selecting “Pickup”, leave only suitable pickup points;
- when switching to the “Ready” status, fill in a short service field;
- show an important field and prevent its accidental change.

This is not done for the sake of effects in the interface. Short and relevant form is faster
is filled in, and the editor is less likely to save incompatible data. However, AVE.cms does not
rewrites old documents just because the form is opened. Hidden or
the blocked value remains in the database. Automatically changed value
gets a prominent caption and a cancel button so the editor sees the action and
can return its value.

The same check is performed on the server. It is impossible to bypass the restriction of the changed
form or a direct request to the JSON API. A public page is not subject to these rules.
changes: the conditions control only the filling of the document in the panel.

The condition is configured in the right column of the constructor:

1. Select a field on the canvas.
2. Turn on the **Form Conditions** block.
3. Select what to do if there is a match: show, hide, or disallow changes
   current field.
4. If necessary, enable **Required when showing**.
5. For a combo box, you can enable **Limit options** and mark the values,
   which will remain available if the condition is met. Save it yourself first
   list in the field type settings so that the designer shows the current keys.
6. For a simple field, you can select **Change value if match**:
   set the specified value or clear the field. For list and radio button
   the value is selected from ready-made options; for text and numbers it is entered manually.
7. In the main group, add a source, comparison, and expected value field.
8. Select **All · AND** if all rules must match, or **Any · OR**,
   if one is enough.
9. The button with brackets adds a nested group. It is calculated separately and
   works like parentheses in a regular expression.For the condition of the entire group, open the group editing with the pencil button and
enable the **Condition for the entire group** block. The tree of rules and actions are the same, but
the result is applied immediately to all fields of the section. This is convenient, for example, when
the entire “Event Data” block is needed only for material of the “Event” type.
The setting does not need to be repeated for each field.

Example:

```text
Тип материала = Событие
И
(
  Формат = Онлайн
  ИЛИ
  Формат = Смешанный
)
```

The root group uses **AND**, the nested group uses **OR**. Nesting is limited
four levels, and one field can contain no more than 32 rules. These limits
protect the form from cyclical and incomprehensible logic.

The field cannot depend on itself. If the selected source field is deleted or
belongs to another category, the server will stop saving and show an error. That
that the rule looks correct in the browser does not override the server-side check.

Conditions begin to affect the document editor only after enabling general
switch **Document form conditions** for the category. Before using AVE.cms
shows how many existing documents will change visibility, accessibility, or
required fields. For a group condition, the preview counts each section field,
shows documents, hidden and locked values and only then asks
confirmation. The **Prohibit change** action leaves
field or group visible and marks it as read-only. The server retains the previous value,
even if someone tries to spoof the submitted form or JSON API. Values
Hidden fields and groups are also not deleted, and the public template and document output are not
are changing.

The restriction of options is also repeated on the server. For example, the "Method" field
delivery" may show all options for a regular order, but when selecting
“Pickup” is limited to specific pick-up points. Inaccessible items cannot be
enter manually via form or API. If the document already contains old
value, the editor will mark it and ask you to select a valid option instead
silent data deletion.

The automatic value is triggered only when the condition becomes
completed. For example, there was “Status = Draft”, the editor chose “Published” -
the "Ready" field will receive the specified value. If the document is already opened with
status "Published", simply opening or resaving nothing
will overwrite. The changed field is marked **Set by condition** or
**Cleared by condition** and a cancel button. After manual editing, the mark disappears.
Undoing and manual editing pass the editor's explicit choice to the server, so the value
will not be replaced again by the same condition during saving.

Auto actions are only available for simple safe values: text in one
string, number, date, single list, single selection, radio button and color.
They are intentionally unavailable for rich/code content, media, links, catalog and
compound fields: such data cannot be reliably replaced with a single short value.
Adminx and JSON API repeat the rule on the server, so submitting the modified form
manually does not bypass the condition. Preview of the diagram shows how many forms
The action is potentially active, but does not change the saved documents.The preview is linked to the current schema and documents with a security fingerprint. If
Between check and confirmation, another editor changed a group, field, or
document, AVE.cms will cancel saving and recalculate the influence.

### Revisions of the rubric scheme

After changing fields, AVE.cms saves a snapshot of the form. This is not a story of texts
documents, but the structure itself: what fields and groups were there, where they stood, what
width occupied, whether they were mandatory and what conditions were used.

Before restoring an old snapshot, the panel first shows the comparison:

- which fields will be returned;
- which fields will change and what exactly will become different;
- which newer fields will remain in place;
- form conditions will be turned on or off;
- which requests, templates, API contracts and modules depend on the affected fields.

For a modified field, the values ​​are shown as **Was → Will be**. For example:
`Field width: 12 columns → 6 columns` or
`Required: No → Yes`. You don't need to read the internal JSON for this.

Restoring a schema does not erase document values. Field created after
image is not deleted, but remains available in the designer library. If
the scheme has changed after opening the preview, the protection will stop the operation and
will ask you to check the comparison again.

## 4. Document creation and presets

Before opening the editor, AVE.cms prompts you to select **content type**. B
The existing database type remains the category: “News”, “Articles”, “Services” and others.
The system no longer assumes that the new document is necessarily a product.

After selecting a category, two options are available:

- **Empty document** - a form with the usual default values from the category fields;
- **Filling preset** - the same form, pre-filled with a saved blank.

The preset is convenient for repetitive materials. For example, a weekly news story might
immediately receive a standard announcement, source, SEO settings and structure of the main text.
This is not a copy of the document: the new material will always have its own name,
URL, author, dates and identifiers.

To create a preset:

1. Open an already saved document of the desired category.
2. In advanced mode, select **Tools → Creation Preset**.
3. Indicate a clear name and purpose of the workpiece.
4. Select whether to migrate announcement, SEO, search, and field values.
5. Include media, connections and catalog only consciously: the new document will
   refer to the same files and related entities.
6. Save the preset. The next time you create a document, it will appear under this category.

Applying a preset only fills out the form. The database does not change until the editor
will not check the values and click **Save**. The preset can be deleted directly in
creation panels; Documents already created using it will not change.

## 5. Document editor

The editor is divided into semantic areas:- **Basic** - title, category, URL, breadcrumbs and announcement;
- **Document fields** — content defined by the rubric;
- **Publication** - status, author, parent, template and position;
- **SEO and search** - keywords, description, robots, sitemap and tags;
- **Dates** — start and end of publication;
- **Integration** - GUID and project property;
- **Tools** - notes, redirects, revisions and JSON snapshot.

The title, URL, and category fields are data from a single document. Interface blocks
They only help you navigate and do not create additional entities in the database.

## 6. Three editor modes

On the right side of the header are three modes. The selection is saved in the browser
separately for user and category.

### Fast

Shows the main and fields of the document. Suitable when the editor changes regularly
text, image or characteristics and should not be distracted by technical
parameters.

Hidden settings are not cleared. They remain in the document and will appear again when
switching mode.

### Usually

Additionally shows the publication: status, author, parent and template. This
mode for the daily work of a content manager.

### Expanded

Shows all areas including SEO, Dates, Integrations, Summary and Tools.
Use it for initial setup, diagnostics and complex editing.

If the error is in a hidden block, transition from the error summary automatically
will turn on the required mode.

## 7. Saving

There are two common scenarios:

- **Save** saves the document and returns to the list;
- **Save and Stay** saves the document and leaves the editor open.

`Ctrl+S` and `Cmd+S` perform Save and Stay. Before shipping system
synchronizes rich editor, CodeMirror, media lists and composite fields.

Saving is done transactionally. If the category code, field or database validation
returned an error, pending changes should not remain partially written.

## 8. Local draft

While the form is modified, AVE.cms saves its state in the storage of the current
browser. This is not a publication or a hidden entry into the database.

Local draft helps if:

- the tab was accidentally closed;
- the browser reloaded the page;
- the network disappeared during operation;
- the server rejected saving due to a newer version of the document.

When you reopen it, a neat panel appears above the form. She shows
draft time and offers two actions:

- **Restore** — return values to the form for verification;
- **Delete** — leave the server version and erase the local copy.

After recovery, be sure to review the document and click save.
The local draft itself does not change the database and is not visible to other employees.After successful saving, the draft is deleted automatically. File contents
input is not stored for browser security reasons, but already selected paths,
descriptions and values of media fields are preserved.

## 9. Two tabs and two editors

Each document has an internal version number. It increases when changed through:

- main editor of a document or product;
- JSON API;
- import of documents;
- CommerceML;
- content package or demo package;
- operations with documents;
- quick editing of a product;
- catalog and SEO back-office operations.

Example of a conflict:

1. Anna and Boris opened the document version 7.
2. Anna saved the changes. The server created version 8.
3. Boris tried to save the old version 7 form.
4. The server returned the conflict and did not overwrite Anna's work.
5. Boris's browser saved his form locally.
6. After the update, Boris sees Anna's version and can accurately restore his
   draft, compare the data and save the combined result.

Security is rechecked within a row-locking transaction. Therefore
concurrent requests do not pass between pre-check and write.

## 10. Error summary

If saving is not possible, a general list of problems appears above the form. For
Each error shows a clear field name and a server message, or
browser.

Click the error line to:

1. open advanced mode if the block was hidden;
2. switch to the desired group of fields;
3. scroll the form to the problematic element;
4. place the cursor in an accessible editor.

A red alert does not mean that data is lost. Correct the values and repeat
preservation. If the error relates to the category or database code, please report it to the administrator
message text and time of occurrence.

## 11. Full document revisions

The new revision stores two layers:

1. basic document settings;
2. all values ​​of the heading fields, including media and communications in their standard format.

Basic settings include title, URL, announcement, SEO, tags, publication, dates,
author, parent, template, navigation, position, GUID and design property.

The revision intentionally does not store:

- ID and category of the document;
- counters for viewing and printing;
- physical files;
- rows of external modular tables;
- passwords, tokens and system secrets.

Before restoring, the current version is automatically saved as a separate
picture. By default, all basic settings and fields are checked, that is, the button
works like the previous full recovery. Uncheck the boxes that cannot be changed
necessary, or disable the entire group **Basic settings** or **Fields at once
rubrics**. Then AVE.cms will restore only the marked elements. Document ID and
his heading always remains the same.Old revisions are not deleted and are shown as **Fields only**. They can
view and restore: in this case, the basic settings will remain
current. New entries are marked as **Full Snapshot**. If the field after creation
revision has been removed from the category, it is shown in the history, but is not created
again when restoring.

The **On the website** button opens a protected preview. It doesn't show up in search engines
indexing and public cache. For the full image, preview uses historical
document parameters and field values only within the current request.

## 12. JSON without saving

The **JSON Data** button shows the normalized data of the current form, not
writing them to the database. This is useful for validating APIs, integrations, and complex fields.

Preview:

- synchronizes editors and dynamic lists;
- performs routine validation;
- shows system data and fields;
- does not run the category code before and after saving;
- does not create a revision;
- does not change the cache and public snapshot.

## 13. Documents and goods

A product in AVE.cms remains a product category document. Therefore, complete commodity
the editor receives the same modes, drafts, errors, versions and revisions.

The product module adds only its subject area: article, prices,
characteristics, options, cargo spaces, payment programs and cards.
The public directory from Content Studio implementation is not automatically switched and
does not change HTML.

The quick product editor upgrades the document version and saves the revision before
change. Modular values from individual tables can have their own
history; they should not pretend to be document fields.

## 14. Imports, APIs and modules

The correct module creates and modifies documents via
`App\Content\Documents\DocumentMutationService`. This service performs
validation, hooks, category code, field entry, terms, revision, snapshot and
cache invalidation.

The following are already working through the common service:

- JSON API documents;
- import of documents;
- CommerceML;
- content packages and demo packages.

If the module bypasses the service and directly updates the document table, it must
at least raise `document_version` and correctly invalidate derivatives
data. For new code, direct SQL is not the recommended contract.

Project price import does not apply to Universal Content Studio and is supported by
separately. It cannot be used as a sample for a new importer.

### Views and the result contract

The request remains a compatible representation of AVE.cms. The condition tree defines
which documents will be included in the result, and the contract lists which properties
These documents are allowed to be returned in a structured form.In the query editor, you can select system data and category fields, then
run AJAX preview. It does not execute HTML/PHP element templates and
compares the old selection with the parameterized Native plan.

Existing `main/item` templates remain Legacy renderer. For confirmed
The query can be separately switched to only the executor selection; public markup
The site does not change from this. Changing the conditions removes Native confirmation.

### Relationships between materials

Fields “Document from the category”, their multiple options, “Analogs” and “Teasers”
historically were saved in different ways: with one ID, a JSON array or the old
serialize-string. The editor continues to read all these options and does not require
mass resaving of documents.

In a JSON snapshot, the same relationship is now always described the same way:

```json
{
  "format": "ave.document-relation",
  "version": 1,
  "multiple": true,
  "items": [
    {"document_id": 42, "position": 0}
  ]
}
```

The system also maintains a service index of outgoing and incoming connections. Thanks to him
the module can quickly find out not only “what is selected in this field”, but also “what
materials refer to the document.” Index is derived: source
true, the values of the document fields remain.

The block registry prepares the structural content for articles and pages: text,
rich text, image, quote and related materials. The module can
add your own block type declaratively. The very inclusion of the block editor
into a category will be a separate explicit action, so existing rich text fields
and their HTML is not automatically converted.

## 15. What hasn’t changed in the public

New capabilities relate to management and conservation. They don't change:

- existing document URLs;
- HTML templates for categories and queries;
- field tags;
- catalog cards;
- filter algorithms;
- existing database values;
- the original `value` field that is used by existing public templates.

The internal JSON snapshot received an additional object `relation` and a new
version of the cache scheme. Old snapshots are automatically rebuilt when read;
this does not change the site's HTML.

The public result changes only after the editor himself has changed the data
or template and saved the document.

## 16. Training scenario

1. Create a test rubric with text and an image.
2. Open a new document in this category.
3. In **Quick** mode, fill in the title and fields.
4. Switch to **Normal**, leave the status as “Draft”.
5. Open **Advanced**, fill in the description and tags.
6. Click **JSON Data** and check the values.
7. Save and stay.
8. Change the text, wait for the local draft message to appear and
   refresh the page.
9. Restore the draft and save.
10. Edit the document again, then open revisions.
11. View the full snapshot and restore the previous version.
12. Open the document on the website and check the public page.

## 17. Common mistakes

### Draft did not appear

Check if the browser's local storage is allowed and if the site is open in
mode, which clears data after closing. The draft is created only after
real change in shape.

### After recovery, the selected file is missing

Browser prevents programmatic recovery of local file in `<input
type="file">`. Select the file again. The already loaded path in the media field should
recover.

### The system reports a newer version

Do not attempt to resend your request. Refresh the page, restore
local draft, compare the data and save the combined version.

### There are no basic settings in the revision

This is an old revision of the Fields Only format. It is compatible, but was created before
introduction of the full image.

### Field not visibleCheck the active group, visibility condition and editor mode. Error like this
fields will automatically open the desired area.

## 18. Related tutorials

- [Documents](documents.md)
- [Categories and fields](rubrics.md)
- [Document fields](../fields/README.md)
- [Requests](requests.md)
- [Catalogue](catalog.md)
- [Modular system](../modules/README.md)
- [JSON API](../api/README.md)
- [Document Hooks](../hooks/documents.md)
