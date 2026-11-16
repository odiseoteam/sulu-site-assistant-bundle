# Development of the bundle. Everything runs in the containers of compose.yml.
DOCKER_COMPOSE ?= docker compose
DOCKER_USER ?= $(shell id -u):$(shell id -g)
PHP = DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec -T php
NODE = DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) --profile node run --rm -T node

.PHONY: init up down install assets phpunit unit integration functional vitest cs cs-fix phpstan deptrac composer-unused static ci console

init: up install assets

up:
	DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d --wait php mysql

down:
	$(DOCKER_COMPOSE) down --remove-orphans

install:
	$(PHP) composer install --no-interaction
	$(NODE) npm install --no-audit --no-fund

# The widget's JavaScript and CSS, built into public/.
assets:
	$(NODE) npm run build

phpunit:
	$(PHP) vendor/bin/phpunit

unit:
	$(PHP) vendor/bin/phpunit --testsuite unit

integration:
	$(PHP) vendor/bin/phpunit --testsuite integration

functional:
	$(PHP) vendor/bin/phpunit --testsuite functional

vitest:
	$(NODE) npx vitest run

cs:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G

deptrac:
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress

composer-unused:
	$(PHP) vendor/bin/composer-dependency-analyser

static: cs phpstan deptrac composer-unused

ci: static phpunit vitest

# The test application's console: make console c="debug:router" [context=website]
console:
	DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec -T -e APP_ENV=dev -e SULU_CONTEXT=$(or $(context),admin) php tests/Application/bin/console $(c)
