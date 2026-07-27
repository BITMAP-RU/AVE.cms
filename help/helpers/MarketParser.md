# MarketParser — клиент внешнего API

`App\Helpers\MarketParser` — обёртка над внешним API MarketParser (цены и отчёты,
Яндекс.Маркет-подобный сервис). Используется как **инстанс** с ключом API и ID
кампании.

```php
use App\Helpers\MarketParser;

$client = new MarketParser($apiKey, $campaignId);
```

---

## Методы

| Метод | Назначение |
| --- | --- |
| `__construct($apiKey, $campaignId)` | Создать клиента. |
| `getCampaigns()` | Список кампаний. |
| `getPriceInfo()` | Информация о ценах. |
| `createReport()` | Создать отчёт (возвращает идентификатор задания). |
| `getReportInfo($reportId)` | Статус/метаданные отчёта. |
| `getReportResults($reportId, $perPage = 10)` | Результаты отчёта (постранично). |
| `getReportsList()` | Список ранее созданных отчётов. |

---

## Рецепт

**Создать отчёт и дождаться результатов:**
```php
$client = new MarketParser($apiKey, $campaignId);

$report = $client->createReport();
$info   = $client->getReportInfo($report['id']);   // проверить готовность
$rows   = $client->getReportResults($report['id'], 50);
```

> Ключ API и ID кампании берите из настроек, не хардкодьте. Это **сетевые**
> вызовы — оборачивайте в `try/catch` и учитывайте таймауты/лимиты API.
