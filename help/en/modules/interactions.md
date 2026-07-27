# Interactions

← [Back to the “Modules” section](README.md)

Module `interactions` stores unified visitor actions: ratings,
voices, reactions and choice of options. It does not display a standalone widget and does not
has an insertion tag. The user interface is provided by dependent
modules such as **Ratings**, **Comments** and **Polls**.

## Data model

Each action is defined by four parts:

| Part | Example | Destination |
| --- | --- | --- |
| Object Type | `document`, `comment`, `poll` | What class of entities does the action belong to? |
| Object key | `145` | ID or other stable string key of the entity. |
| Channel | `rating`, `comment_vote`, `poll_vote` | A set of rules and permitted actions. |
| Action | `rating`, `up`, `option:8` | Specific user choice within a channel. |

Before any recording, the module checks the existence of the application object. Type
`document` supported by runtime itself: deleted or missing document
will receive `404`. Types `comment`, `poll` and `gallery` register the corresponding
modules. For your own type you need resolver `interactions.target.resolve`;
Checking the key format or policy channel alone is not enough.

## Channel modes

| Mode | Behavior |
| --- | --- |
| `selection` | The user is left with one option. Repeated `toggle` may remove the selection. |
| `multiple` | Several variants are stored simultaneously with the limitation `max_selections`. |
| `rating` | A single numeric score is stored in the range `min_value`–`max_value`. |

Operation `set` creates or updates an action, `toggle` switches it, and
`remove` deletes. After the change, the channel units are rebuilt in the same
transactions: public code does not need to count votes from raw records.

## Public API

Object Summary:

```text
GET /api/v1/interactions/document/145
```

The response contains public channel definitions, action aggregations, and selections
current visitor. The new CSRF token is returned in the `csrf` field.

Action Record:

```text
POST /api/v1/interactions/document/145/rating
Content-Type: application/json
X-CSRF-Token: <token>

{"operation":"set","action":"rating","value":5}
```

For react, the request might look like this:

```json
{"operation":"toggle","action":"up"}
```

A successful response returns `changed`, the operation performed, actor and a complete new
summary `snapshot`. The errors use HTTP codes `401`, `403`, `409`, `419`,
`422` and `429`.

## Register your channel

The module adds a channel with filter `interactions.channels`:

```php
Hooks::add('interactions.channels', function ($channels) {
	$channels['reaction'] = array(
		'code' => 'reaction',
		'label' => 'Реакция',
		'mode' => 'selection',
		'actions' => array('like', 'useful'),
		'target_types' => array('document'),
		'public_read' => true,
		'public_write' => true,
		'anonymous' => true,
		'toggle' => true,
		'rate_limit' => 20,
		'rate_window' => 60,
	);

	return $channels;
});
```

The channel code and actions accept Latin characters, numbers and service delimiters. For
dynamic actions, instead of `actions`, you can declare `action_pattern`.

## Register your own object type

The Resolver must explicitly report whether it has processed the type, whether the object exists, and
whether writing is allowed in its current state:

```php
Hooks::add('interactions.target.resolve', function ($target) {
	if (!is_array($target) || $target['type'] !== 'article') {
		return $target;
	}

	$article = ArticleRepository::find($target['key']);
	$target['handled'] = true;
	$target['exists'] = $article !== null;
	$target['writable'] = $article && $article['status'] === 'published';
	$target['message'] = $article ? 'Материал закрыт для действий' : 'Материал не найден';

	return $target;
});
```

An unknown type without a resolver is rejected with `422`, a missing object is rejected with
`404`, existing but not writable - with `409`. Resolver shouldn't
create an object or change its state.

## Hooks

| Hook | When | is called Basic data |
| --- | --- | --- |
| `interactions.channels` | When assembling the channel registry | Associative array of definitions. |
| `interactions.target.resolve` | Before writing a policy and creating an internal goal | `type`, `key`, `operation`, `handled`, `exists`, `writable`, `message`. |
| `interactions.recording` | Before rate limit and recording | target, channel, action, value, operation, metadata, actor. |
| `interactions.recorded` | After commit and recalculation of aggregates | changed, operation, actor and snapshot. |

In `interactions.recording` you can change the data or throw an exception with
suitable HTTP code. Do not re-record to the same channel from
`interactions.recorded`: This will create a recursive loop.

## Settings and removal

- **Anonymous actions** allow issuing a persistent anonymous actor to the visitor.
- **Actions per minute** sets the overall limit if the channel has not declared its own.
- Each entry is limited by actor. Additional valid for guests
  limit by HMAC network prefix and specific target: IPv4 `/24`, IPv6 `/64`.
- **IP Hash** controls only the storage of the diagnostic hash in the record.
  The network limiter operates independently and does not store the original IP.

Uninstall removes goals, actions, and aggregates. Dependent active modules first
needs to be disabled or uninstalled.
