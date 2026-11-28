#!/usr/bin/env bash
# Brings up the development environment in a fresh cloud container:
# Docker (PHP 8.4 runs in serversideup/php:8.4-cli), PostgreSQL, Redis,
# backend and frontend dependencies. Safe to run again.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BIN="$ROOT/docs/agent/bin"

pgrep dockerd >/dev/null || (nohup dockerd >/tmp/dockerd.log 2>&1 &)
service postgresql start >/dev/null
redis-cli ping >/dev/null 2>&1 || redis-server --daemonize yes >/dev/null
for i in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 2; done
docker image inspect serversideup/php:8.4-cli >/dev/null 2>&1 || docker pull -q serversideup/php:8.4-cli

sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='laundry'" | grep -q 1 \
  || sudo -u postgres psql -qc "CREATE USER laundry WITH PASSWORD 'secret' SUPERUSER;"
for db in laundry laundry_test laundry_e2e; do
  sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='$db'" | grep -q 1 || sudo -u postgres createdb -O laundry "$db"
done

cd "$ROOT/backend"
[ -f .env ] || cp .env.example .env
if [ ! -f vendor/autoload.php ]; then
  # GitHub zip downloads are blocked here; phpstan has no source package, so seed Composer's cache from git.
  ref=$(python3 -c "import json;print([p for p in json.load(open('composer.lock'))['packages-dev'] if p['name']=='phpstan/phpstan'][0]['dist']['reference'])")
  url="https://api.github.com/repos/phpstan/phpstan/zipball/$ref"
  cache="$HOME/.cache/composer/files/phpstan/phpstan/$(php -r "echo sha1('$url');").zip"
  if [ ! -f "$cache" ]; then
    tmp=$(mktemp -d); (cd "$tmp" && git init -q src && cd src && git remote add origin https://github.com/phpstan/phpstan.git \
      && git fetch -q --depth 1 origin "$ref" && git checkout -q FETCH_HEAD && rm -rf .git && cd .. && mv src "phpstan-$ref" && zip -qr p.zip "phpstan-$ref")
    mkdir -p "$(dirname "$cache")" && cp "$tmp/p.zip" "$cache" && rm -rf "$tmp"
  fi
  COMPOSER_ALLOW_SUPERUSER=1 composer install --ignore-platform-req=php --prefer-source --no-interaction --no-scripts -q
fi
"$BIN/dphp" php artisan package:discover -q
grep -q '^APP_KEY=base64' .env || "$BIN/dphp" php artisan key:generate -q
"$BIN/dphp" php artisan migrate --force -q

cd "$ROOT/frontend"
[ -d node_modules ] || npm ci --silent
npx next typegen >/dev/null
echo "ready"
