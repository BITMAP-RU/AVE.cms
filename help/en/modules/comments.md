# Comments

← [Back to the “Modules” section](README.md)

Module `comments` provides tree discussions, pre-moderation, replies,
owner editing and rating of comments. Voices require a module
[Interactions](interactions.md), guest form protection is connected via
[Antispam](antispam.md).

## Inserting a discussion

For the current document:

```text
[mod_comments]
```

For a specific object:

```text
[mod_comments:document:145]
[mod_comments:poll:8]
```

Format: `[mod_comments:TYPE:KEY]`. The type must be allowed by the setting
**Object types**. The comment page parameter is called `comments_page`.
Pagination is applied to root branches: the selected page is given the root
comment along with all its answers, and the answers of neighboring pages from the database are not
are loading.

## List template

| Tag | Meaning |
| --- | --- |
| `[tag:count]` | Total number of discussion comments. |
| `[tag:items]` | A collected comment tree or a message about an empty list. |
| `[tag:form]` | New comment form or invitation to enter. |
| `[tag:notice]` | Message about a closed discussion. |
| `[tag:pagination]` | Links to pages of top-level branches. |

## Comment template

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Comment ID. |
| `[tag:parent_id]` | Parent ID, `0` for the root record. |
| `[tag:depth]` | Branch depth starting from `0`. |
| `[tag:author]` | Escaped author name. |
| `[tag:body]` | Prepared secure HTML text. |
| `[tag:date]` | Date `dd.mm.YYYY HH:MM`. |
| `[tag:datetime]` | ISO date for attribute `datetime`. |
| `[tag:status]` | Pre-moderation box or empty line. |
| `[tag:reply]` | Answer button if depth is enabled. |
| `[tag:votes]` | For/against buttons and current score. |
| `[tag:edit]` | Change button in the allowed time interval. |
| `[tag:delete]` | Owner delete button. |
| `[tag:children]` | Already collected child comments. |

## Form template

| Tag | Meaning |
| --- | --- |
| `[tag:guest_fields]` | Name and email for unauthorized visitor. |
| `[tag:protection]` | Active antispam profile fields `comments`. |
| `[tag:min_length]` | Minimum text length. |
| `[tag:max_length]` | Maximum text length. |

The form must save `textarea[name=body]`, hidden `parent_id`, elements
`data-comment-form`, `data-comment-cancel` and status location
`data-comment-form-status`, if standard JavaScript is used.

## Public API

```text
GET  /api/v1/comments/document/145?page=1
POST /api/v1/comments/document/145
POST /api/v1/comments/37/edit
POST /api/v1/comments/37/delete
```

Creation accepts `body`, `parent_id`, and for guest `author_name` and
`author_email`; CSRF is carried with the `_csrf` field or the `X-CSRF-Token` header.
The response reports the status `published` or `pending`.

You cannot create a discussion for a missing or deleted object. Antispam
checks the contents of the form, but does not replace the frequency limit: always for guest
There are separate limits for the actor cookie and the network/target HMAC prefix. Therefore
changing cookies does not create an unlimited flow of comments.

Setting up pre-moderation has modes: without moderation, only guests, everyone. When
When a document is deleted, its discussion is closed. Hooks `comments.creating`,
`created`, `published`, `updated`, `deleted` and `rendering` allow you to connect
notifications, external moderation and project HTML.
