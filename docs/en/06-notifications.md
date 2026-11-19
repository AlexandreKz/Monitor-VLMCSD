# Notifications

🔒 Access: the “Notifications: edit” permission or a full administrator. For other users the menu item is greyed out.

## What it is

Messages about events in the panel and on the KMS server by email, Telegram and Bitrix24.

## How it works

1. An event occurs in the panel or is found by a background check.
2. A rule decides which events to report, where and how often.
3. A channel knows how to deliver the message (email, Telegram, Bitrix24).
4. The result of every delivery is visible on the “History” tab.

## “Channels” tab

A channel is a delivery method. The “Create channel” button opens a window. The table shows name, type, recipient, status (“Active” or disabled), and test and delete buttons.

The channel type is chosen in the window:

- **Email (SMTP):** “SMTP server”, “Port”, “Encryption” (STARTTLS, SSL/TLS or none), “Login”, “Password”, “Sender”, “Recipients” (several addresses separated by commas).
- **Telegram:** “Bot token” and “Chat ID” (numeric chat ID or @channel; the bot must be added to the chat). The optional **“Proxy (optional)”** field is for places where Telegram is blocked: for example `socks5h://127.0.0.1:40000` (`socks5://`, `socks5h://`, `http://` and `https://` are accepted). The proxy applies only to Telegram; all other panel requests go directly. The PHP curl extension is required for a proxy. Setting up WARP is described in a separate guide.
- **Bitrix24:** “Incoming webhook” (an address from Bitrix24 → Developer resources → Other → Incoming webhook, `https://` only) and “Recipient” (a user ID or a chat like `chat123`).
- The “Channel enabled” checkbox.
- The “Test” button sends a test message; the result (success or the error text) is visible in the window and in the history.

Passwords, tokens and webhooks are stored encrypted (the `notify.key` key in the data folder).

## “Rules” tab

A rule answers “what about”, “where to” and “how often”. The “Create rule” button opens a window. The table shows name, events, channels, frequency, quiet hours, status.

In the rule window:

- **Name.**
- **Events.** A list by group (groups collapse; “Security and users” is expanded by default). There is a “Search events” field next to the “Events” heading. Some events have thresholds (for example the number of failed logins and in how many minutes).
- **Delivery channels** — one or more (channels are created on the “Channels” tab).
- **Scope** — “Groups” and “Devices”: an optional filter for events related to groups and devices. If nothing is selected, the rule applies to all groups and devices.
- **Thresholds** — settings for events with numeric conditions.
- **Interval between notifications, min.** At most one notification per this period for one rule and event; 0 — unlimited. Messages suppressed during the period are counted and added to the next one as “(+N)”.
- **Quiet hours** — “from” and “to” fields: no messages are sent in this period.
- **Rule is active** — a switch to stop a rule without deleting it.

By default the panel offers 13 ready-made rules (disabled, no channels). Deleted ready-made rules do not come back.

## “History” tab

A log of deliveries: time, rule, event, channels, result (success or the reason for the error). Test messages also appear in the history.

## “Templates” tab

A “Section under development” placeholder. The tab is left on purpose as a reminder of future work (backlog item 69).

## Events

44 events in six groups. Importance (“high”, “medium”, “low”) helps to pick the main ones.

### Security and users

| Event | Importance |
|---|---|
| Series of failed logins | high |
| Access denied | medium |
| User added | medium |
| User deleted | high |
| User or permissions changed | medium |
| API key created | medium |
| API key deleted | medium |
| Panel IP restriction changed | high |
| User password changed | medium |
| Successful panel sign-in | low |
| Firewall changed (IP block, settings) | high |
| Allowed IP list changed | medium |
| Panel settings changed | medium |

### Panel changes

| Event | Importance |
|---|---|
| Device added | low |
| Devices moved to a group | low |
| Group renamed | low |
| Group deleted | medium |
| Device deleted | medium |
| Panel configuration reset | high |
| Group created | low |
| Device edited | low |
| KMS log path changed | medium |
| Project exported (panel backup) | high |
| KMS server log cleared | medium |
| Event log cleared | high |

### Updates

| Event | Importance |
|---|---|
| New panel version available | low |
| Panel updated | low |
| Panel update failed | medium |

### Devices

| Event | Importance |
|---|---|
| New device in the log | low |
| Device not seen for a long time | low |
| Device with many IPs | medium |
| Many devices from one IP | medium |
| Request from a new country | medium |

### Service and system

| Event | Importance |
|---|---|
| KMS service stopped | high |
| KMS service is running again | low |
| Log not updated for a long time | medium |
| Log is too big | low |
| Low disk space for panel data | medium |
| Background task failed | medium |

### Integrations

| Event | Importance |
|---|---|
| AD/LDAP unavailable | high |
| AD/LDAP available again | low |
| AD/LDAP service account rejected | medium |
| Integration changed (webhook, cloud, server) | medium |
| AD/LDAP settings changed | medium |

## Where events come from

- **The event log.** Most events relate to user actions: login, changes to settings, groups, devices, API keys, integrations.
- **The `notify_watch` background check** (every 5 minutes): new devices, devices not seen for a long time, several IPs for one device, several devices from one IP, a request from a new country, KMS service stopped or running again, log not updated or too big, low disk space. It is enabled only if there is a rule for such events.
- **The update check:** “New panel version available” fires once per version, separately for the release and early access channels.
- **Background task failures** and AD/LDAP events.
- The same events can be sent to external systems through Webhook (the “Integrations” tab).

For the background check to run without opening the panel, set up an OS scheduler job (see “Event log → Background”).

## Important

- The encryption key `notify.key` lives in the data folder. Without it saved passwords, tokens and webhooks cannot be decrypted: keep it together with data backups.
- Background events (“New device in the log” and others) work only with the OS scheduler job or while the panel is open (see “Event log → Background”).
