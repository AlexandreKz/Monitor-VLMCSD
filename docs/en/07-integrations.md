# Integrations

## What it is

Connections between the panel and external systems: a user directory, backup storages, webhooks and other panels.

## How it works

1. Choose the tab of the integration you need and enable it.
2. Enter the connection parameters and press the test.
3. An enabled integration works in the background; its state is visible in “Information → Status”.

## Active Directory / LDAP

Login of domain users and import of accounts from Active Directory, OpenLDAP and other LDAP servers. The PHP `ldap` extension is required (`sudo apt install php8.5-ldap` and an Apache restart). Without it a warning is shown at the top of the tab, and “Information → Status” shows “Warning”.

The tab has three parts: “Connection and sign-in”, “Users”, “Log”.

### Connection

- **Enable directory integration.**
- **LDAP Server** — the full name or IP of the domain controller.
- **Security and port** — no encryption, StartTLS or LDAPS, and the port.
- **Verify the server certificate.**
- **Base DN** — the root for user search.
- **Service account** and its password — a read-only account for the directory.
- **Backup accounts** (up to 5) — tried in order if the main one is rejected.
- **Domain (UPN), optional** — needed when no service account is set or users log in as name@domain.
- Buttons “Save” and “Test connection”. The test runs for each service account separately: ✅ or ❌ next to each account, details in the log.

### Login and users

- **Allow login of domain users.** The local panel database is checked first, then the directory. A domain user cannot take over a local account (for example `root`).
- **Create the user at first login.** When off, only imported users can log in.
- **Rights of new users** — a permission template (“View: log and devices”, “View: log, devices, groups, information”, “Work: view and edit devices and groups”). Permissions can be changed later in “Security”.
- **Login attribute.**
- **Required group.** The “Select groups…” button opens a window with directory groups (search by name, or enter a name or DN by hand). Members of any selected group may sign in. If nothing is selected, any directory user is allowed.
- **Extra filter** — an LDAP search condition, for example `(department=IT)`.
- **Timeout** in seconds.

### Directory users

Search by name, login or email, with the “Import selected” and “Refresh data” buttons. The table shows login, name, email, groups and the “In panel” mark.

### Log

A log of checks and sync: time, operation, result, details (the reason for an error). Every 12 hours a background task checks the connection and updates the “Connected” or “Not connected” state in “Information → Status”.

## Cloud Storage

External storage for backups: up to 10 storages of types SFTP, FTP, FTPS, WebDAV and S3.

Fields: name, storage type, server (“Server URL” for WebDAV and S3), port, user / access key, password / secret key, folder, bucket and region (for S3), what to back up (settings, users, KMS log, event log), schedule, time, number of copies to keep, enabled.

The password is stored encrypted. There is a connection test.

Currently the settings and the connection test work. Automatic backup creation and upload on schedule are not ready yet (backlog item 31). FTP and FTPS need the PHP `ftp` extension.

## Webhook

The panel sends a POST request to your address on the selected events. Up to 20 addresses.

Fields: name, URL (http/https), format (JSON, Slack or plain text), secret for signing (optional), timeout in seconds, events, enabled.

- If a secret is set, the request body is signed (HMAC-SHA256) and the signature is sent in the `X-KMS-Signature` header.
- The event list is the same as in “Notifications” (44 events).
- The table shows the time and result of the last delivery. There is a test delivery.
- To send to Bitrix24 use the “Bitrix24” notification channel.

## Multi-server

The tab is covered by an “Under development” notice. It is designed for adding other KMS Monitor panels: on adding, the panel connects to the given server and signs in with your login and password (fields: name, panel address, login, password), and the table shows the version and the check time. This is a draft; the section will appear in one of the next versions.

## Important

- Cloud Storage does not create backups automatically yet: only settings and the connection test work.
- “Multi-server” and “Firewall” are under development: the tabs are covered by a notice.
- AD/LDAP needs the PHP `ldap` extension.
