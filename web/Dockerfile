# Brewsbook Web stub — static hello page (Next.js app lands later)
FROM nginx:1.27-alpine

ARG PUBLIC_WEB_URL=http://localhost:3000
ARG PUBLIC_API_URL=http://localhost:8080
ARG PUBLIC_KEYCLOAK_URL=http://localhost:8081
ARG NEXT_PUBLIC_OIDC_ISSUER=http://localhost:8081/realms/brewsbook
ARG NEXT_PUBLIC_OIDC_CLIENT_ID=brewsbook-nextjs

ENV PUBLIC_WEB_URL=$PUBLIC_WEB_URL \
    PUBLIC_API_URL=$PUBLIC_API_URL \
    PUBLIC_KEYCLOAK_URL=$PUBLIC_KEYCLOAK_URL \
    NEXT_PUBLIC_OIDC_ISSUER=$NEXT_PUBLIC_OIDC_ISSUER \
    NEXT_PUBLIC_OIDC_CLIENT_ID=$NEXT_PUBLIC_OIDC_CLIENT_ID

COPY public/ /usr/share/nginx/html/
COPY nginx-default.conf /etc/nginx/conf.d/default.conf
COPY docker-entrypoint.sh /docker-entrypoint.d/99-brewsbook-env.sh
RUN chmod +x /docker-entrypoint.d/99-brewsbook-env.sh

EXPOSE 3000
