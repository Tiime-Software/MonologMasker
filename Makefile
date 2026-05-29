PHP_VERSION ?= 8.3
IMAGE := monolog-masker:php$(PHP_VERSION)
DOCKER_RUN := docker run --rm -v $(PWD):/app -w /app $(IMAGE)

.DEFAULT_GOAL := help

.PHONY: help
help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

.PHONY: build
build: ## Construit l'image Docker de dev
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $(IMAGE) .

.PHONY: install
install: build ## Installe les dépendances Composer
	$(DOCKER_RUN) composer install --no-interaction --prefer-dist

.PHONY: cs-check
cs-check: ## Vérifie le style (php-cs-fixer, dry-run)
	$(DOCKER_RUN) composer cs-check

.PHONY: cs-fix
cs-fix: ## Corrige le style
	$(DOCKER_RUN) composer cs-fix

.PHONY: phpstan
phpstan: ## Analyse statique (niveau max)
	$(DOCKER_RUN) composer phpstan

.PHONY: test
test: ## Lance les tests PHPUnit
	$(DOCKER_RUN) composer test

.PHONY: coverage
coverage: ## Tests + gate de couverture (100% lignes/méthodes)
	$(DOCKER_RUN) composer coverage

.PHONY: infection
infection: ## Mutation testing (MSI 100%)
	$(DOCKER_RUN) composer infection

.PHONY: proofs
proofs: ## Property-based testing (black-box, suite property)
	$(DOCKER_RUN) composer proofs

.PHONY: validate
validate: cs-check phpstan coverage infection proofs ## Validation complète (à passer avant tout commit)

.PHONY: shell
shell: ## Ouvre un shell dans le conteneur
	$(DOCKER_RUN) sh
