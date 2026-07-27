# Categories and fields

← [To the section “How the site is assembled”](README.md)

A category describes the type of content. It combines fields, the document editor form,
public templates, URLs, rights and lifecycle code. Category is not a folder
and does not itself form the site tree.

## Where to start

Before creating a category, answer four questions:

1. What data is the same for all documents of this type?
2. Which fields are needed only by the editor, and which are involved in public output,
   searches, queries and filters?
3. How should the URL be formed?
4. What does one document look like and its summary in a list?

Example for the article: `lead`, `cover`, `content`, `author`, `published_source`.
General properties do not need to be duplicated with several fields for different directories:
For this purpose, a single alias of characteristics and settings of a specific
catalogue.

## Form constructor

The left column contains fields that are available but not yet placed. Central region
shows the document form, the right one shows the settings of the selected field.

- the field added to the form disappears from the list of available ones;
- the order can be changed by dragging;
- the field can be returned to the library without deleting the entity itself;
- the grid consists of 12 columns, the width determines the place of the field in the line;
- groups visually separate large forms and do not change public data;
- renaming in the constructor changes the signature in this section, but not the system one
  alias field type;
- viewing settings without changes should not mark the form as changed.

After saving, the message about unsaved changes should disappear. If the field
does not fit or the editor stretches the line, check the width and type settings.

## Field settings in a category

Settings are divided into general and type-specific. One value should not
edited in two places. For example, for a list of values one is used
option editor, and for “Document from category” - one choice of source category.

Frequently used parameters:

| Parameter | Destination |
| --- | --- |
| Title | Field signature in the document editor. |
| Width | Number of form columns from 12. |
| Default | The initial value of the new document. |
| Required | Prohibits saving without a valid value. |
| Prefix/Suffix | Unit of measurement or explanation next to the editor. |
| Public template | Marking the value when outputting a document or query. |
| Load Path | Media catalog with document and field markers. |

Detailed contract types: [Document fields](../fields/README.md).

## Category templates

The main template collects `[tag:maincontent]` of the current document. Additional
templates allow you to choose a different view for a single document without changing it
fields.

```html
<article>
  <h1>[tag:fld:title]</h1>
  <div class="article-content">[tag:fld:content]</div>
</article>
```

Separately configured:

- main and additional document templates;
- Open Graph;
- insertion into `<head>` through `[tag:rubheader]`;
- insert before closing the page via `[tag:rubfooter]`;
- short presentation template where supported;
- code before and after saving.

Do not move the common shell `<html>`, `<head>` and `<body>` into the category: owns it
website template.

## URL template

The category template is a document address prefix. Date markers supported:

| Marker | Meaning |
| --- | --- |
| `%d`, `%m` | Day and month with leading zero. |
| `%Y`, `%y` | Four or two digit year. |
| `%H`, `%M`, `%S` | Hour, minute and second. |

Example: `news/%Y/%m`. In the document editor, a short one is added to the result
alias The full path entered manually with `/` is not overwritten by the system with a template.

## Rights

A category's permissions determine which groups can read, create, edit, and
delete her documents. They complement the rights of the “Documents” section: screen access
does not yet mean the right to change any category.

After creating a category, check the minimum guest, user,
moderator and administrative groups.

## Code before and after saving

Stored PHP is currently supported for project logic. The code gets the context,
described in the editor's information block, and is executed through a secure runtime.
Before saving, you can normalize or reject the data, after saving -
launch the integration without duplicating the main CRUD document.

For reused logic, hooks and module services are preferable. So alone
the handler can be connected to several categories, tested and disabled without
template code edits. See [Document Hooks](../hooks/documents.md).

## Safely change work rubric

1. Make a database backup.
2. Do not delete a field while its values are used by documents, queries,
   catalogs or templates.
3. First add a new field and transfer the data.
4. Check several real documents and public listings.
5. Only after this delete the old alias and clear the cache.

## Schema revisions

A schema revision is the point of restoring the composition of the fields and their location. She
is created automatically when a field, group, order, or constructor changes.
Open the list of categories and click the button with the history icon in the desired line.

Before recovery, the panel shows three different results:

- **will return** - the object was in the old image, but is now missing;
- **will change**—the object exists, but its settings are different;
- **will be saved new** - the object appeared later than the selected image.The last option is especially important: AVE.cms does not remove newer fields. They
remain in the category along with the document values, but are returned to the library
constructor as unplaced. They can be put back into the mold by hand.

Document values ​​are not included in the schema revision and are not overwritten. Before
The system considers documents containing the data of the affected fields as confirmation. If
the diagram or documents have changed after opening the preview, restoration
will be stopped: open the snapshot again and check the new calculation.

You cannot restore a snapshot if it references an unspecified field type,
contains a duplicate alias or conflicts with a field in another category. Correct
reason for blocking, rather than deleting a valid field to bypass the check.

Before each restore, the current scheme is automatically saved separately
picture. Therefore, immediately after the operation, you can select the entry “Before
recovery" and return safely.
