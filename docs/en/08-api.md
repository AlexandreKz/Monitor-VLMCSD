# API

🔒 Access: full administrator only.

## What it is

A read-only REST API: panel data can be pulled from Zabbix, Grafana and your own scripts.

API address: `https://your-server/vlmcconf/api.php/v1`. Use HTTPS only: the key is sent in clear text.

## How it works

1. Enable the API on the “Settings” tab.
2. Create a key on the “Keys” tab and save it: it is shown once.
3. Pass the key in a request header and get data as JSON.
4. The “Documentation” tab describes all methods with examples.

## “Keys” tab

A key table: name, key (the beginning is shown), access scope, allowed addresses, expiry, last used, status. The “New key” button opens a window:

- **Name** (for example Zabbix, Grafana).
- **Access** — scopes: Status, Devices, Groups, Log, Statistics, Reports, Security, Event journal.
- **Allowed addresses** — IP addresses and networks (CIDR) from which the key may be used; empty means no restriction.
- **Expires** — an expiry date (calendar).

After creation the key (`kms_…`) is shown once and cannot be recovered later: the panel stores only a hash. Keys can be disabled, enabled and deleted. Creating, changing and deleting are written to the “Event log”.

## “Settings” tab

- **Enable the API** — the main switch. Applied immediately.
- **Rate limit** — requests per minute per key (default 60, 0 — unlimited). When exceeded, the answer is 429 with a `Retry-After` header; the remainder is visible in the `X-RateLimit-*` headers.
- **Allowed origins (CORS)** — one address per line or `*`. Needed only for requests from a browser; closed by default.

Brute-force protection: 20 wrong keys per minute from one address block that address. The panel IP restriction does not apply to the API: keys have their own address lists.

## How to use

Pass the key in the `Authorization: Bearer kms_…` or `X-API-Key` header. Example:

```text
curl -H "Authorization: Bearer kms_…" https://your-server/vlmcconf/api.php/v1/status
```

Only GET requests are supported.

## “Documentation” tab

A “Search and filters” block (search by methods, filters by method type and by access scope, an `openapi.json` button for Swagger Editor and Postman) and a “Methods” block (description, a request and response example with a copy button).

### Methods (22)

| Scope | Methods |
|---|---|
| Status | `/v1/status` (version, log state), `/v1/health` (the result of the “Status” tab, handy for monitoring) |
| Devices | `/v1/devices`, `/v1/devices/{name}`, `/v1/devices/{name}/ips` |
| Groups | `/v1/groups`, `/v1/groups/{name}` |
| Log | `/v1/log` (filters device, since, limit) |
| Statistics | `/v1/stats` |
| Security | `/v1/unknown-devices`, `/v1/whitelist-ips` |
| Event journal | `/v1/audit` |
| Reports | `/v1/reports/device-ips`, `ip-devices`, `multi-ip-devices`, `shared-ips`, `top-devices`, `inactive-devices`, `new-devices`, `products`, `countries`, `by-country` |

The description is open without a key: `/vlmcconf/api.php/v1/openapi.json`.

## Important

- The key is shown once and cannot be recovered later: save it at once.
- Use the API over HTTPS only: the key is sent in clear text.
- Do not give keys more access scopes than needed and restrict them by IP.
