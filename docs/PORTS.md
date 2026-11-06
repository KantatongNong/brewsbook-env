# Brewsbook port map

## Local stack (`docker-compose.yml`)

| Service | Container | Host port | Internal | Notes |
|---------|-----------|-----------|----------|-------|
| Caddy (HTTP) | reverse-proxy | 80 | 80 | Optional edge |
| Caddy (HTTPS) | reverse-proxy | 443 | 443 | Local TLS (self-signed via Caddy) |
| Web (Next.js) | web | 3000 | 3000 | Identity & Profile UI |
| API (CodeIgniter) | api | 8080 | 80 | `/health` and `/api/*` |
| Keycloak | keycloak | 8081 | 8080 | Admin + realm `brewsbook` |
| MySQL | mysql | 3306 | 3306 | DB `brewsbook` |

Docker network: `brewsbook` (bridge).

Suggested local URLs:
- Web: http://localhost:3000
- API health: http://localhost:8080/health
- Keycloak: http://localhost:8081
- Keycloak admin: http://localhost:8081/admin (user from `.env`)
- MySQL: localhost:3306

## Dev server OVPN (`dev-server/docker-compose.yml`)

| Service | Container | Host port | Internal | Notes |
|---------|-----------|-----------|----------|-------|
| PHP Apache | `dev_app_brewsbook` | **8888** | 80 | DocumentRoot `/var/www/html/web` |
| MySQL (external) | — | **3305** on `192.168.3.94` | — | DB `dev_brewbook`, user `dbBrew` |
| Public HTTPS | — | 443 | — | https://dev-brewsbook.dxtms.com/ |
| DB UI | — | 8080 on `192.168.3.100` | — | points at `192.168.3.94:3305` |
| SSH | — | 22 on `192.168.3.94` | — | user `dxnong` |

Docker network on host: `app-network`.
