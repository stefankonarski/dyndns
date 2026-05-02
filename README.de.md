# DynDNS Service (Symfony)

Eigenes DynDNS-System für Fritzbox-Updates mit Hetzner DNS API.

## Stack

- PHP 8.5 (im Docker-Container)
- Symfony 8.0
- SQLite (`/app/var/data/app.db`)
- Doctrine ORM + Migrationen
- Docker (HTTP intern, TLS extern über Reverse Proxy)
- Frontend-Assets via `npm run build`
- PHPUnit 13 (Tests in Docker mit PHP 8.5)

## Funktionen

- Öffentlicher DynDNS-Endpunkt: `GET /update`
- Separate Authentifizierung:
  - Admin-Login (Security/Form-Login)
  - Fritzbox DynDNS Username/Passwort (separat, Passwort gehasht)
- Idempotente DNS-Synchronisierung:
  - A-Record (IPv4 via `ipaddr`)
  - AAAA-Record (IPv6 via `ipv6` oder `ip6addr`)
- Vollständiges Request-Logging (ohne Secrets)
- Separate IP-Historie mit Gültigkeitszeiträumen
- Historien-Suche:
  - exakter Timestamp
  - Intervall
- Locking gegen parallele Updates
- Rate-Limiting für `/update` und Login-Throttling für Admin-Login

## Wichtige ENV-Variablen

Siehe `.env.example`:

- `APP_ENV=prod`
- `APP_SECRET=...`
- `DATABASE_URL=sqlite:////app/var/data/app.db`
- `HETZNER_DNS_API_TOKEN=...` (API-Token aus der Hetzner Console / Cloud API)
- `TRUSTED_PROXIES=...`

Hinweis: `HETZNER_DNS_API_TOKEN` wird **nur** aus ENV gelesen, nie in SQLite gespeichert und nie im UI angezeigt.
Der Token muss aus der Hetzner Console stammen (Cloud API, DNS-Berechtigung), nicht aus der alten DNS-Console.
`docker-compose.yml` erzwingt `APP_SECRET` und `HETZNER_DNS_API_TOKEN` beim Start.

## Lokale Installation (ohne Docker)

Voraussetzung lokal für Frontend-Build: Node.js `>=20.10`.

```bash
composer install
npm ci
npm run build
mkdir -p var/data var/log
php bin/console doctrine:migrations:migrate --no-interaction
```

Admin-User erstellen:

```bash
php bin/console app:create-admin-user admin@example.org "SehrSicheresPasswort123"
```

Admin-Passwort ändern:

```bash
php bin/console app:change-admin-password admin@example.org "NeuesSehrSicheresPasswort123"
```

Entwicklungsserver:

```bash
symfony server:start
```

## Docker Start (Produktion)

```bash
cp .env.example .env.local
# .env.local bearbeiten (APP_SECRET, HETZNER_DNS_API_TOKEN, TRUSTED_PROXIES)
set -a; . ./.env.local; set +a
docker compose -f docker-compose.yml up -d --build
```

Die App ist dann erreichbar über:

- Host: `http://127.0.0.1:9099`
- Container intern: `http://<container>:80`

### Persistentes Volume

`docker-compose.yml` nutzt:

- `./var:/app/var`

Wichtige persistente Daten:

- `./var/data` (SQLite DB)
- `./var/log` (Logs)
- `./var/cache` (Cache, wiederherstellbar)

`./var/cache` kann bei Problemen gelöscht werden; die App erstellt den Cache neu.

Backup-Empfehlung:

- tägliches Backup von `./var/data` mit `./ops/backup-var-data.sh`
- tägliche Wartung (DB-Log-Cleanup + Dateilog-Rotation) mit `./ops/maintenance-cron.sh`
- Cron-Vorlage liegt unter `./ops/cron.example` (ersetze dort `PROJECT_DIR`)

Cron installieren:

