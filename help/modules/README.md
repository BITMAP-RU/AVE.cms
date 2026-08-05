# Модульная система

Расширение может быть только административным или составным пакетом с публичной
и административной частями. Оно декларативно описывает права, маршруты,
миграции, настройки, хуки, типы полей и вклады в интерфейс. Управляемый модуль
можно установить, обновить, выключить, переустановить и удалить через панель
управления AVE.cms.

В этом разделе **AVE.cms** означает сам движок, а «панель управления» — его
административный интерфейс. Имя `adminx` используется как имя каталога по
умолчанию, а также в namespace и технических API панели. Физический каталог
можно переименовать.

Только административный модуль:

```text
<admin-directory>/modules/Notes/
├── module.php
├── Controller.php
├── Model.php
├── migrations/
├── view/
└── assets/
```

Составной пакет, который меняет и публичный сайт:

```text
modules/reviews/
├── app/
│   ├── module.php
│   ├── services.php
│   └── Fields/
└── admin/
    ├── module.php
    ├── Controller.php
    ├── view/
    └── assets/
```

## Как система находит модули

`ModuleManager` загружает встроенные модули панели из каталога
`<admin-directory>/modules`, а
`PackageModuleRuntime` обнаруживает составные пакеты в `modules`. Классы
подключает штатный namespace-loader, затем descriptor регистрирует доступные
контракты в общих реестрах.

После первого обнаружения сериализуемые descriptors сохраняются в защищённом
`storage/runtime/module-registry.json`. Реестр проверяет корневой каталог и
автоматически пересобирает административную секцию после переименования панели.
Manifest с вычисляемым runtime-содержимым объявляет
`'registry' => array('dynamic' => true)` и загружается напрямую.

Управляемый модуль без записи жизненного цикла только обнаруживается. До явной
установки его миграции, маршруты и интерфейс не активны.

В разделе **Модули → Управление** область каждого расширения отмечена цветом:

- `Админка` - код находится только в каталоге панели и не подключается в паблике;
- `Публичный` - пакет предоставляет только runtime публичного сайта;
- `Паблик + админка` - единый пакет содержит `app/` и `admin/`.

Поэтому небольшое число папок в корневом `modules/` нормально: чисто
административные расширения физически находятся в
`<admin-directory>/modules/`, а составные пакеты - в корневом `modules/`.

## Карта раздела

