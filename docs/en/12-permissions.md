# User permissions

## What it is

A table of permissions: which permission opens what in the panel.

Permissions are given when adding a user and changed with the ✎ button. Most sections have three levels: 🚫 no access, 👁️ view only, ✏️ full access. IP whitelist and notifications have two states: ✅ and ❌.

## Permissions table

| Permission | What it opens |
|---|---|
| Groups: view / edit | The “Groups” section: the group list; creating, renaming, colours and icons, deleting |
| Devices: view / edit | The “Devices” section: the list and filters; adding, editing, deleting, bulk move |
| Logs: view / edit | The event log, “Statistics and reports”, viewing the log on the main page |
| Users: view / edit | The “Security” section: the user table; creating, permissions, passwords, deleting |
| Information: view | The “Information” section |
| Tools: view / edit | The “Tools” section: viewing; export, reset, clearing the log and caches, update |
| IP Whitelist: manage | The “Not suspicious” list and adding addresses from the main page |
| Integrations: view / edit | Viewing integrations and settings; changing AD settings, syncing users, managing webhooks |
| API: view / edit | The “API” section: documentation; managing keys and settings |
| Notifications | The “Notifications” section: viewing and changing delivery channels and rules |

## Full administrator

A user with all permissions is a full administrator. Only they see the “API” section and can change the “Hide IP addresses from unauthorized visitors” setting. The `root` account is created at installation with the password `root`, and the panel requires changing it on the first login.

## Permissions of domain users

When logging in through Active Directory a new user gets the template from “Integrations → Active Directory / LDAP → Rights of new users”: “View: log and devices”, “View: log, devices, groups, information” or “Work: view and edit devices and groups”. Permissions can be changed manually afterwards.
