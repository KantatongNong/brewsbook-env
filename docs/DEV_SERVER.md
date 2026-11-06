# Dev server vs local compose / เซิร์ฟเวอร์ DEV เทียบ local

Thai + English mapping of the **real OVPN DEV host** (`dxnong@192.168.3.94`) versus the **local Keycloak stack** at repo root.

## Quick map / ตารางเทียบ

| Topic / หัวข้อ | Local (repo root) | Dev server (OVPN) |
|----------------|-------------------|-------------------|
| Path on disk | `./` (this clone) | `/opt/dev-brewsbook/` |
| Compose file | `docker-compose.yml` | `dev-server/docker-compose.yml` (mirror of host file) |
| Network access | localhost | OVPN → `192.168.3.94` |
| SSH | — | `dxnong@192.168.3.94:22` |
| App path | stubs: `api/`, `web/` | `/opt/dev-brewsbook/app-brewsbook/` → container `/var/www/html` |
| Web content today | nginx Next stub :3000 | `app-brewsbook/web/index.html` (Hello World) via Apache |
| HTTP port | `3000` (web), `8080` (api), `80/443` (Caddy) | host `8888` → container `80` |
| Public URL | http://localhost:3000 | https://dev-brewsbook.dxtms.com/ |
| PHP | stub PHP 8.2 Apache (`api/`) | **PHP 8.3.36-Apache** (`php:8.3.36-apache`) |
| Auth | Keycloak `start-dev` :8081 | not in this compose (TBD) |
| Database | MySQL container :3306 DB `brewsbook` | **External** host MySQL `192.168.3.94:3305` DB `dev_brewbook` user `dbBrew` |
| DB UI | — | http://192.168.3.100:8080/ (server `192.168.3.94:3305`) |
| Reverse proxy | Caddy in compose | external / host TLS → container :8888 (not in this file) |
| Docker network | `brewsbook` | `app-network` |
| Container name | `brewsbook-api` / `brewsbook-web` | `dev_app_brewsbook` |
| Env style | Docker/`MYSQL_*` + OIDC | CodeIgniter `database.default.*` / `app.baseURL` |

## DocumentRoot mismatch (preserved) / ความไม่ตรงกันของ DocumentRoot

On the DEV server these three disagree — mirrored as-is with comments:

| Source | DocumentRoot |
|--------|--------------|
| Compose `APACHE_DOCUMENT_ROOT` | `/var/www/html/public` |
| Image-baked `app-brewsbook/docker/apache/vhost.conf` | `/var/www/html/public` |
| Runtime mount `config/apache/000-default.conf` | **`/var/www/html/web`** ← wins |
| Actual files today | `app-brewsbook/web/` |

**EN:** Runtime Apache uses `/web` because of the bind-mount. Compose env `/public` is unused until Apache is configured to honor `APACHE_DOCUMENT_ROOT` (official PHP image pattern) or CodeIgniter `public/` lands.  
**TH:** ค่าที่ Apache ใช้จริงคือ `/web` จากไฟล์ mount — env `/public` ยังไม่มีผลจนกว่าจะตั้ง Apache ให้ใช้ตัวแปรนี้ หรือย้ายโค้ด CI ไปที่ `public/`

## Layout on server / โครงสร้างบนเซิร์ฟเวอร์

```
/opt/dev-brewsbook/
  docker-compose.yml
  app-brewsbook/
    Dockerfile
    .env                 # secrets — not in git
    docker/php/php.ini
    docker/apache/vhost.conf   # baked → /public
    web/index.html             # Hello World
  config/apache/
    000-default.conf     # runtime → /web
    php.conf
    apache2.conf         # present; not mounted by compose today
  logs/apache/
```

Repo mirror: `dev-server/` (same relative paths).

## How to use the mirror locally / ลอง mirror บนเครื่อง

```bash
cd dev-server
cp .env.example app-brewsbook/.env   # fill placeholders; do not commit
docker compose up -d --build
# http://localhost:8888  → Hello World (if DocumentRoot /web is active)
```

DB will **not** start with this compose — point `database.default.*` at a reachable MySQL (VPN + host IP, or a separate local MySQL).

## Secrets policy / นโยบายความลับ

- Only **key names** and **placeholders** (`CHANGE_ME`) live in git.
- Real SSH / DB passwords stay out of this repo and out of Pages.
