.DEFAULT_GOAL := up

COMPOSE := docker compose

.PHONY: up build restart config down delete-volumes stop logs ps check-env

up: check-env
	$(COMPOSE) up --build -d --wait

build: check-env
	$(COMPOSE) build

restart: check-env
	$(COMPOSE) restart

config: check-env
	$(COMPOSE) config

down:
	$(COMPOSE) down

# Supprime les conteneurs et les volumes de données du projet.
delete-volumes:
	$(COMPOSE) down --volumes

stop:
	$(COMPOSE) stop

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

check-env:
	@test -f .env || { echo "Fichier .env absent : copie .env.example vers .env et renseigne DB_PASSWORD." >&2; exit 1; }
