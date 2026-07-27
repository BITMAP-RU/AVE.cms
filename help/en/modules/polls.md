# Polls

← [Back to the “Modules” section](README.md)

Module `polls` creates single and multiple polls with a schedule,
limited selection, results and a separate archive. Votes are stored via
[Interactions](interactions.md), forms can be protected by the `polls` profile
module [Antispam](antispam.md).

## Insertion and addresses

```text
[mod_poll:8]
```

The number after the colon is the survey ID. Tag returns empty string for invalid
ID. The default public archive is available at `/polls`, a separate survey is available at
`/polls/8`; the base URL is changed in the settings and reserved by the module.

## Survey template

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Survey ID. |
| `[tag:title]` | Escaped question or title. |
| `[tag:description]` | A ready-made description block or an empty line. |
| `[tag:options]` | Options for voting form. |
| `[tag:results]` | Collected result rows. |
| `[tag:protection]` | Antispam fields when voting is available. |
| `[tag:notice]` | Status: not started, completed, or vote already taken into account. |
| `[tag:ballots]` | Number of ballots, if display of results is allowed. |
| `[tag:max_choices]` | Maximum options for multiple polling. |

## Variant template

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Variant ID. |
| `[tag:title]` | Variant signature. |
| `[tag:input_type]` | `radio` for single and `checkbox` for multiple mode. |
| `[tag:checked]` | Attribute ` checked` for selecting the current visitor. |

## Result template

| Tag | Meaning |
| --- | --- |
| `[tag:id]` | Variant ID. |
| `[tag:title]` | Variant signature. |
| `[tag:votes]` | Number of votes for an option. |
| `[tag:percent]` | Share as a percentage with one sign after the dot. |
| `[tag:color]` | Customized result bar color. |

## Archive template

`[tag:items]` contains survey cards, `[tag:pagination]` - archive pages,
`[tag:total]` - total number of published surveys.

Standard JavaScript expects `data-poll-form`, `data-poll-results`,
`data-poll-result-list` and `data-poll-status`. They can be rearranged, but not
delete if Ajax voting and results switching are needed.

## API and hooks

```text
GET  /api/v1/polls/8
POST /api/v1/polls/8/vote
```

Voting takes the array `options[]` and CSRF. The server checks the schedule,
mode, maximum options, the right to change the vote and results display policy.
The response returns the aggregates and the updated HTML of the widget.

Antispam and rate limit are performed independently. Even a successfully processed profile
`polls` does not disable actor and network prefix limits; closed, postponed or
a non-existent survey cannot receive a new internal interaction goal.

`polls.voted` is called after the ballot is saved and receives the poll selected
options, actor and interaction result. `polls.rendering` may change
ready HTML.