| Страница | О чём |
| --- | --- |
| [module.php — descriptor](module-php.md) | Все основные ключи manifest с примерами. |
| [Файлы модуля](files.md) | `Menu`, `Permissions`, контроллеры, модели, маршруты, Twig и assets. |
| [Операции и состояния](operations.md) | Установка, обновление, включение, переустановка, деинсталляция и удаление файлов. |
| [ZIP-пакеты](packages.md) | Структура архива, ограничения и безопасная установка через панель управления. |
| [Удалённый каталог](repository.md) | Подключение подписанного источника, публикация и установка модулей по HTTPS. |
| [Миграции](migrations.md) | Плейсхолдеры таблиц, ledger, идемпотентность и удаление схемы. |
| [Вклады в UI](contributions.md) | Обзор меню, действий шапки, виджетов дашборда и уведомлений. |
| [Локализация](localization.md) | XML-словари модуля, Twig, JavaScript и перевод пунктов меню. |
| [Виджеты дашборда](dashboard-widgets.md) | Descriptor, provider, Twig, сетка, порядок и видимость. |
| [Действия в шапке](header-actions.md) | Кнопка, dropdown, модальное окно, права и Ajax. |
| [Уведомления](notifications.md) | Элементы общего колокольчика, счётчики и производительность provider. |
| [Встроенная справка](help-viewer.md) | Как модуль читает каталог `help/`, строит навигацию и полнотекстовый поиск. |
| [Готовность сайта](site-readiness.md) | Профили запуска, отчёты, ручной чек-лист и расширение проверок модулем. |
| [Взаимодействия](interactions.md) | Общий runtime оценок, реакций и голосов, каналы, API и hooks. |
| [Антиспам](antispam.md) | Профили защиты форм, challenge, CAPTCHA и подключение своих проверок. |
| [Рейтинги](ratings.md) | Виджет оценки объектов, шаблонные теги и API голосов. |
| [Комментарии](comments.md) | Обсуждения, премодерация, три шаблона, API и антиспам. |
| [Опросы](polls.md) | Одиночные и множественные голосования, архив, теги и API. |
| [Галереи](galleries.md) | Медиаколлекции, сетка, слайдер, миниатюры и шаблонные теги. |
| [Поиск по сайту](search.md) | Индекс, области, вывод через запросы, JSON/HTML API, живой поиск и хуки. |
| [Подбор товара](quiz.md) | Пошаговый подбор по фильтрам каталога и шаблон публичной страницы. |
| [Публичные виджеты](public-widgets.md) | Курсы валют, подписка и Twig-override активной темы. |
| [Баннеры](banners.md) | Рекламные места, стратегии ротации, расписание, шаблоны и статистика. |
| [Вопросы и ответы](faq.md) | Коллекции FAQ, rich-text ответы, шаблоны, JSON API и Schema.org. |
| [Похожие материалы](related.md) | Релевантность, кольцевой вывод, ручные связи, шаблоны, запросы и API. |
| [Контактные формы](contacts.md) | Формы, поля, публичная вставка, история обращений и ответы. |
| [Корзина, доставка и оплата](commerce.md) | Публичные страницы корзины, Twig-шаблоны, сервисы доставки и платёжные шлюзы. |
| [RSS-каналы](rss.md) | Публичные адреса каналов, рубрики, поля и ограничения выдачи. |
| [Вход через VK ID и Яндекс](oauth-providers.md) | Callback URL, секреты, регистрация и привязка аккаунтов. |
| [Вход по SMS через SMSC](smsc-auth.md) | API-ключ, одноразовые коды, ограничения и телефонные аккаунты. |
| [Личные инструменты](personal-tools.md) | Todo, канбан, заметки и напоминания. |
| [Ошибки 404](not-found.md) | Журнал несуществующих URL и создание редиректов. |
| [SEO-аудит](seo-audit.md) | Правила проверки документов и безопасное заполнение метаданных. |
| [Производительность](benchmark.md) | Синтетическая проверка БД, файловой системы и CPU. |
| [Безопасность файлов](file-security.md) | Эталон, профили проверки, карантин, права и уведомления. |
| [Демо-сайт](demo-site.md) | Обратимое добавление темы и демонстрационного контента. |
| [Импорт документов](document-import.md) | Профили CSV, Excel и XML, сопоставление полей и повторное обновление документов. |
| [Миграция из старой AVE.cms](legacy-migration.md) | Preflight, пакетный перенос с сохранением ID, сверка и импорт uploads. |
| [Пакеты контента](content-packages.md) | JSON-перенос рубрик, полей, запросов, документов и блоков между сайтами. |
| [Планировщик](scheduler.md) | Реестр фоновых задач, cron-расписание, HTTP-тик и журнал запусков. |
| [Мониторинг здоровья](system-health.md) | Диск, MySQL, фоновые задачи, внешние сервисы и история снимков. |
| [Отзывы](reviews.md) | Отзывы и рейтинг объектов, премодерация и ответ компании. |
| [Всплывающие окна](popups.md) | Кампании, A/B-варианты, сценарии показа и лиды. |
| [Обмен с 1С](commerceml.md) | Профили CommerceML, сопоставление полей и журнал импортов. |
| [Источники трафика](traffic-analytics.md) | UTM-кампании, внешние переходы, посадочные страницы и CSV. |
| [Поисковые запросы](search-analytics.md) | Спрос посетителей, нулевая выдача и рабочие заметки. |
| [A/B-тесты](experiments.md) | Варианты блоков и элементов, стабильное распределение и конверсии. |
| [Надёжные исходящие события](reliable-events.md) | Webhook-очередь, подписи, повторы и журнал доставок. |
| [Автоматический прогрев кеша](cache-warmup.md) | Пакетный прогрев опубликованных страниц после сброса кеша. |
| [Кросспостинг](crossposting.md) | Telegram, VK, Дзен и собственные шлюзы: шаблоны, расписание, очередь и защита от дублей. |
| [Сравнение систем](environment-comparison.md) | Ядро, модули, миграции, схема БД, настройки, темы и шаблоны без выгрузки секретов. |
| [Пошагово: свой модуль](tutorial.md) | Мини-модуль с нуля до рабочего экрана. |

## Устанавливаемые модули AVE.cms

Таблица ниже покрывает штатные нетоварные модули. Товарные пакеты, импорт цен,
виджеты и «Операции с документами» сознательно не входят в этот список.

