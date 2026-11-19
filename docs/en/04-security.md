# Security

## What it is

User accounts and permissions, login protection and restriction of panel access by IP.

## How it works

1. An administrator creates users and sets their permissions per section.
2. Login is protected by password requirements, a timeout and a limit on failed attempts.
3. In addition, panel access can be restricted to a list of addresses, and IPs on the main page can be hidden from unauthorized visitors.
4. All changes are written to the “Event log”.

## “Users” tab

### User Management

A table: username, role (a set of permissions per section), created date, actions. The current user is marked “Current”.

- **Add User:** username, password and permissions per section.
- **✎** — edit the user's permissions. **🔑** — change password. **✕** — delete the user.
- Permissions are shown as icons. Most sections have three levels: no access 🚫, view 👁️, edit ✏️. IP whitelist and notifications have two states: ✅ and ❌. What each permission opens is described in [“User permissions”](12-permissions.md).
- A user who has all permissions is a full administrator. Only that user sees the “API” section.
- User changes are written to the “Event log” (passwords and tokens are never logged).

### Password requirements

At least 8 characters, uppercase and lowercase letters, digits and special characters. On the first login as `root`/`root` the panel requires a password change.

### Login timeout

How many minutes without activity before the session ends: from 5 to 1440 (24 hours), default 30. Applied immediately; users who are already logged in continue with the new value.

### IP address privacy

The “Hide IP addresses from unauthorized visitors” setting. When enabled, the main page without login shows blurred placeholders instead of addresses in the log and in the “Unknown devices” list. Real addresses are not sent to the page. The “Suspicious IPs” block stays open. Only an administrator can change this.

### Not suspicious (IP whitelist)

Addresses from the “Not suspicious” list do not appear in the “Suspicious IPs” block on the main page. Add an address with the “➕ Add IP” button (“IP address” field and a comment) or from the main page. Requires the “IP Whitelist: manage” permission.

## “Access & IP” tab

### Panel IP whitelist

The switch “Open the control panel only from the listed addresses”. When enabled, panel pages and login open only from the addresses and networks in the list (and from the server itself). Everyone else gets a 403 error with a page showing their address. The main page with the log and the API are not affected.

- “Your address” is shown. The “📍 Add my address” button adds it to the list so you do not lock yourself out.
- The “Allowed addresses and networks” list: an address or network (IPv4 and IPv6, CIDR format) and a comment.
- The panel will not let you save a setting that would cut off your own access.
- Denials are written to the “Event log” (address, page, browser; no more than once per 10 minutes per address).
- The address is taken from the connection (`REMOTE_ADDR`). Behind a reverse proxy (nginx, Cloudflare) all visitors appear with the proxy's address, so set the restriction on the proxy itself.

### Emergency switch-off

If you locked yourself out, create an empty file `panel_whitelist.off` in the data folder on the server (the `touch` command is shown in the panel) or set the environment variable `VLMC_PANEL_WHITELIST_OFF=1`. The restriction stops working at once. After fixing the list, delete the file manually (the `rm` command is shown too).

## “Firewall” tab

Blocking IPs from the “Suspicious IPs” block is under development: the tab is covered by an “Under development” notice, and in “Information → Status” the Firewall row shows “Under development”.

The code already has: a backend choice (ufw, nftables, iptables), the blocking scope (all ports or only the panel port), a blocked address list, and the “Apply pending” and “Re-apply all” buttons. The web server must not change firewall rules directly, so a helper script and a sudo rule are needed; details and the reason for the pause are in backlog item 30.

## Important

- Do not lock yourself out: before enabling “Open the control panel only from the listed addresses” add your address with “📍 Add my address”. If you still locked yourself out, lift the restriction with the `panel_whitelist.off` file.
- The first login as `root`/`root` requires a password change: do not keep the default password.
- Behind a reverse proxy (nginx, Cloudflare) all visitors appear with the proxy's address: set the IP restriction on the proxy itself.
