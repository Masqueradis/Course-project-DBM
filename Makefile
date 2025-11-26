PROJECT_NAME=sandbox
DOCKER_COMPOSE=docker compose -f docker/docker-compose.yml --env-file docker/.env

.PHONY: up down restart build logs shell ps

up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) down
	$(DOCKER_COMPOSE) up -d

build:
	$(DOCKER_COMPOSE) build

logs:
	$(DOCKER_COMPOSE) logs -f

ps:
	$(DOCKER_COMPOSE) ps

shell:
	$(DOCKER_COMPOSE) exec php bash

install:
	$(DOCKER_COMPOSE) exec php composer install

update:
	$(DOCKER_COMPOSE) exec php composer update

stan:
	$(DOCKER_COMPOSE) exec php composer stan -- --level $(LEVEL)
	
cs-fix:
	$(DOCKER_COMPOSE) exec php composer cs-fix

cs-check:
	$(DOCKER_COMPOSE) exec php composer cs-check
