# Brewsbook DEV deploy (192.168.3.94)

## Trade-offs

| Choice | Why |
|--------|-----|
| No MySQL in this compose | Host already runs `brewbook-mysql` on Docker network `db-brewbook_app-network` (host port 3305). API joins that network and talks to `brewbook-mysql:3306`. |
| Caddy publishes **8888:80** | Host Apache + Let’s Encrypt already terminate TLS for `https://dev-brewsbook.dxtms.com` and ProxyPass to `127.0.0.1:8888`. Keeps the public URL stable. |
| Keycloak `http-relative-path=/auth` | Same-origin browser auth at `https://dev-brewsbook.dxtms.com/auth/...` without a second public port. Fallback: publish Keycloak on host `8081` and `ProxyPass /auth`. |
| Separate `docker-compose.dev.yml` | Leaves local `docker-compose.yml` (with its own MySQL) untouched. |
| Realm file `realm-brewsbook.dev.json` | DEV redirect URIs / webOrigins for the public hostname; local realm JSON stays localhost-only. |

**Not in scope:** Shop Page full, Shop Store, Location, Ecommerce, Rating, Beer Keg, POS.

## Prerequisites

- SSH: `dxnong@192.168.3.94` (OVPN)
- `sudo docker` (dxnong needs password or docker group)
- External DB `dev_brewbook` / user `dbBrew` already up
- Do **not** delete `/opt/db-brewsbook/data`

## Deploy

```bash
# on server
sudo mkdir -p /opt/dev-brewsbook/stack
sudo chown dxnong:dxnong /opt/dev-brewsbook/stack
cd /opt/dev-brewsbook/stack
git clone https://github.com/KantatongNong/brewsbook-env.git .
# or: git pull if already cloned

cp .env.dev.example .env
# Fill MYSQL_PASSWORD from /opt/dev-brewsbook/app-brewsbook/.env (database.default.password)
# Generate KEYCLOAK_ADMIN_PASSWORD; chmod 600 .env

# Free :8888 (old Hello World PHP)
sudo docker stop dev_app_brewsbook || true
sudo docker rm dev_app_brewsbook || true
# Optional: leave the old compose file in place for rollback via docker compose up

# Optional: update Apache conf from docs/dev/dev-brewsbook-reverse.conf.desired
# (ProxyPreserveHost + X-Forwarded-Proto). Backup first, then reload httpd.

sudo docker compose -f docker-compose.dev.yml --env-file .env up -d --build
```

## Verify

```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://dev-brewsbook.dxtms.com/health
curl -sk -o /dev/null -w '%{http_code}\n' https://dev-brewsbook.dxtms.com/auth/realms/brewsbook/.well-known/openid-configuration
curl -sk -o /dev/null -w '%{http_code}\n' https://dev-brewsbook.dxtms.com/
```

Test users (realm import): `buyer` / `buyer123`, `platform_admin` / `admin123`.

## Rollback

```bash
cd /opt/dev-brewsbook/stack
sudo docker compose -f docker-compose.dev.yml down
cd /opt/dev-brewsbook
sudo docker compose up -d --build   # restores dev_app_brewsbook on :8888
# restore Apache conf from backup if changed, then: sudo systemctl reload httpd
```
