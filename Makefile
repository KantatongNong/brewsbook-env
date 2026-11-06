.PHONY: up down logs reset-db config ps pull

up:
	docker compose up -d

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
