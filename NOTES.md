# Brewsbook Identity & Profile

This slice replaces the local API and web stubs with a CodeIgniter 4 REST API and a Next.js app. Shop Store, Location, Ecommerce, Rating, Beer Keg, POS, team invites, and a public shop page are not implemented. Module keys are stored as flags only.

## Run

```bash
cp .env.example .env
# set MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD, and KEYCLOAK_ADMIN_PASSWORD

make up
# same as: docker compose up -d --build
```

Published ports stay the same:

| What | URL |
|------|-----|
| Web | http://localhost:3000 |
| API | http://localhost:8080 |
| API health | http://localhost:8080/health |
| Web health | http://localhost:3000/health |
| Keycloak | http://localhost:8081 |
| Keycloak admin | http://localhost:8081/admin (`KEYCLOAK_ADMIN` / `KEYCLOAK_ADMIN_PASSWORD`) |
| Caddy edge | http://localhost/api/... (prefix is no longer stripped) |
| MySQL | localhost:3306, database `brewsbook` |

`NEXT_PUBLIC_*` values are baked into the web image at build time. Change the public URLs in `.env`, then run `make up` again so Compose rebuilds.

The API waits until MySQL and Keycloak are healthy, runs `php spark migrate --all`, then serves Apache. `GET /health` does not check a token.

Keycloak 26 answers `GET /health/ready` on management port **9000** inside the container. The compose healthcheck uses that port. The published Keycloak port is still **8081 → 8080**.

### Reset

Init SQL runs only on an empty MySQL volume. The Keycloak realm file imports only into an empty Keycloak volume.

```bash
docker compose down -v
make up
```

`make reset-db` wipes MySQL only. Use `down -v` when realm users or client settings must be imported again. If the API volume already has the old stub tables, the migration adds the identity columns on startup.

## Test users

Local realm `brewsbook` only. Do not reuse these passwords anywhere else.

| Username | Password | Roles |
|----------|----------|--------|
| `buyer` | `buyer123` | `buyer` |
| `platform_admin` | `admin123` | `buyer`, `platform_admin` |

Self-registration is enabled on this local realm. A newly registered account receives the realm default role `buyer`.

The web sign-in screen lists the same accounts. Browser login uses the public client `brewsbook-nextjs` (authorization code + PKCE). Redirects are `http://localhost:3000/*`.

## Get a Keycloak token

Password grant is enabled on `brewsbook-nextjs` for local API checks.

```bash
curl -s -X POST 'http://localhost:8081/realms/brewsbook/protocol/openid-connect/token' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=password' \
  -d 'client_id=brewsbook-nextjs' \
  -d 'username=buyer' \
  -d 'password=buyer123'
```

Use `username=platform_admin` and `password=admin123` for the admin token. Send the access token as `Authorization: Bearer <access_token>`.

Repeatable checks: `python3 scripts/qa-identity.py` (stack must already be up).

## API

All JSON. Missing, invalid, or expired bearer tokens return **401**. Authenticated callers without `platform_admin` get **403** on `/api/admin/*`. Validation errors are **422** with `fields`. A second pending request for the same user is **409**.

| Method | Path | Who | Behavior |
|--------|------|-----|----------|
| GET | `/api/me` | any signed-in user | Upserts the user from the token. Returns profile, visible realm roles, and shop memberships. |
| PUT | `/api/me` | any signed-in user | Updates `display_name`, `given_name`, `family_name`, `phone`, `locale`. |
| POST | `/api/shop-requests` | any signed-in user | Body: `shop_name` (required), `description`, `phone`, `address_line`. Status `pending`. |
| GET | `/api/shop-requests/me` | any signed-in user | That user's requests, newest first. |
| GET | `/api/admin/shop-requests` | platform admin | Optional `?status=pending\|approved\|rejected`. |
| PUT | `/api/admin/shop-requests/{id}` | platform admin | `{"action":"approve","enabled_modules":["ecommerce"]}` or `{"action":"reject","rejection_reason":"..."}`. `status: approved\|rejected` is accepted as an alias of `action`. |
| GET | `/api/admin/shops` | platform admin | Optional `?platform_status=enabled\|disabled`. |
| PUT | `/api/admin/shops/{id}` | platform admin | `name`, `description`, `phone`, `address_line`, `slug`, `open_status` (`open` or `closed`). Rejects `platform_status` here. |
| PUT | `/api/admin/shops/{id}/modules` | platform admin | `{"enabled_modules":["location","beer_keg"]}`. Replaces flags. At least one key. |
| PUT | `/api/admin/shops/{id}/status` | platform admin | `{"platform_status":"disabled"}` or `"enabled"`. Does not change `open_status`. |

Module keys, used exactly: `ecommerce`, `shop_store`, `location`, `beer_keg`.

Approve (only while the request is `pending`, and only with at least one allowed module):

1. Creates one shop for that request (`platform_status=enabled`, `open_status=closed`).
2. Writes all four module rows, enabled only for the chosen keys.
3. Inserts `shop_members.role = owner`.
4. Grants the Keycloak realm role `owner` to the requester.
5. Sets the request to `approved` and stores the chosen modules.

The caller's current access token does not gain `owner` until it is refreshed. The web profile page has **Refresh session**. `GET /api/me` still shows the MySQL membership immediately.

Disable sets `platform_status=disabled` and leaves `open_status` alone. Enable restores platform access the same way. This slice does not run shop, catalog, keg, or POS code, so "blocked" means the flag is stored, returned on the shop and on `GET /api/me` memberships, and shown in the admin UI. Later modules should refuse work while `platform_status` is `disabled`.

## Web

- Sign in / sign out against Keycloak.
- Profile edit (`GET`/`PUT /api/me`).
- Submit a shop request and read `GET /api/shop-requests/me`.
- Admin request queue with a module picker, approve, and reject.
- Admin shop list: edit details, change modules, disable and enable platform access, set open/closed separately.

Non-admins who open `/admin/*` see a 403 state and the API is not treated as authorized.

## Data

`mysql/init/01-schema.sql` is the fresh-volume schema: `users` (Keycloak `sub`), `shop_requests`, `shops` (`platform_status`, `open_status`), `shop_modules`, `shop_members`. One pending request per user is enforced with a generated unique column. The same shape is applied or upgraded by `api/app/Database/Migrations/2026-11-06-000001_IdentityProfileSchema.php`.

## Limitations

- No shop storefront, location, ecommerce catalog/cart/orders, rating, beer keg, POS, invites, or public shop page.
- Roles are authoritative in Keycloak. MySQL stores the profile and shop membership, not a second copy of platform roles.
- The API checks the token signature (Keycloak JWKS via `http://keycloak:8080`), `exp`, issuer `OIDC_ISSUER` (default `http://localhost:8081/realms/brewsbook`), and `azp` when present (`brewsbook-nextjs` or `brewsbook-api`). It does not require `aud` to equal the API client, because the browser sends the Next.js access token.
- Approve fails with **502** and rolls back the shop if Keycloak cannot grant `owner` (admin password missing, Keycloak down, or the user id is unknown).
- Direct access grants and open registration are for this local realm. Do not copy that into production.
- Use http://localhost:3000 for the UI. The Keycloak client is not registered for the Caddy HTTPS origin, and a page on https://localhost cannot call the HTTP API.
- `dev-server/` is still the separate PHP hello-world mirror. It is not this app.
- `make reset-db` does not reimport Keycloak.
