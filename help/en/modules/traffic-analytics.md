# Traffic sources and UTM

Module `traffic_analytics` shows sources of incoming traffic, UTM campaigns
and landing pages. It uses syslog `TrafficAttribution` and does not
installs a second counter on the site.

## What's going on

- source type: UTM campaign, search engine, social network or external
  website;
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`;
- referrer address and domain;
- first landing page;
- number of transitions and anonymized visitor identifier.

The IP address is not stored in the report. Re-entry of one visitor to the same
page during the day is aggregated into an existing record.

## Reports

In the **Modules -> Traffic Sources** section the following are available:

- summary of transitions, visitors, sources and campaigns;
- sections by source, campaign, channel and landing page;
- filter by period, type, source and text;
- list of recent aggregated transitions;
- uploading the current filter to CSV;
- UTM link constructor.

The constructor works in the browser and does not save the entered URLs. Module settings
determine the period of the main report and the period of the dashboard widget.

## Permissions and deletion

- `view_traffic_analytics` - viewing reports and CSV;
- `manage_traffic_analytics` - settings and clearing of the selected period.

When you delete a module, its settings are deleted, but the system transition log
is saved. Log data is only deleted by an explicit command from the interface.
