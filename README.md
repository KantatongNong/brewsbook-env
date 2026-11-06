# Brewsbook — Local / Dev Environment

สภาพแวดล้อม local สำหรับ Brewsbook (beer-lover platform)  
Local bootstrap for Brewsbook SE — runnable without real FE/API source yet (stubs).

> **Callout:** เริ่มจาก local ที่รันได้ก่อน แล้วค่อย harden สำหรับ staging/prod  
> Start with a runnable local stack before production hardening.

## Architecture / สถาปัตยกรรม

**Local (this repo root)** — full SE bootstrap with Keycloak:

```
Browser → Caddy (reverse-proxy)
            ├─ web   (Next.js Identity & Profile)
            ├─ keycloak (OIDC, start-dev)
            └─ api   (CodeIgniter Identity & Profile)
                       └─ mysql:8
```

**Dev server (OVPN)** — mirrored under `dev-server/` from `/opt/dev-brewsbook` on `192.168.3.94`:

```
Browser → https://dev-brewsbook.dxtms.com/ → (host TLS) → :8888 → PHP 8.3 Apache
                                                              └─ external MySQL :3305
```

Roles (Keycloak realm `brewsbook`, **local only** for now): `buyer` (default), `owner`, `shop_admin`, `worker`, `platform_admin`.

## Prerequisites / สิ่งที่ต้องมี

- Docker Engine + Docker Compose v2
- Make (optional, มี `Makefile`)
- พอร์ตว่าง local: `80`, `443`, `3000`, `8080`, `8081`, `3306` (ปรับได้ใน `.env`)
- สำหรับ DEV server: OVPN + SSH ไป `dxnong@192.168.3.94`

## Quick start (local Keycloak stack) / เริ่มใช้งาน local

```bash
cd brewsbook-env   # หรือ path ที่ clone มา
cp .env.example .env
# แก้รหัสผ่านใน .env ให้เป็นค่าของคุณ (อย่า commit .env)

make up
# หรือ: docker compose up -d
```

ตรวจสถานะ:

```bash
make ps
curl -s http://localhost:8080/health
# เปิด http://localhost:3000 และ http://localhost:8081
```

หยุด / ล้าง DB:

```bash
make down
make reset-db    # ลบ volume MySQL แล้วขึ้น mysql ใหม่
make logs
```

## Dev server (OVPN) / เซิร์ฟเวอร์ DEV

| Item | Value |
|------|-------|
| Host | `192.168.3.94` (ต้องต่อ OVPN ก่อน) |
| SSH | `dxnong@192.168.3.94:22` |
| Path on host | `/opt/dev-brewsbook/` |
| Web app | `/opt/dev-brewsbook/app-brewsbook/web` |
| Public URL | https://dev-brewsbook.dxtms.com/ |
| Container HTTP | host **8888** → container 80 (`dev_app_brewsbook`) |
| MySQL | `192.168.3.94:3305` DB `dev_brewbook` user `dbBrew` (password **not** in git) |
| DB UI | http://192.168.3.100:8080/ (server `192.168.3.94:3305`) |
| Stack | PHP **8.3.36-Apache** only — **no** MySQL/Keycloak/Caddy in that compose |
| Repo mirror | [`dev-server/`](dev-server/) |

How local compose differs from server:

| | Local `docker-compose.yml` | `dev-server/docker-compose.yml` |
|--|----------------------------|----------------------------------|
| Services | mysql + keycloak + api + web + caddy | `web_brewsbook` only |
| DB | in-compose MySQL :3306 | external host :3305 |
| Auth | Keycloak start-dev | not included |
| PHP base | stub 8.2 (`api/`) | **8.3.36-apache** (matches server) |
| DocumentRoot | stub `/var/www/html` health | **`/var/www/html/web`** (aligned) |

รายละเอียดเทียบ: [docs/DEV_SERVER.md](docs/DEV_SERVER.md)

ลอง mirror บนเครื่อง (ไม่มี DB ใน compose):

```bash
cd dev-server
cp .env.example app-brewsbook/.env   # ใส่ค่าจริงเอง — อย่า commit
docker compose up -d --build
# http://localhost:8888
```

### DocumentRoot

Aligned in this repo to **`/var/www/html/web`** (compose env, image vhost, runtime `000-default.conf`).  
เนื้อหาปัจจุบันอยู่ที่ `web/` (Hello World). Live host may need an image rebuild to refresh the baked vhost; runtime mount already uses `/web`.

## Default local URLs

| What | URL |
|------|-----|
| Web | http://localhost:3000 |
| API `/health` | http://localhost:8080/health |
| Keycloak | http://localhost:8081 |
| Keycloak Admin | http://localhost:8081/admin |
| Caddy HTTP edge | http://localhost (path: `/`, `/api`, `/auth`, `/health`) |
| Caddy HTTPS | https://localhost (internal CA) |
| MySQL | `localhost:3306` DB `brewsbook` |

รายละเอียดพอร์ต: [docs/PORTS.md](docs/PORTS.md)

## Env vars / ตัวแปรสำคัญ

