# Ratings

← [Back to the “Modules” section](README.md)

Module `ratings` displays a rating scale for documents and other objects. Voices
stores the required module [Interactions](interactions.md).

## Inserting a widget

For the current document:

```text
[mod_rating]
```

For an explicitly specified object:

```text
[mod_rating:document:145]
[mod_rating:article:news-2026-07]
```

Format: `[mod_rating:TYPE:KEY]`. The type must be present in the setting
**Object types**. For regular documents use `document`, key
serves as ID. Without explicit arguments, the ID is taken from the context of the current document.

## Template tags

| Tag | Meaning |
| --- | --- |
| `[tag:label]` | Custom rating name. |
| `[tag:average]` | Average rating with one sign after the period. |
| `[tag:count]` | The number of users who gave a rating. |
| `[tag:scale]` | Maximum scale, from 2 to 10. |
| `[tag:own]` | The rating of the current visitor or an empty string. |
| `[tag:buttons]` | Ready-made accessible scale buttons with ARIA attributes. |

The external container, endpoint, CSRF and service message are added by the renderer.
Do not remove `[tag:buttons]` if the widget must accept ratings.

## API and behavior

The widget sends the rating to the shared endpoint:

```text
POST /api/v1/interactions/document/145/rating
```

Payload: `operation=set`, `action=rating`, `value=1..scale`. Repeated
evaluating the same actor updates the old value rather than creating a second vote.

Guest ratings are included separately. Rate limit specifies the number of changes in
minute. If the object type is not resolved, renderer should not be used for
it, and the API will reject the entry according to the channel rules.

Filter `ratings.rendering` receives `target_type`, `target_key`, `average`,
`count`, `own`, `scale` and the finished `html`. The handler can return the modified
HTML without interfering with the calculation.

## Reviewing and removing ratings

The action in **Most rated** opens individual votes for a target. A user with
`manage_ratings` may remove one invalid vote or reset the whole target. The
aggregate is rebuilt in the same operation and moderation is written to the
audit log. The document, poll, or gallery itself is not deleted.
