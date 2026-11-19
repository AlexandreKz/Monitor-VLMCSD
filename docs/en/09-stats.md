# Statistics and reports

## What it is

A summary of the vlmcsd log: totals, activity charts and ready-made reports.

## How it works

1. The panel calculates data from the log.
2. The “Statistics” tab shows totals, tops and charts.
3. On the “Reports” tab choose a report, set a parameter and press “Show”.
4. The result can be exported to XLSX or PDF.

## “Statistics” tab

- **Cards:** “Total devices”, “Total groups”, “Total requests”.
- **By groups** — how many devices are in each group.
- **Top IP addresses** and **Top countries** — the most frequent request sources. The country is determined by IP and taken from the geolocation cache (it can be cleared and refreshed in “Tools → Cache Management”). If there is no data, “No IP data available” or “No country data available” is shown.
- **Overall activity** — a chart of requests by period. The switch is “Days”, “Weeks”, “Months”.
- **By device** — the same chart for a selected device.

## “Reports” tab

Choose a “Report”, enter a parameter if needed and press “Show”. Under the table are the “XLSX” and “PDF” buttons for exporting the result.

| Report | What it shows | Parameter |
|---|---|---|
| IP addresses of a device | All addresses the device connected from | Device name (suggestions come from the log) |
| Devices on an IP | All devices that connected from the selected address | IP address |
| Devices with several IPs | Devices that changed IP: network change, VPN, a laptop in different offices | — |
| IPs with several devices | Addresses with several devices behind them: NAT, an office or a suspicious source | — |
| Top devices by requests | The 50 most active devices | — |
| Long-inactive devices | Devices missing from the log for longer than the chosen period | Period |
| New devices | Devices that first appeared in the log within the chosen period | Period |
| Products | Unique devices and requests per product | — |
| By country | IP addresses of the selected country and the devices from them | Country (from the geolocation cache) |

If nothing is found in the log, “Nothing found in the log” is shown. All reports are also available through the [API](08-api.md).
