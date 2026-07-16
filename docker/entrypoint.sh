#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/html"
BACKEND="${APP_ROOT}/backend"
DATA_DIR="${APP_ROOT}/data"
DB_FILE="${APP_DATABASE_PATH:-${DATA_DIR}/app.sqlite}"

mkdir -p \
  "${DATA_DIR}" \
  "${DATA_DIR}/booking-uploads" \
  "${BACKEND}/storage/framework/cache" \
  "${BACKEND}/storage/framework/sessions" \
  "${BACKEND}/storage/framework/views" \
  "${BACKEND}/storage/logs" \
  "${BACKEND}/bootstrap/cache"

if [[ ! -f "${DB_FILE}" ]]; then
  touch "${DB_FILE}"
fi

# Keep Laravel and form PHP on the same SQLite file
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-${DB_FILE}}"
export APP_DATABASE_PATH="${APP_DATABASE_PATH:-${DB_FILE}}"

# Coolify usually injects env vars; write a runtime .env for artisan if missing
if [[ ! -f "${BACKEND}/.env" ]]; then
  {
    echo "APP_NAME=\"${APP_NAME:-Safer Handling}\""
    echo "APP_ENV=${APP_ENV:-production}"
    echo "APP_KEY=${APP_KEY:-}"
    echo "APP_DEBUG=${APP_DEBUG:-false}"
    echo "APP_URL=${APP_URL:-http://localhost}"
    echo "DB_CONNECTION=${DB_CONNECTION}"
    echo "DB_DATABASE=${DB_DATABASE}"
    echo "APP_DATABASE_PATH=${APP_DATABASE_PATH}"
    echo "SESSION_DRIVER=${SESSION_DRIVER:-database}"
    echo "QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}"
    echo "CACHE_STORE=${CACHE_STORE:-database}"
    echo "LOG_CHANNEL=${LOG_CHANNEL:-stderr}"
  } > "${BACKEND}/.env"
fi

cd "${BACKEND}"

if [[ -z "${APP_KEY:-}" ]] || [[ "${APP_KEY}" == "base64:" ]]; then
  if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction || true
  fi
fi

php artisan migrate --force --no-interaction

chown -R www-data:www-data \
  "${DATA_DIR}" \
  "${BACKEND}/storage" \
  "${BACKEND}/bootstrap/cache"

exec "$@"
