# KMS Monitor — sections and options

A draft for backlog item 68. Panel version: 6.1.0 (plus fixes in `main` after the release). The description is written from the running panel and the code; button and field labels are given as they appear in the interface (English).

## Contents

| File | What is described |
|---|---|
| [00-main-page.md](00-main-page.md) | The main monitoring page (`vlmc.php`) |
| [01-general.md](01-general.md) | General settings: theme, language, log file |
| [02-groups.md](02-groups.md) | Groups |
| [03-devices.md](03-devices.md) | Devices |
| [04-security.md](04-security.md) | Security: users and permissions, access and IP, Firewall |
| [05-tools.md](05-tools.md) | Tools: project, update, log, cache |
| [06-notifications.md](06-notifications.md) | Notifications: channels, rules, events, history |
| [07-integrations.md](07-integrations.md) | Integrations: AD/LDAP, Cloud Storage, Webhook, Multi-server |
| [08-api.md](08-api.md) | API: keys, settings, methods |
| [09-stats.md](09-stats.md) | Statistics and reports |
| [10-audit.md](10-audit.md) | Event log and background tasks |
| [11-info.md](11-info.md) | Information: project structure, data files, status |
| [12-permissions.md](12-permissions.md) | User permissions: what each permission opens |

## How the panel is organised

- The main page `/vlmc.php` shows the KMS server log and is open to everyone (IP addresses can be hidden from unauthorized visitors, see “Security”).
- The control panel `/vlmcconf/` opens after login. The section menu is on the left, the selected section on the right. Which menu items are visible depends on the user's permissions.
- First login: username `root`, password `root`. The panel immediately requires a password change.
- Panel data (settings, users, logs, keys) lives in a data folder outside the web folder: by default `/var/lib/vlmc-monitor/`.
- Interface languages: Russian and English. Themes: 16 options.
