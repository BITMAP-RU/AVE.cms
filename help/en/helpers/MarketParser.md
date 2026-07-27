# MarketParser - external API client

`App\Helpers\MarketParser` - wrapper over external API MarketParser (prices and reports,
Yandex.Market-like service). Used as an **instance** with an API key and ID
campaigns.

```php
use App\Helpers\MarketParser;

$client = new MarketParser($apiKey, $campaignId);
```

---

## Methods

| Method | Destination |
| --- | --- |
| `__construct($apiKey, $campaignId)` | Create a client. |
| `getCampaigns()` | List of campaigns. |
| `getPriceInfo()` | Price information. |
| `createReport()` | Create report (returns job ID). |
| `getReportInfo($reportId)` | Report status/metadata. |
| `getReportResults($reportId, $perPage = 10)` | Report results (page by page). |
| `getReportsList()` | List of previously created reports. |

---

## Recipe

**Create a report and wait for the results:**

```php
$client = new MarketParser($apiKey, $campaignId);

$report = $client->createReport();
$info   = $client->getReportInfo($report['id']);   // проверить готовность
$rows   = $client->getReportResults($report['id'], 50);
```

> Take the API key and campaign ID from the settings, do not hardcode it. These are **network**
> calls - wrap in `try/catch` and take into account API timeouts/limits.
