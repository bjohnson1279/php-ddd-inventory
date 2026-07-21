#!/usr/bin/env bash
set -e

# Run the test suite inside the app container
docker compose run --rm app vendor/bin/phpunit --testdox
