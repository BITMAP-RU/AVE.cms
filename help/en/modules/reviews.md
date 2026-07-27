# Reviews

← [Back to the “Modules” section](README.md)

Module `reviews` stores reviews separately from comments and simple votes. U
review has a rating from 1 to 5, title, text, advantages, disadvantages, feature
confirmed purchase and company response. Requires a module to work
[Interactions](interactions.md).

## Inserting reviews

For the current document:

```text
[mod_reviews]
```

For a specific object:

```text
[mod_reviews:document:123]
[mod_reviews:poll:8]
[mod_reviews:gallery:4]
```

Format: `[mod_reviews:TYPE:KEY]`. Products, news and articles are documents
therefore, they use the type `document` and the document ID. Type must be
allowed by the **Where reviews are available** setting. The page parameter is called
`reviews_page`.

## Behavior

On the **Settings** tab you can:

- allow or prohibit guest reviews;
- require the guest's email;
- completely hide the form by leaving published reviews;
- include the title, advantages and disadvantages;
- show a confirmed purchase mark;
- send all reviews for moderation, only guest reviews, or publish them immediately;
- set the number of reviews on the page, text length and limit of submissions per hour;
- select acceptable types of objects;
- change widget, single review and form templates.

Unverified reviews are located on the **Reviews** tab. The moderator can
publish or reject the entry, add a company response and manually post
confirmation of purchase mark. The author's email is not transferred to the public template.

## Widget template

| Tag | Meaning |
| --- | --- |
| `[tag:count]` | The number of published reviews of the property. |
| `[tag:average]` | Average rating with one decimal place. |
| `[tag:items]` | A ready list of reviews or a message about an empty list. |
| `[tag:pagination]` | Links to review pages. |
| `[tag:notice]` | Message about closed form or need to login. |
| `[tag:form]` | Ready form or empty line. |

## Review template

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Review ID. |
| `[tag:author]` | Escaped author name. |
| `[tag:rating]` | Score from 1 to 5. |
| `[tag:stars]` | Five filled and empty star symbols. |
| `[tag:verified]` | Confirmed purchase box or empty line. |
| `[tag:verified_value]` | Sign of a confirmed purchase: `1` or `0`. |
| `[tag:title]` | The header in the finished `h3` or an empty line. |
| `[tag:title_text]` | Escaped header without HTML wrapper. |
| `[tag:body]` | Escaped text with line breaks. |
| `[tag:pros]` | A ready-made block of advantages or an empty line. |
| `[tag:pros_text]` | Escaped merit text without wrapper. |
| `[tag:cons]` | A ready-made block of shortcomings or an empty line. |
| `[tag:cons_text]` | Escaped flaw text without wrapper. |
| `[tag:reply]` | The company's response is either an empty line. |
| `[tag:reply_text]` | Escaped response text without outer wrapper. |
| `[tag:date]` | Date in the format `dd.mm.YYYY`. |
| `[tag:datetime]` | ISO date for attribute `datetime`. |

## Form template| Tag | Meaning |
| --- | --- |
| `[tag:csrf]` | Hidden CSRF field. |
| `[tag:guest_fields]` | Name and email for unauthorized visitor. |
| `[tag:title_field]` | Title field, if enabled. |
| `[tag:pros_cons_fields]` | Strengths and weaknesses fields, if enabled. |
| `[tag:protection]` | Active antispam profile fields `reviews`. |
| `[tag:min_length]` | Minimum text length. |
| `[tag:max_length]` | Maximum text length. |

For regular submission, the form must contain `data-review-form`, field
`rating`, `textarea[name=body]`, `type=submit` button and element
`data-review-status`. External container with API address, CSRF and public assets
the module is added automatically.

## Public API

```text
GET  /api/v1/reviews/document/123?page=1
POST /api/v1/reviews/document/123
```

`GET` returns published reviews, pagination, and rating aggregate. `POST`
accepts `rating`, `body`, optional `title`, `pros`, `cons`, and for guest
`name` and `email`. CSRF is transmitted by the `_csrf` field or header
`X-CSRF-Token`. The response reports the status `published` or `pending`.

The average score and quantity are stored in a separate aggregate table and not
are recalculated each time the card is shown.

## Hooks

| Hook | Context |
| --- | --- |
| `reviews.creating` | Array `allowed`, `status`, `rating`, `title`, `body`, `pros`, `cons`, `target_type`, `target_key`; you can reject or correct the entry. |
| `reviews.created` | Full saved review record. |
| `reviews.rendering` | Array `target_type`, `target_key`, `data`, `settings`, `html`; allows you to replace the resulting HTML. |

Templates do not execute PHP. For design logic, use hooks, and for
design - classes in templates and CSS themes of the site.
