# Contact Forms

← [Back to the “Modules” section](README.md)

The `contacts` module creates public forms without system blocks: feedback,
applications, questionnaires and other requests. The public part receives data, and the panel
stores history, statuses and replies.

## Creating a form

1. Open **Modules → Contact Forms** and create a form.
2. Specify the name and unique alias.
3. Add fields, set mandatory requirements, labels, and value options.
4. Fill in the recipients in the format `Name|email`.
5. Set up form, result and letter templates.
6. Save the form and insert `[mod_contacts:<alias>]` into your document, template, or
   block

Supports single and multiline text, list, multiple list,
checkbox, radio, file, single document and multiple documents. Tag specific
fields are shown in the editor and look like `[tag:fld:<id>]`.

The form can also be opened at `/contacts/<alias>`. Business address
`/callback` uses the form with alias `callback` if it exists.

## Reception and protection

Public submission checks for CSRF, required values, field types, and uploads.
Honeypot and frequency limiting protect against simple spam. Ajax request receives
JSON, the normal form after processing is returned via redirect. History keeps
a snapshot of the sent values and has the states `new`, `viewed`, `replied`.

Only active form fields with their signatures are included in the history. CSRF, service
routing parameters, captcha and fields with passwords or tokens are not saved.
For older hits, these values ​​are additionally hidden during viewing.

In HTML letters the values are `[tag:fld:<id>]`, `[tag:title:<id>]`, `[tag:easymail]`
and system tags are always escaped, and the resulting markup passes the permissive
HTML filter. The subject of the letter, the name and address of the sender are processed separately as
single-line headers: HTML and line breaks are not allowed in them. Therefore
The markup of the letter is set only by the administrator, and the visitor cannot send it via
form field executable HTML or optional mail header.

Connecting the anti-spam module complements, but does not replace the built-in
frequency limitation. Even if the external handler accepted the request, the form limit
continues to operate.

A new request appears in the panel notifications. Opening a post marks it
viewed. The answer is saved in history and sent to the visitor when
the request has a correct email.

Rights:

- `view_contacts` - forms and history;
- `manage_contacts` - forms, fields, copying and deleting;
- `reply_contacts` - responses to requests.

A form with existing history cannot be accidentally deleted. Uninstalling a module
deletes forms, fields and all history, so export the required applications before it.
