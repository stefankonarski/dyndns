# DynDNS Service (Symfony)

Custom DynDNS system for Fritzbox updates using the Hetzner DNS API.

## Stack

- PHP 8.5 (inside Docker container)
- Symfony 8.0
- SQLite (`/app/var/data/app.db`)
- Doctrine ORM + migrations
- Docker (HTTP internal, TLS external via reverse proxy)
- Frontend assets via `npm run build`
- PHPUnit 13 (tests in Docker with PHP 8.5)

## Features

- Public DynDNS endpoint: `GET /update`
- Separate authentication:
  - Admin login (Security/Form login)
  - Fritzbox DynDNS username/password (separate, password stored as hash)
- Idempotent DNS synchronization:
  - A record (IPv4 via `ipaddr`)
  - AAAA record (manual IPv6 from admin configuration)
- Full request logging (without secrets)
- Separate IP history with validity time ranges
- History search:
  - exact timestamp
  - interval
- Locking to prevent parallel updates
- Rate limiting for `/update` and login throttling for admin login

## Important ENV Variables

See `.env.example`:

- `APP_ENV=prod`
- `APP_SECRET=...`
- `DATABASE_URL=sqlite:////app/var/data/app.db`
- `HETZNER_DNS_API_TOKEN=...` (API token from Hetzner Console / Cloud API)
- `TRUSTED_PROXIES=...`

Note: `HETZNER_DNS_API_TOKEN` is read **only** from ENV, never stored in SQLite, and never shown in the UI.
The token must come from the Hetzner Console (Cloud API, DNS permissions), not from the legacy DNS console.

## Local Setup (without Docker)

Local requirement for frontend build: Node.js `>=20.10`.

```bash
composer install
npm ci
npm run build
mkdir -p var/data var/log
php bin/console doctrine:migrations:migrate --no-interaction
```

Create admin user:

```bash
php bin/console app:create-admin-user admin@example.org "VeryStrongPassword123"
```

Change admin password:

```bash
php bin/console app:change-admin-password admin@example.org "NewVeryStrongPassword123"
```

Development server:

```bash
symfony server:start
```

## Docker Start (Production)

```bash
cp .env.example .env.local
docker compose -f docker-compose.yml up -d --build
```

The app is then reachable at:

- Host: `http://127.0.0.1:9099`
- Inside container: `http://<container>:80`

### Persistent Volume

`docker-compose.yml` uses:

- `./var:/app/var`

Important persistent data:

- `./var/data` (SQLite DB)
- `./var/log` (logs)
- `./var/cache` (cache, can be recreated)

`./var/cache` can be deleted if needed; the app will regenerate it.

Backup recommendation:

- regularly back up at least `./var/data` and `./var/log`.

## Migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

The container startup also attempts to run migrations.

## Test Hetzner DNS

```bash
php bin/console app:test-hetzner-dns
```

## Manual DNS Sync

```bash
php bin/console app:sync-dns --force
php bin/console app:sync-dns --ipv4=1.1.1.1
```

## Cleanup Old Logs

```bash
php bin/console app:cleanup-ddns-logs --days=90
```

## Fritzbox DynDNS URL

Default endpoint:

```text
https://ddns.hoody.de/update?username=<username>&password=<pass>&domain=<domain>&ipaddr=<ipaddr>
```

Processed query parameters:

- `username`
- `password`
- `domain`
- `ipaddr`

## Reverse Proxy Example

External TLS termination, internal HTTP:

- Public: `https://ddns.hoody.de`
- Internal upstream target: `http://127.0.0.1:9099`

Set `TRUSTED_PROXIES` correctly (for example proxy IP or network) so `X-Forwarded-*` is handled properly.

## Admin Area

- Login: `/login`
- Dashboard: `/admin`
- Configuration: `/admin/config`
- Logs: `/admin/logs`
- History/Search: `/admin/history`

Configurable:

- Domain from Hetzner zones
- Subdomain (default `home`)
- DynDNS username + DynDNS password
- TTL
- IPv4/IPv6 enabled/disabled
- Manual IPv6
- Delete AAAA record
- Force sync

## Tests

Recommended (always with PHP 8.5 in container):

```bash
docker compose -f docker-compose.test.yml run --build --rm dyndns-test
```

Local (only if your host also uses PHP 8.5):

```bash
php bin/phpunit
```

Covered core cases:

- successful Fritzbox request
- auth failure
- invalid/private IPv4
- domain mismatch
- unchanged/created/updated/delete scenarios
- log writing + secret redaction
- IP history IPv4/IPv6 switching
- search by timestamp/interval
- lock protection for parallel updates

## Security Notes

- No secrets in logs or UI
- Fritzbox password is stored as hash
- API token only via ENV
- Public endpoint protected by rate limiting + lock + validation
- No raw technical errors in the UI

## License

MIT (see `LICENSE`).
