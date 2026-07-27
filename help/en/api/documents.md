# JSON API documents

← [To the “HTTP API” section](README.md)

In all examples, replace domain and `$TOKEN` with your installation values.

## Reading

```bash
curl -sS 'https://example.test/api/v1/documents/42' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

By alias:

```bash
curl -sS --get 'https://example.test/api/v1/documents/by-alias' \
  --data-urlencode 'alias=news/2026/example' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

Successful response:

```json
{
  "success": true,
  "data": {
    "format": "ave.document-api",
    "version": 1,
    "document": {
      "id": 42,
      "rubric_id": 3,
      "rubric_template_id": 1,
      "title": "Пример",
      "alias": "news/2026/example",
      "excerpt": "Краткий анонс",
      "status": 1,
      "deleted": 0,
      "published_at": 1784062800,
      "expire_at": 0,
      "changed_at": 1784062800,
      "author_id": 1,
      "meta": {
        "title": "301",
        "description": "Описание страницы",
        "keywords": "пример, api",
        "robots": "index,follow"
      }
    },
    "fields": {
      "cover": {
        "id": 17,
        "type": "image_single",
        "value": {
          "url": "/uploads/example.jpg",
          "description": "Обложка"
        }
      }
    },
    "revision": 1784062800,
    "generated_at": 1784062801
  }
}
```

The keys `fields` are the alias of the field, and in its absence, the string ID. The client must
focus on `type` and `value`, do not parse the internal storage string.
`format` and `version` allow you to add a new format in the future without silently breaking.

## Creation

The minimum required is the existing `rubric_id` and `title`:

```bash
curl -sS 'https://example.test/api/v1/documents' \
  -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data-binary @document.json
```

```json
{
  "rubric_id": 3,
  "title": "Новая статья",
  "alias": "new-article",
  "excerpt": "Текст для карточки и метаописания по умолчанию",
  "status": true,
  "tags": ["API", "Интеграция"],
  "meta_description": "Описание страницы",
  "fields": {
    "cover": {
      "url": "/uploads/articles/cover.jpg",
      "description": "Обложка статьи"
    },
    "rating": 8
  }
}
```

If `alias` is specified only as the last segment or is empty, the system applies
category alias template. An unknown field or a field from another category causes a 422.
The fields of the new document that are not transferred receive the default values of the category.

Success returns `201 Created`, exports the created document and:

```json
{
  "meta": {
    "operation": "create",
    "snapshot_warning": ""
  }
}
```

## Update

`PUT` and `PATCH` in the current version retain untransmitted properties and fields:

```bash
curl -sS 'https://example.test/api/v1/documents/42' \
  -X PATCH \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"status":false,"fields":{"rating":9}}'
```

Success returns `200 OK`, `operation: update` and a new snapshot. Category
an existing document cannot be changed; create a document in a different category.

## Document properties

| Key | Meaning |
| --- | --- |
| `rubric_id` | Heading; required upon creation. |
| `template_id`, `parent_id` | Template and parent document. |
| `title`, `breadcrumb_title`, `excerpt` | Basic texts. |
| `alias`, `short_alias` | Public addresses. |
| `redirect_code` | `301`, `302`, `307` or `308`. |
| `alias_history` | History mode alias: `0..2`. |
| `published_at`, `expire_at` | Unix timestamp or date string understood by PHP. |
| `author_id` | Author; when created, the actor of the token becomes the actual owner. |
| `status`, `in_search` | Boolean; `0/1`, `true/false`, `active/published` are also accepted. |
| `meta_keywords`, `meta_description`, `meta_robots` | SEO. Robots: `index,follow`, `index,nofollow`, `noindex,nofollow`. |
| `sitemap_frequency` | Numeric value `0..6`. |
| `sitemap_priority` | Number `0..1`. |
| `navigation_id` | Related navigation item. |
| `tags` | Comma separated string or array. |
| `property`, `position`, `guid` | Additional system properties. |
| `fields` | Field value object by alias or numeric ID. |

The field value can be passed directly or as `{"value": ...}`. Structural
the value corresponds to the JSON contract of the field type; its description is in
[section about fields](../fields/README.md).

POST/PUT/PATCH body must be valid JSON up to 2 MB with
`Content-Type: application/json`.