| Код | Область | Основные права | Руководство |
| --- | --- | --- | --- |
| `benchmark` | Панель | `view_benchmark`, `manage_benchmark` | [Производительность](benchmark.md) |
| `interactions` | Паблик + панель | `view_interactions`, `manage_interactions` | [Взаимодействия](interactions.md) |
| `reviews` | Паблик + панель | `view_reviews`, `manage_reviews` | [Отзывы](reviews.md) |
| `antispam` | Паблик + панель | `view_antispam`, `manage_antispam` | [Антиспам](antispam.md) |
| `banners` | Паблик + панель | `view_banners`, `manage_banners` | [Баннеры](banners.md) |
| `comments` | Паблик + панель | `view_comments`, `manage_comments` | [Комментарии](comments.md) |
| `contacts` | Паблик + панель | `view_contacts`, `manage_contacts`, `reply_contacts` | [Контактные формы](contacts.md) |
| `commerce` | Паблик + панель | `view_orders`, `manage_orders`, `refund_orders` | [Корзина, доставка и оплата](commerce.md) |
| `demo_site` | Паблик + панель | `view_demo_site`, `manage_demo_site` | [Демо-сайт](demo-site.md) |
| `document_import` | Панель | `view_document_import`, `run_document_import`, `manage_document_import` | [Импорт документов](document-import.md) |
| `legacy_migration` | Панель | `view_legacy_migration`, `run_legacy_migration`, `manage_legacy_migration` | [Миграция AVE.cms](legacy-migration.md) |
| `content_packages` | Панель | `view_content_packages`, `export_content_packages`, `import_content_packages` | [Пакеты контента](content-packages.md) |
| `faq` | Паблик + панель | `view_faq`, `manage_faq` | [Вопросы и ответы](faq.md) |
| `galleries` | Паблик + панель | `view_galleries`, `manage_galleries` | [Галереи](galleries.md) |
| `polls` | Паблик + панель | `view_polls`, `manage_polls` | [Опросы](polls.md) |
| `ratings` | Паблик + панель | `view_ratings`, `manage_ratings` | [Рейтинги](ratings.md) |
| `related` | Паблик + панель | `view_related`, `manage_related` | [Похожие материалы](related.md) |
| `file_security` | Паблик + панель | `view_file_security`, `run_file_security`, `manage_file_security` | [Безопасность файлов](file-security.md) |
| `help` | Панель | `view_help` | [Встроенная справка](help-viewer.md) |
| `kanban` | Панель | `view_kanban`, `manage_kanban` | [Личные инструменты](personal-tools.md#канбан) |
| `notes` | Панель | `view_notes`, `manage_notes` | [Личные инструменты](personal-tools.md#заметки) |
| `notfound` | Панель | `view_notfound`, `manage_notfound` | [Ошибки 404](not-found.md) |
| `reminders` | Панель | `view_reminders`, `manage_reminders` | [Личные инструменты](personal-tools.md#напоминания) |
| `rss` | Паблик + панель | `view_rss`, `manage_rss` | [RSS-каналы](rss.md) |
| `search` | Паблик + панель | `view_search`, `manage_search` | [Поиск по сайту](search.md) |
| `seoaudit` | Панель | `view_seoaudit`, `manage_seoaudit` | [SEO-аудит](seo-audit.md) |
| `site_readiness` | Панель | `view_site_readiness`, `run_site_readiness`, `manage_site_readiness` | [Готовность сайта](site-readiness.md) |
| `scheduler` | Паблик + панель | `view_scheduler`, `run_scheduler`, `manage_scheduler` | [Планировщик](scheduler.md) |
| `system_health` | Панель | `view_system_health`, `run_system_health`, `manage_system_health` | [Мониторинг здоровья](system-health.md) |
| `popups` | Паблик + панель | `view_popups`, `manage_popups`, `manage_popup_code` | [Всплывающие окна](popups.md) |
| `commerceml` | Паблик + панель | `view_commerceml`, `manage_commerceml` | [Обмен с 1С](commerceml.md) |
| `traffic_analytics` | Паблик + панель | `view_traffic_analytics`, `manage_traffic_analytics` | [Источники трафика](traffic-analytics.md) |
| `search_analytics` | Паблик + панель | `view_search_analytics`, `manage_search_analytics` | [Поисковые запросы](search-analytics.md) |
| `experiments` | Паблик + панель | `view_experiments`, `manage_experiments`, `manage_experiment_code` | [A/B-тесты](experiments.md) |
| `reliable_events` | Паблик + панель | `view_reliable_events`, `manage_reliable_events` | [Исходящие события](reliable-events.md) |
| `cache_warmup` | Паблик + панель | `view_cache_warmup`, `manage_cache_warmup` | [Прогрев кеша](cache-warmup.md) |
| `crossposting` | Паблик + панель | `view_crossposting`, `manage_crossposting` | [Кросспостинг](crossposting.md) |
| `environment_compare` | Панель | `view_environment_compare`, `run_environment_compare` | [Сравнение систем](environment-comparison.md) |
| `todo` | Панель | `view_todos`, `manage_todos` | [Личные инструменты](personal-tools.md#todo) |
| `vk_oauth` | Паблик + панель | `view_vk_oauth`, `manage_vk_oauth` | [Вход через VK ID](oauth-providers.md#vk-id) |
| `yandex_oauth` | Паблик + панель | `view_yandex_oauth`, `manage_yandex_oauth` | [Вход через Яндекс](oauth-providers.md#яндекс) |
| `smsc_auth` | Паблик + панель | `view_smsc_auth`, `manage_smsc_auth` | [Вход по SMS через SMSC](smsc-auth.md) |

## Ключевые принципы

- Только панель управления — `<admin-directory>/modules/<Name>`, публичная часть
  и панель — один пакет в `modules/<code>`.
- Права, меню, маршруты, миграции, хуки и настройки объявляются декларативно.
- Managed-модуль требует явной установки; наличие папки не означает активацию.
- Отключение сохраняет данные и общие контракты, но убирает runtime-вклады.
- Twig-namespace административного модуля совпадает с его `code`.
- Модуль не должен требовать CLI для установки или повседневной production-работы.
- Браузерные подтверждения выполняются через технический API
  `Adminx.Confirm`, ответы Ajax — через общий контракт панели управления.

Перед установкой стороннего пакета проверьте его исходный код и сделайте backup:
ZIP-модуль является исполняемым PHP-кодом и может содержать миграции БД.
