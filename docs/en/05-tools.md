# Tools

## What it is

Panel maintenance: project export, update, clearing the log and caches.

## How it works

1. The “Project Management” tab exports an archive and resets settings.
2. The “Update” tab checks GitHub and installs a new version.
3. The “Log Management” and “Cache Management” tabs clear data.
4. Dangerous actions ask for confirmation and are written to the “Event log”.

## Project Management

### Export Project

Download a project archive. Three variants:

- **Clean project** — file structure only, no logs and no settings.
- **With settings** — with the config and the user list, no logs.
- **Full backup** — everything, including log files.

An export is written to the “Event log” and can trigger the “Project exported (panel backup)” notification. The archive with settings contains password hashes: keep it secret.

### Reset settings

Returns settings to defaults (theme, language, groups, devices). The action cannot be undone, so the panel asks for confirmation. The “Panel configuration reset” event has high importance in notifications.

## Update

- **Info block:** repository (`AlexandreKz/Monitor-VLMCSD`), branch, current version, time of the last check, status.
- **Buttons:** “Check for updates”, “Update project” (opens a confirmation window with the file list, then “Yes, update”), “Check GitHub” (checks access and the certificate).
- **Update type** (two channels):
  - “Release (stable)” — files from the tag of the latest published release;
  - “Early access (by SHA)” — files from the `main` branch; new and changed files count as an update.
- **GitHub token (optional):** raises the GitHub request limit from 60 to 5000 per hour. Stored encrypted.
- **Check for updates automatically (both channels):** the “Enable automatic check” checkbox and an interval in hours.
- **Automatic update** — under development (backlog item 62).
- **How installation goes:** files are downloaded to a temporary folder and checked against the repository by SHA, then a backup of the files being replaced is made (in `backups/update_…` of the data folder) and the files are replaced. If something goes wrong, a rollback is performed. The `pic/` and `screen/` folders, settings and panel data are not updated, and files that are not in the repository are not deleted.
- **Scheduled check:** `update_check.php` exits with code 0 (no updates), 10 (an update is available) or 1 (check error). A cron line for it: `0 */6 * * * www-data php /var/www/html/vlmcconf/update_check.php >/dev/null 2>&1`.
- If you made your own edits to the panel files, early access will offer to replace them with the repository version: push your edits to the repository first.
- The **console** at the bottom shows the progress of checks and installation.
- If certificate verification fails, the section shows the “How to enable certificate verification” hint.

The events “New panel version available”, “Panel updated” and “Panel update failed” can be subscribed to in “Notifications”.

## Log Management

- Info block: log file, size, status, date of the first event.
- **Clear by date:** the “From” and “To” fields and the “Clear” button.
- **Full clear:** the “Clear all” checkbox, the “Log Backup” checkbox (save a copy first) and the “Full clear” button.
- Clearing the log cannot be undone, so make a backup first. The action is written to the “Event log”; there is a notification event “KMS server log cleared”.

## Cache Management

- **GeoIP Cache:** number of files and cache size; buttons “Clear Cache”, “Refresh Cache” (re-request data for all IPs from the log; may take a while, the window warns about it) and “Check” (cache statistics).
- **Emoji Cache:** cache status, “Clear Cache” (delete the file) and “Refresh Cache” (download the current emoji list from the internet).

## Important

- Resetting settings and clearing the log cannot be undone: make a “Full backup” first.
- The “With settings” archive contains password hashes: keep it secret.
- Make a backup before updating: if something fails the panel rolls the files back, but it is better to save the data in advance.
