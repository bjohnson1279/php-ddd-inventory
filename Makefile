PHONY: test

test:
	@echo "Running tests inside docker..."
	docker compose run --rm app vendor/bin/phpunit --testdox
