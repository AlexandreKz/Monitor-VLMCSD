# Event log

## What it is

A log of user actions and the state of the panel's background tasks.

## How it works

1. The panel records logins, settings changes and other actions.
2. On the “General” tab records can be searched, filtered and exported to CSV.
3. Old records are removed after the retention period.
4. The “Background” tab shows how periodic tasks are running.

## “General” tab

A table: Time, User, IP, Action, Details, Result.

- **Filters:** “Search” (by user, IP, action, description), “User”, “Action”, “Result”, “Period” (today, 7 days, 30 days, all time or your own dates), reset.
- **Result:** “Success”, “Error”, “Denied”.
- **Refresh:** the 🔄 button and the “Auto-refresh” checkbox. “Records per page” changes the page size.
- **Export CSV** — export of the found records.
- At the bottom: “Records found” and page navigation.

What goes into the log: login, logout and failed login attempts, password, theme and language changes, actions with groups, devices, users and permissions, configuration reset, clearing of the log and caches, project export, changes to integrations and API keys, panel updates, IP whitelist denials. Passwords and tokens are never logged.

### Retention period

How many days to keep records (default 90) and the “Save” button. Old records are removed automatically by a background task. Below the setting you can see the number of records, the size and the retention.

### Clear log

The “Clear log” button opens a confirmation window. Clearing cannot be undone; the clearing itself is written to the new log.

## “Background” tab

### Background tasks

Tasks run automatically: when the panel is opened (only overdue ones, at most once a minute) and on the OS schedule. The table shows: Task, Period, Last run, Result, Next.

| Task | Period | What it does |
|---|---|---|
| Update check | as set in “Tools → Update” | looks for a new panel version |
| AD/LDAP status | 12 hours | checks the connection to the directory |
| Notification watch | 15 minutes | new devices in the log, suspicious IPs, vlmcsd stopped and so on |
| Log pruning | once a day | removes records older than the retention period |

### Run on OS schedule

Shows the `cron` command (the `/etc/cron.d/kms-monitor` file, running `cron.php` every 15 minutes). It can be copied or written with the button in the panel. The panel writes the same file as the installer and replaces it: this is fine, because `cron.php` runs the update check itself.

Without `cron` tasks run only when somebody opens the panel. In “Information → Status” the “OS scheduler (cron)” row then shows “Warning”.

### Run history

“Time”, “Task”, “Result”, “From” (panel or cron), “ms” (duration).

## Important

- Clearing the log cannot be undone.
- Without the OS scheduler job, background tasks run only while the panel is open.
