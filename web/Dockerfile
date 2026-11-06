# Brewsbook web — Next.js (standalone) on port 3000
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

FROM node:22-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

ARG PUBLIC_WEB_URL=http://localhost:3000
ARG PUBLIC_API_URL=http://localhost:8080
ARG PUBLIC_KEYCLOAK_URL=http://localhost:8081
ARG NEXT_PUBLIC_OIDC_ISSUER=http://localhost:8081/realms/brewsbook
ARG NEXT_PUBLIC_OIDC_CLIENT_ID=brewsbook-nextjs
ARG NEXT_PUBLIC_API_URL=http://localhost:8080
ARG NEXT_PUBLIC_KEYCLOAK_URL=http://localhost:8081

ENV NEXT_PUBLIC_OIDC_ISSUER=$NEXT_PUBLIC_OIDC_ISSUER \
    NEXT_PUBLIC_OIDC_CLIENT_ID=$NEXT_PUBLIC_OIDC_CLIENT_ID \
    NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL \
    NEXT_PUBLIC_KEYCLOAK_URL=$NEXT_PUBLIC_KEYCLOAK_URL \
    NEXT_TELEMETRY_DISABLED=1

RUN npm run build

FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production \
    PORT=3000 \
    NEXT_TELEMETRY_DISABLED=1
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
COPY --from=builder /app/public ./public
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh
EXPOSE 3000
ENTRYPOINT ["/docker-entrypoint.sh"]
