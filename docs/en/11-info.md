# Information

## What it is

A reference about the server and the project: file structure, data files and a component health check.

## Server

OS, kernel, host, server IP, web server, PHP version, uptime, server time.

## Project structure

The project file tree: name, type, size, date. At the top is the line “Updated at …: files N, changed N” and a refresh button. Colour hints show feature readiness: 95–100% stable, 85–94% working, below 85% under development.

## Data files

The data folder and a list of service files: name, purpose, full path, status “not created” or size. Among them:

- `vlmcconf_config.json` — panel settings;
- `users.json` — accounts and permissions;
- `audit.log` — the event log;
- `notify.json`, `notify.key`, `notify_history.jsonl` — notification channels and rules, the secret encryption key, delivery history;
- `update_state.json`, `github_token.json`, `update.lock` — update state, the GitHub token (encrypted), a lock;
- `config_changes.log`, `users_changes.log` — change traces;
- `backups/` — backups of settings and users.

The `notify.key` key is needed to decrypt secrets: do not lose it when moving data.

### Migration, rollback and backups

- **Migration.** On the first opening of the panel after the data folder is created, `users.json` and `vlmcconf_config.json` are copied, verified and removed from the old place (`vlmcconf/`). A “before migration” copy stays in the `backups/` subfolder of the new folder.
- **Update and rollback.** A panel update does not touch the data: it lives outside the project folder. Do not go back to a panel version that does not know about the new folder: it will not find `users.json` in the old place and will create a new `root` user. For a backup, save the whole data folder.
- **Copies before changes.** Before each change to the settings and the user list the panel saves a copy to `backups/config/` and `backups/users/` (the last 50 of each). If a file is damaged, the panel restores it from the latest copy and keeps the damaged one next to it (`*.broken`). Every save is written to `config_changes.log` and `users_changes.log` (the SHRINK mark means the number of entries decreased).
- If `users.json` is missing but copies exist, users are restored from a copy. To deliberately reset the `root` password, delete `users.json` and the `backups/users` folder.
- The `vlmcconf/cache` folder still stays in its old place.

## Status

The “Check again” button. A table: Component, Status, Details, Parameters. Statuses: “Working”, “Warning”, “Error”, “Disabled”.

| Group | Components |
|---|---|
| Core | KMS server (vlmcsd, port 1688), vlmcsd log, data folder, configuration and users, PHP and extensions |
| Integrations | Active Directory / LDAP, Webhook, Cloud Storage, API, Multi-server, Notifications |
| Security | Panel access restriction, Firewall (“Under development”) |
| Maintenance | OS scheduler (cron), background tasks, updates, event log |

If vlmcsd does not answer on port 1688, the “KMS server” row shows “Error” and the reason.