```bash
sed "s|^PROJECT_DIR=.*|PROJECT_DIR=$(pwd)|" ops/cron.example | crontab -
```

## Migrationen

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Im Docker-Container wird beim Start ebenfalls versucht, Migrationen auszuführen.

## Hetzner DNS testen

```bash
php bin/console app:test-hetzner-dns
```

## DNS Sync manuell

```bash
php bin/console app:sync-dns --force
php bin/console app:sync-dns --ipv4=1.1.1.1
```

## Alte Logs aufräumen

```bash
php bin/console app:cleanup-ddns-logs --days=90
```

Als täglicher Cron-Job:

```bash
./ops/maintenance-cron.sh
```

## Fritzbox DynDNS URL

Standard-Endpunkt:

```text
https://ddns.example.com/update?username=<username>&password=<pass>&domain=<domain>&ipaddr=<ipaddr>&ipv6=<ipv6>
```

Verarbeitete Query-Parameter:

- `username`
- `password` (`pass` wird ebenfalls unterstützt)
- `domain`
- `ipaddr` (`ipv4` wird ebenfalls unterstützt)
- `ipv6` (`ip6addr` wird ebenfalls unterstützt)

Wenn IPv6 in der Admin-Konfiguration aktiv ist, muss im Update-Request eine gültige öffentliche IPv6 mitgesendet werden.

## Reverse Proxy Beispiel

Extern TLS-Termination, intern HTTP:

- Öffentlich: `https://ddns.example.com`
- Internes Upstream-Ziel: `http://127.0.0.1:9099`

Setze `TRUSTED_PROXIES` passend (z.B. Proxy-IP oder Netz), damit `X-Forwarded-*` korrekt verarbeitet wird.

Für Docker-Setups ist meist `TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12` ein sinnvoller Startwert.

Nginx-Beispiel ohne Query-String im Access-Log für `/update`:

```nginx
log_format main_no_query '$remote_addr - $remote_user [$time_local] '
                         '"$request_method $uri $server_protocol" $status $body_bytes_sent '
                         '"$http_referer" "$http_user_agent"';

location = /update {
    access_log /var/log/nginx/dyndns_access.log main_no_query;
    proxy_pass http://127.0.0.1:9099;
}
```

Prüfen, dass keine Query-Strings für `/update` im Access-Log landen:

```bash
./ops/check-proxy-update-logging.sh /var/log/nginx/dyndns_access.log
```

## Admin-Bereich

- Login: `/login`
- Dashboard: `/admin`
- Konfiguration: `/admin/config`
- Logs: `/admin/logs`
- Historie/Suche: `/admin/history`

Konfigurierbar:

- Domain aus Hetzner-Zonen
- Subdomain (Default `home`)
- DynDNS-Username + DynDNS-Passwort
- TTL
- IPv4/IPv6 aktiv/inaktiv
- AAAA-Record löschen
- Force-Sync

## Tests

Empfohlen (immer mit PHP 8.5 im Container):

```bash
docker compose -f docker-compose.test.yml run --build --rm dyndns-test
```

Lokal (nur wenn dein Host ebenfalls PHP 8.5 nutzt):

```bash
php bin/phpunit
```

Abgedeckte Kernfälle:

- erfolgreicher Fritzbox-Request
- Auth-Fehler
- ungültige/private IPv4
- Domain-Mismatch
- Unchanged/Created/Updated/Delete-Szenarien
- Log-Schreiben + Secret-Redaction
- IP-Historie IPv4/IPv6 Wechsel
- Suche nach Timestamp/Intervall
- Lock-Schutz für parallele Updates

## Sicherheits-Hinweise

- Keine Secrets in Logs oder UI
- Fritzbox-Passwort wird gehasht gespeichert
- API-Token nur via ENV
- Öffentlicher Endpunkt nur per Rate-Limit + Lock + Validierung
- Keine technischen Rohfehler im UI

## Lizenz

MIT (siehe `LICENSE`).
