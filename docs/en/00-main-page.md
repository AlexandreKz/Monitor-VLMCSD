# Main page

🌐 Access: opens without login at `/vlmc.php`.

## What it is

The monitoring page: who contacted the KMS server and when, which products were activated and which addresses look suspicious.

## How it works

1. The vlmcsd server writes every request to its log.
2. The panel reads the log and refreshes the page every few seconds.
3. Lists of products, unknown devices and suspicious IPs are built from the log.
4. A device from the unknown list can be added to the panel; an address from the suspicious list can be put on the whitelist.

## What is on the page

- **Request log.** vlmcsd log lines: time, device, IP address, product. Updates automatically.
- **Search and filters.**
  - The “Search text...” field and the “Search” and reset buttons.
  - Record type filter: “All events”, “Only connections”, “Only requests”.
  - The “All groups” filter shows only the devices of the selected group.
- **Products.** Microsoft products that were activated, with the number of devices and requests.
- **Server status.** An indicator based on server uptime: “Recently started” (under a day), “Active” (from a day to 30 days), “Stable” (over 30 days), “No data”. The tooltip shows the uptime.
- **Suspicious IPs.** Addresses used by devices that are not added to the panel, or addresses with unusual behaviour. Each row shows the country, number of connections, time span and actions:
  - copy the IP;
  - address information (country, region, city, provider, organization, time zone);
  - add the address to the whitelist (it stops appearing in this block; requires the “IP Whitelist: manage” permission);
  - block the address in the firewall (the 🚫 button appears only when the firewall is connected; the feature is currently under development).
- **Unknown devices.** Devices that contact the server but are not added to the panel: name, number of requests, last IP, first and last request. The ➕ button opens the “Add device” window: name, group and comment. Without logging in, a 🔒 lock is shown instead of the button.
- **Language switch** (Russian and English) and the “Settings” icon that leads to the control panel.

## Hiding IP addresses

If “Hide IP addresses from unauthorized visitors” is enabled in “Security → Users”, addresses in the log and in the unknown devices list are replaced with a blurred placeholder for visitors who are not logged in. Real addresses are not sent to the page at all. The “Suspicious IPs” block stays open.

## Geolocation

The country and address data come from external services and are cached in `vlmcconf/cache/`. Clearing and refreshing the cache is in “Tools → Cache Management”.
