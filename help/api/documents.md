# JSON API документов

← [К разделу «HTTP API»](README.md)

Во всех примерах замените домен и `$TOKEN` значениями своей установки.

## Чтение

```bash
curl -sS 'https://example.test/api/v1/documents/42' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

По alias:

```bash
curl -sS --get 'https://example.test/api/v1/documents/by-alias' \
  --data-urlencode 'alias=news/2026/example' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

Успешный ответ:

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

Ключи `fields` — alias поля, а при его отсутствии строковый ID. Клиент должен
ориентироваться на `type` и `value`, не разбирать внутреннюю строку хранения.
`format` и `version` позволяют в будущем добавить новый формат без тихой поломки.

## Создание

Минимально нужны существующая `rubric_id` и `title`:

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

Если `alias` задан только последним сегментом или пуст, система применяет
шаблон alias рубрики. Неизвестное поле или поле другой рубрики вызывает 422.
Не переданные поля нового документа получают значения по умолчанию рубрики.

Успех возвращает `201 Created`, экспорт созданного документа и:

```json
{
  "meta": {
    "operation": "create",
    "snapshot_warning": ""
  }
}
```

## Обновление

`PUT` и `PATCH` в текущей версии сохраняют не переданные свойства и поля:

```bash
curl -sS 'https://example.test/api/v1/documents/42' \
  -X PATCH \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"status":false,"fields":{"rating":9}}'
```

Успех возвращает `200 OK`, `operation: update` и новый snapshot. Рубрику
существующего документа изменить нельзя; создайте документ в другой рубрике.

## Свойства документа

| Ключ | Значение |
| --- | --- |
| `rubric_id` | Рубрика; обязательна при создании. |
| `template_id`, `parent_id` | Шаблон и родительский документ. |
| `title`, `breadcrumb_title`, `excerpt` | Основные тексты. |
| `alias`, `short_alias` | Публичные адреса. |
| `redirect_code` | `301`, `302`, `307` или `308`. |
| `alias_history` | Режим истории alias: `0..2`. |
| `published_at`, `expire_at` | Unix timestamp или строка даты, понятная PHP. |
| `author_id` | Автор; при создании фактически владельцем становится actor токена. |
| `status`, `in_search` | Boolean; также принимаются `0/1`, `true/false`, `active/published`. |
| `meta_keywords`, `meta_description`, `meta_robots` | SEO. Robots: `index,follow`, `index,nofollow`, `noindex,nofollow`. |
| `sitemap_frequency` | Числовое значение `0..6`. |
| `sitemap_priority` | Число `0..1`. |
| `navigation_id` | Связанный пункт навигации. |
| `tags` | Строка через запятую или массив. |
| `property`, `position`, `guid` | Дополнительные системные свойства. |
| `fields` | Объект значений полей по alias или числовому ID. |

Значение поля можно передать напрямую или как `{"value": ...}`. Структурное
значение соответствует JSON-контракту типа поля; его описание находится в
[разделе о полях](../fields/README.md).

Тело POST/PUT/PATCH должно быть валидным JSON до 2 МБ с
`Content-Type: application/json`.
