# Brewsbook — Local / Dev Environment

สภาพแวดล้อม local สำหรับ Brewsbook (beer-lover platform)  
Local bootstrap for Brewsbook SE — runnable without real FE/API source yet (stubs).

> **Callout:** เริ่มจาก local ที่รันได้ก่อน แล้วค่อย harden สำหรับ staging/prod  
> Start with a runnable local stack before production hardening.

## Architecture / สถาปัตยกรรม

```
Browser → Caddy (reverse-proxy)
            ├─ web   (Next.js stub → real Next later)
            ├─ keycloak (OIDC, start-dev)
            └─ api   (CodeIgniter PHP stub → real CI later)
                       └─ mysql:8
```

Roles (Keycloak realm `brewsbook`): `buyer` (default), `owner`, `shop_admin`, `worker`, `platform_admin`.

## Prerequisites / สิ่งที่ต้องมี

- Docker Engine + Docker Compose v2
- Make (optional, มี `Makefile`)
- พอร์ตว่าง: `80`, `443`, `3000`, `8080`, `8081`, `3306` (ปรับได้ใน `.env`)

## Quick start / เริ่มใช้งาน

```bash
cd /workspace/brewsbook-env   # หรือ path ที่ clone มา
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

## Default local URLs

| What | URL |
|------|-----|
| Web stub | http://localhost:3000 |
| API `/health` | http://localhost:8080/health |
| Keycloak | http://localhost:8081 |
| Keycloak Admin | http://localhost:8081/admin |
| Caddy HTTP edge | http://localhost (path: `/`, `/api`, `/auth`, `/health`) |
| Caddy HTTPS | https://localhost (internal CA) |
| MySQL | `localhost:3306` DB `brewsbook` |

รายละเอียดพอร์ต: [docs/PORTS.md](docs/PORTS.md)

## Env vars / ตัวแปรสำคัญ

คัดลอกจาก `.env.example` → `.env` เท่านั้น — **ห้ามใส่ secret จริงใน git**

ชื่อหลัก (ไม่มีค่าจริงใน repo):

- `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`
- `KEYCLOAK_ADMIN`, `KEYCLOAK_ADMIN_PASSWORD`
- `OIDC_ISSUER`, `NEXT_PUBLIC_OIDC_ISSUER`, `NEXT_PUBLIC_OIDC_CLIENT_ID`
- `OIDC_API_CLIENT_ID`, `OIDC_API_CLIENT_SECRET`
- `PUBLIC_WEB_URL`, `PUBLIC_API_URL`, `PUBLIC_KEYCLOAK_URL`
- พอร์ต: `WEB_PORT`, `API_PORT`, `KEYCLOAK_HTTP_PORT`, `MYSQL_PORT`, `CADDY_*`

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

| Service | Image / build | Healthcheck |
|---------|---------------|-------------|
| `mysql` | `mysql:8.0` | `mysqladmin ping` |
| `keycloak` | `quay.io/keycloak/keycloak:26.0` | `/health/ready` |
| `api` | build `./api` (PHP 8.2 Apache stub) | `GET /health` |
| `web` | build `./web` (nginx static stub) | — |
| `reverse-proxy` | `caddy:2.8-alpine` | — |

Network: Docker bridge ชื่อ `brewsbook`

## MySQL schema stubs

`mysql/init/01-schema.sql` สร้างตารางบาง: `users`, `shops`, `shop_requests`, `shop_modules`, `shop_members`  
**หมายเหตุ:** migrations ของแอปจะเป็นเจ้าของ schema จริงในภายหลัง — ไฟล์นี้เป็น stub เพื่อให้ local ขึ้นได้

## API / Web stubs

- API ไม่ validate JWT ตอนนี้ — แค่ตอบ `/health` เป็น JSON 200
- Web เป็นหน้า hello ที่ลิงก์ Keycloak/API จาก env — แทนที่ด้วย Next.js + React + Tailwind เมื่อมี source

## Layout

```
brewsbook-env/
  README.md
  docker-compose.yml
  .env.example
  .gitignore
  Makefile
  caddy/Caddyfile
  keycloak/realm-brewsbook.json
  mysql/init/01-schema.sql
  api/Dockerfile
  api/public/index.php
  api/public/health.php
  web/Dockerfile
  web/public/index.html
  docs/PORTS.md
```

## Next steps (SE)

1. ใส่ source Next.js / CodeIgniter จริงแทน stub
2. ตั้ง staging/prod overlays (Keycloak production mode, HTTPS จริง, secrets manager)
3. CI/CD, backups, observability
4. Sync roles policy กับ BA (OQ-14)

เอกสาร Page: *Brewsbook — Environments (local / staging / prod)*  
ซิงก์กับ canvas *Brewsbook — โมดูล + API หลัก*
