SHELL := /bin/sh

COMPOSE = docker compose --env-file .env $(if $(wildcard .env.local),--env-file .env.local,) -f docker/compose.yaml

.PHONY: init up down logs install test check reindex shell reset

init:
	@test -f .env
	@$(MAKE) install

up: init
	$(COMPOSE) up --build -d

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f --tail=100

install:
	$(COMPOSE) run --rm --no-deps app composer install --no-interaction --prefer-dist

test: install
	$(COMPOSE) run --rm --no-deps app composer test

check: install
	$(COMPOSE) run --rm --no-deps app composer check

reindex:
	$(COMPOSE) exec app php bin/reindex-products.php

shell:
	$(COMPOSE) exec app sh

# Explicitly destructive: removes local MySQL and Elasticsearch volumes.
reset:
	$(COMPOSE) down -v --remove-orphans
