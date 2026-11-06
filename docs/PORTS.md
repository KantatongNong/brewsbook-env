# Brewsbook local port map

| Service | Container | Host port | Internal | Notes |
|---------|-----------|-----------|----------|-------|
| Caddy (HTTP) | reverse-proxy | 80 | 80 | Optional edge |
| Caddy (HTTPS) | reverse-proxy | 443 | 443 | Local TLS (self-signed via Caddy) |
| Web (Next stub) | web | 3000 | 3000 | Hello page |
| API (PHP stub) | api | 8080 | 80 | `/health` JSON |
| Keycloak | keycloak | 8081 | 8080 | Admin + realm `brewsbook` |
| MySQL | mysql | 3306 | 3306 | DB `brewsbook` |

Docker network: `brewsbook` (bridge).

Suggested local URLs:
- Web: http://localhost:3000
- API health: http://localhost:8080/health
- Keycloak: http://localhost:8081
- Keycloak admin: http://localhost:8081/admin (user from `.env`)
- MySQL: localhost:3306
