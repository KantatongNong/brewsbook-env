.PHONY: up down logs reset-db config ps pull up-dev down-dev logs-dev

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

config:
	docker compose config

pull:
	docker compose pull

reset-db:
	docker compose down -v
	docker compose up -d mysql
	@echo "MySQL volume wiped and container restarted. Re-run: make up"

# Mirror of /opt/dev-brewsbook (PHP Apache only; DB external)
up-dev:
	docker compose -f dev-server/docker-compose.yml --project-directory dev-server up -d --build

down-dev:
	docker compose -f dev-server/docker-compose.yml --project-directory dev-server down

logs-dev:
	docker compose -f dev-server/docker-compose.yml --project-directory dev-server logs -f --tail=200
