@echo off
REM Run PHPUnit inside the app container on Windows
docker compose run --rm app vendor\\bin\\phpunit --testdox
