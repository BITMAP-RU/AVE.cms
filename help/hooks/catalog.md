# Каталог системных событий

← [К разделу «Хуки и события»](README.md)

`HookCatalog` хранит стабильные точки расширения, их назначение, контекст и
источник. Модули могут добавлять определения через `hook_definitions`; runtime
также отмечает фактически вызванные динамические хуки.

Если в таблице не указано иное, контекст — `App\Common\LifecycleEvent`.

## Система и модули

| Событие | Назначение |
| --- | --- |
| `system.initialize` | Системный runtime инициализирован. |
| `module.registered` | Descriptor зарегистрирован; контекст — строковый код. |
| `module.installed` | Установка завершена. |
| `module.updated` | Обновление завершено. |
| `module.enabled`, `module.disabled` | Состояние активности изменено. |
| `module.uninstalling`, `module.uninstalled` | До и после деинсталляции. |

## Публичный HTTP-запрос

| Событие | Назначение |
| --- | --- |
| `frontend.request.received` | Получен публичный запрос. |
| `frontend.runtime.ready` | Конфигурация и публичные модули загружены. |
| `frontend.route.resolved` | Определён маршрут и контекст страницы. |
| `frontend.response.rendering` | Ответ подготовлен перед финальной выдачей. |
| `frontend.response.rendered` | Финальный HTML сформирован. |

## Документы и рубрики

| Событие | Назначение |
| --- | --- |
| `content.document.loading`, `content.document.loaded` | До и после чтения документа. |
| `content.document.saving`, `content.document.saved` | До и после сохранения; `DocumentSaveEvent`. |
| `content.document.created`, `content.document.updated` | Создание/обновление; `DocumentSaveEvent`. |
| `content.document.published`, `content.document.unpublished` | Смена публикации; `DocumentSaveEvent`. |
| `content.document.deleting`, `content.document.deleted` | До и после удаления. |
| `content.document.snapshot_built` | Итоговый JSON-снимок перестроен. |
| `content.rubric.loading`, `content.rubric.loaded` | До и после чтения рубрики. |
| `content.rubric.schema_impact` | Дополняет список зависимостей перед восстановлением схемы рубрики; контекст — массив. |

Фильтр `content.rubric.schema_impact` получает `rubric_id`, `field_ids`, описания
полей в `fields` и массив `items`. Модуль добавляет в `items` записи с ключами
`key`, `title`, `meta`, `details` и возвращает весь контекст. Анализ ничего не
изменяет в БД и не должен выполнять тяжёлые пересчёты: его результат входит в
fingerprint подтверждения восстановления.

Сам движок всегда добавляет в анализ JSON API документов: его чтение и запись
используют alias, ID, тип и структуру значения поля. Для запросов отдельно
проверяются условия, сортировка, шаблонные теги и контракт результата. Из
установленных подсистем учитываются каталоги, варианты товаров и товарные фиды.

## Запросы, шаблоны и блоки

| Событие | Назначение |
| --- | --- |
| `content.query.loading`, `content.query.loaded` | Чтение AVE-запроса. |
| `content.query.rendering`, `content.query.rendered` | Рендер списка/запроса. |
| `content.template.loading`, `content.template.loaded` | Чтение шаблона страницы. |
| `content.template.rendering`, `content.template.rendered` | Выполнение шаблона страницы. |
| `content.block.rendering`, `content.block.rendered` | Совместимый вызов блока через старый тег `block`. |
| `content.sysblock.loading`, `content.sysblock.loaded` | Чтение блока из единого реестра. |
| `content.sysblock.rendering`, `content.sysblock.rendered` | Основной цикл рендера блока. |

## Поля

| Событие | Назначение |
| --- | --- |
| `content.field.registry` | Реестр типов собран. |
| `content.field.normalizing`, `content.field.normalized` | Нормализация значения. |
| `content.field.validating`, `content.field.validated` | Серверная валидация. |
| `content.field.saving`, `content.field.saved` | Запись значения. |
| `content.field.rendering`, `content.field.rendered` | Публичный вывод. |

Точный набор ключей описан в [Хуках полей](fields.md).

## Пользователи

| Событие | Назначение |
| --- | --- |
| `auth.user.registering`, `auth.user.registered` | До и после регистрации публичного пользователя. |
| `auth.user.authenticated` | Успешный вход. |
| `auth.user.logged_out` | Выход. |
| `auth.phone.code_sent` | Код передан SMS-провайдеру; доступны код провайдера и маскированный телефон, но не сам код. |

## Commerce

Commerce-события доступны, когда установлен соответствующий пакет.

| Событие | Назначение |
| --- | --- |
| `commerce.cart.changed` | Состав корзины изменён. |
| `commerce.order.creating`, `commerce.order.created` | До и после создания заказа. |
| `commerce.order.status_changed` | Статус заказа изменён. |
| `commerce.payment.completed` | Платёж подтверждён. |
| `commerce.delivery.quoted` | Получен расчёт доставки. |

## Товарный каталог

| Фильтр | Назначение |
| --- | --- |
| `catalog.product_cards.rendering` | Передаёт место вывода, ID товаров и исходный HTML. Обработчик может вернуть центральные карточки и `handled=true`. |

Контекст фильтра: `context`, `product_ids`, `items`, `html`, `handled`. Товарный
модуль захватывает вывод только для контекста, явно переведённого в режим
центральной карточки. Если хотя бы один документ отсутствует в товарном индексе,
исходный HTML сохраняется.

## Кеш

| Событие | Назначение |
| --- | --- |
| `cache.invalidating` | Перед удалением ключа, тега или всего кеша. |
| `cache.invalidated` | После завершения очистки. |

## Загрузка файлов

Эти хуки используют массив, а не `LifecycleEvent`.

| Событие | Назначение |
| --- | --- |
| `file.upload.validating` | Фильтр до сохранения. Получает `allowed`, `reason`, `temporary_path`, `original_name`, `size`, `destination`, `source`. |
| `file.upload.stored` | Файл сохранён; получает итоговый `path`, исходное имя, размер и источник. |

Ядро всегда отклоняет служебные файлы сервера, неоднозначные имена, исполняемые
и активные веб-форматы, включая SVG. Для известных источников также проверяются
допустимые расширения и сигнатуры содержимого. Модуль может дополнительно
ужесточить правила: для этого
фильтр возвращает весь массив с `allowed=false` и понятным текстом в `reason`.
Вернуть ядром запрещённый формат через hook нельзя.

## Как узнать фактический контекст

Каталог описывает класс контекста и назначение события. Конкретные ключи
`LifecycleEvent::data()` зависят от операции. На тестовом стенде включите
публичный отладчик, откройте вкладку **Хуки** и сопоставьте событие с вкладками
документа, SQL и timeline. Не выводите весь контекст посетителю и не логируйте
секреты авторизации.
## Обращения по товарам

После создания уведомления о поступлении, предзаказа или запроса цены модуль
`products` вызывает `catalog.product.demand.created`. Событие подходит для
подключения CRM, мессенджера или собственного канала уведомлений.

Контекст содержит `type`, `document_id` и `public_user_id`. Контактные данные
намеренно не передаются в публичный каталог hook-ов: модуль с соответствующими
правами читает полную запись через `DemandRepository`.

Личный кабинет расширяется фильтрами `auth.account.links` и
`auth.account.overview`. Первый добавляет ссылки, второй — краткие карточки на
обзорную страницу.
