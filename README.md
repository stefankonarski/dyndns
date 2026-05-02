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
  - AAAA record (IPv6 via `ipv6` or `ip6addr`)
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
`docker-compose.yml` now requires both `APP_SECRET` and `HETZNER_DNS_API_TOKEN` at startup.

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
# edit .env.local (APP_SECRET, HETZNER_DNS_API_TOKEN, TRUSTED_PROXIES)
set -a; . ./.env.local; set +a
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

- run a daily `./ops/backup-var-data.sh` backup for `./var/data`
- run daily maintenance (`DB log cleanup + file log rotation`) via `./ops/maintenance-cron.sh`
- use `./ops/cron.example` as a crontab template (replace `PROJECT_DIR`)

Install cron entries:

```bash
sed "s|^PROJECT_DIR=.*|PROJECT_DIR=$(pwd)|" ops/cron.example | crontab -
```

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

Daily cron entrypoint:

```bash
./ops/maintenance-cron.sh
```

## Fritzbox DynDNS URL

Default endpoint:

```text
https://ddns.example.com/update?username=<username>&password=<pass>&domain=<domain>&ipaddr=<ipaddr>&ipv6=<ipv6>
```

Processed query parameters:

- `username`
- `password` (`pass` also supported)
- `domain`
- `ipaddr` (`ipv4` also supported)
- `ipv6` (`ip6addr` also supported)

If IPv6 is enabled in the admin config, a valid public IPv6 must be sent with the update request.

## Reverse Proxy Example

External TLS termination, internal HTTP:

- Public: `https://ddns.example.com`
- Internal upstream target: `http://127.0.0.1:9099`

Set `TRUSTED_PROXIES` correctly (for example proxy IP or network) so `X-Forwarded-*` is handled properly.

For Docker-based reverse proxies, `TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12` is a practical baseline.

Nginx example without query strings in `/update` access logs:

```nginx
log_format main_no_query '$remote_addr - $remote_user [$time_local] '
                         '"$request_method $uri $server_protocol" $status $body_bytes_sent '
                         '"$http_referer" "$http_user_agent"';

location = /update {
    access_log /var/log/nginx/dyndns_access.log main_no_query;
    proxy_pass http://127.0.0.1:9099;
}
```

Verify `/update` query strings are not written to access logs:

```bash
./ops/check-proxy-update-logging.sh /var/log/nginx/dyndns_access.log
```

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