คัดลอกจาก `.env.example` → `.env` เท่านั้น — **ห้ามใส่ secret จริงใน git**

ชื่อหลัก local (ไม่มีค่าจริงใน repo):

- `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`
- `KEYCLOAK_ADMIN`, `KEYCLOAK_ADMIN_PASSWORD`
- `OIDC_ISSUER`, `NEXT_PUBLIC_OIDC_ISSUER`, `NEXT_PUBLIC_OIDC_CLIENT_ID`
- `OIDC_API_CLIENT_ID`, `OIDC_API_CLIENT_SECRET`
- `PUBLIC_WEB_URL`, `PUBLIC_API_URL`, `PUBLIC_KEYCLOAK_URL`
- พอร์ต: `WEB_PORT`, `API_PORT`, `KEYCLOAK_HTTP_PORT`, `MYSQL_PORT`, `CADDY_*`

Dev-server CI keys (placeholders only — see `dev-server/.env.example`):

- `CI_ENVIRONMENT`, `app.baseURL`
- `database.default.hostname|database|username|password|DBDriver|charSet|DBCollat|port`
- `app.appTimezone`, `cache.file.storePath`, `session.savePath`

## Keycloak realm notes / โน้ต realm

- Image: `quay.io/keycloak/keycloak` (pinned `26.0`) — **local ใช้ `start-dev` เท่านั้น**
- Staging/prod จะใช้ **production mode** + DB แยก (ยังไม่กำหนด hostname — TBD)
- Import อัตโนมัติจาก `keycloak/realm-brewsbook.json` (`--import-realm`)
- Realm: `brewsbook`
- Clients:
  - `brewsbook-nextjs` — public OIDC (PKCE), redirect `http://localhost:3000/*`
  - `brewsbook-api` — confidential / bearer (secret ใน realm JSON เป็น placeholder — เปลี่ยนหลัง boot)
- Roles: `buyer`, `owner`, `shop_admin`, `worker`, `platform_admin`
- **OQ-14:** แหล่งความจริงของ roles (Keycloak vs app DB) ยังรอ sync กับ BA

หลังขึ้นครั้งแรก เปิด Admin console แล้วตรวจ realm `brewsbook` และ clients

## Services in compose

### Local (`docker-compose.yml`)

| Service | Image / build | Healthcheck |
|---------|---------------|-------------|
| `mysql` | `mysql:8.0` | `mysqladmin ping` |
| `keycloak` | `quay.io/keycloak/keycloak:26.0` | `/health/ready` |
| `api` | build `./api` (PHP 8.2 Apache stub) | `GET /health` |
| `web` | build `./web` (nginx static stub) | — |
| `reverse-proxy` | `caddy:2.8-alpine` | — |

Network: Docker bridge ชื่อ `brewsbook`

### Dev-server mirror (`dev-server/docker-compose.yml`)

| Service | Image / build | Notes |
|---------|---------------|-------|
| `web_brewsbook` | build `./app-brewsbook` (`php:8.3.36-apache`) | ports `8888:80`, DB external |

Network: `app-network`

## MySQL schema stubs

`mysql/init/01-schema.sql` creates `users`, `shops`, `shop_requests`, `shop_modules`, and `shop_members` for Identity & Profile. The API migration upgrades an existing volume on startup. See [NOTES.md](NOTES.md).

## Identity & Profile

The local API and web services implement Identity & Profile (Keycloak login, profile, shop requests, platform-admin approve/modules, shop enable/disable). How to run, exact routes, test users, and what is intentionally not built: [NOTES.md](NOTES.md).

`python3 scripts/qa-identity.py` exercises the API against a running stack.

Dev-server `web/` is still the Hello World mirror of the OVPN host. It is not this app.

## Layout

```
brewsbook-env/
  README.md
  docker-compose.yml          # local Keycloak + MySQL + stubs
  .env.example
  .gitignore
  Makefile
  caddy/Caddyfile
  keycloak/realm-brewsbook.json
  mysql/init/01-schema.sql
  api/ …                      # local PHP stub
  web/ …                      # local nginx stub
  docs/PORTS.md
  docs/DEV_SERVER.md          # local vs OVPN DEV mapping
  dev-server/                 # mirror of /opt/dev-brewsbook
    docker-compose.yml
    .env.example              # CI-style keys, placeholders only
    app-brewsbook/
      Dockerfile
      .env.example
      docker/php/php.ini
      docker/apache/vhost.conf   # → /web
      web/index.html
    config/apache/
      000-default.conf           # runtime → /web
      php.conf
      apache2.conf
    logs/apache/
```

## Next steps (SE)

1. ใส่ source Next.js / CodeIgniter จริงแทน stub (DocumentRoot already `/web`)
2. ตั้ง staging/prod overlays (Keycloak production mode, HTTPS จริง, secrets manager)
3. CI/CD, backups, observability
4. Sync roles policy กับ BA (OQ-14)

เอกสาร Page: *Brewsbook — Environments (local / staging / prod)*  
ซิงก์กับ canvas *Brewsbook — โมดูล + API หลัก*
