#!/bin/sh
set -eu
TARGET=/usr/share/nginx/html/index.html
if [ -f "$TARGET" ]; then
  sed -i \
    -e "s|__PUBLIC_WEB_URL__|${PUBLIC_WEB_URL:-http://localhost:3000}|g" \
    -e "s|__PUBLIC_API_URL__|${PUBLIC_API_URL:-http://localhost:8080}|g" \
    -e "s|__PUBLIC_KEYCLOAK_URL__|${PUBLIC_KEYCLOAK_URL:-http://localhost:8081}|g" \
    -e "s|__OIDC_ISSUER__|${NEXT_PUBLIC_OIDC_ISSUER:-http://localhost:8081/realms/brewsbook}|g" \
    -e "s|__OIDC_CLIENT_ID__|${NEXT_PUBLIC_OIDC_CLIENT_ID:-brewsbook-nextjs}|g" \
    "$TARGET"
fi
