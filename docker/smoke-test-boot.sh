#!/usr/bin/env bash
# Simulates Coolify/Docker entrypoint boot using the real backend tree + a temp sqlite DB.
# Usage: ./docker/smoke-test-boot.sh
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="${ROOT}/backend"
TMP="$(mktemp -d -t sh-docker-smoke)"
DB_FILE="${TMP}/app.sqlite"
ENV_BACKUP=""

cleanup() {
  if [[ -n "${ENV_BACKUP}" && -f "${ENV_BACKUP}" ]]; then
    mv "${ENV_BACKUP}" "${BACKEND}/.env"
  elif [[ -f "${BACKEND}/.env.smoke-backup" ]]; then
    mv "${BACKEND}/.env.smoke-backup" "${BACKEND}/.env"
  fi
  rm -rf "${TMP}"
}
trap cleanup EXIT

echo "==> smoke-test-boot in ${TMP}"
echo "==> php $(php -r 'echo PHP_VERSION;')"

if [[ ! -f "${BACKEND}/vendor/autoload.php" ]]; then
  echo "ERROR: backend/vendor missing — run composer install in backend/"
  exit 1
fi

# Preserve developer .env
if [[ -f "${BACKEND}/.env" ]]; then
  ENV_BACKUP="${BACKEND}/.env.smoke-backup"
  cp "${BACKEND}/.env" "${ENV_BACKUP}"
fi

touch "${DB_FILE}"
mkdir -p \
  "${BACKEND}/storage/framework/cache" \
  "${BACKEND}/storage/framework/sessions" \
  "${BACKEND}/storage/framework/views" \
  "${BACKEND}/storage/logs" \
  "${BACKEND}/bootstrap/cache"

export APP_NAME="Safer Handling Smoke"
export APP_ENV=production
export APP_DEBUG=false
export APP_URL=http://127.0.0.1:8080
export FORM_BASE_URL=http://127.0.0.1:8080
export APP_KEY=""
export DB_CONNECTION=sqlite
export DB_DATABASE="${DB_FILE}"
export APP_DATABASE_PATH="${DB_FILE}"
export SESSION_DRIVER=file
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export LOG_CHANNEL=stderr

cd "${BACKEND}"

{
  echo "APP_NAME=\"${APP_NAME}\""
  echo "APP_ENV=${APP_ENV}"
  echo "APP_KEY="
  echo "APP_DEBUG=${APP_DEBUG}"
  echo "APP_URL=${APP_URL}"
  echo "FORM_BASE_URL=${FORM_BASE_URL}"
  echo "DB_CONNECTION=${DB_CONNECTION}"
  echo "DB_DATABASE=${DB_DATABASE}"
  echo "APP_DATABASE_PATH=${APP_DATABASE_PATH}"
  echo "SESSION_DRIVER=${SESSION_DRIVER}"
  echo "CACHE_STORE=${CACHE_STORE}"
  echo "QUEUE_CONNECTION=${QUEUE_CONNECTION}"
  echo "LOG_CHANNEL=${LOG_CHANNEL}"
} > .env

echo "==> key:generate (empty APP_KEY like first Coolify boot)"
php artisan key:generate --force --no-interaction
APP_KEY="$(grep -E '^APP_KEY=' .env | head -1 | cut -d= -f2-)"
APP_KEY="${APP_KEY%\"}"
APP_KEY="${APP_KEY#\"}"
export APP_KEY
echo "==> APP_KEY=${APP_KEY:0:20}..."

echo "==> migrate --force"
php artisan migrate --force --no-interaction

echo "==> route:list (boot Laravel)"
php artisan route:list 2>&1 | head -40

PORT="$(php -r '$s=@stream_socket_server("tcp://127.0.0.1:0"); $n=stream_socket_get_name($s,false); fclose($s); echo (int)explode(":", $n)[1];')"
echo "==> php -S smoke on :${PORT}"
cd "${ROOT}"
# Export DB paths so form PHP + Laravel see the temp sqlite
export APP_DATABASE_PATH="${DB_FILE}"
export DB_DATABASE="${DB_FILE}"
php -S "127.0.0.1:${PORT}" router.php >"${TMP}/php-server.log" 2>&1 &
SERVER_PID=$!
for i in 1 2 3 4 5; do
  if curl -s -o /dev/null "http://127.0.0.1:${PORT}/"; then break; fi
  sleep 0.3
done

code_home="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/" || true)"
code_login="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/login" || true)"
# Unauthenticated dashboard should redirect to login (302)
code_admin="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/admin/dashboard" || true)"

kill "${SERVER_PID}" 2>/dev/null || true
wait "${SERVER_PID}" 2>/dev/null || true

echo "==> GET /                 -> HTTP ${code_home}"
echo "==> GET /login            -> HTTP ${code_login}"
echo "==> GET /admin/dashboard  -> HTTP ${code_admin}"

ok=1
if [[ "${code_home}" != "200" && "${code_home}" != "302" ]]; then
  echo "ERROR: form home failed"
  ok=0
fi
if [[ "${code_login}" != "200" && "${code_login}" != "302" ]]; then
  echo "ERROR: /login failed"
  ok=0
fi
if [[ "${code_admin}" != "200" && "${code_admin}" != "302" ]]; then
  echo "ERROR: /admin/dashboard failed"
  ok=0
fi
if [[ "${ok}" -ne 1 ]]; then
  cat "${TMP}/php-server.log" || true
  exit 1
fi

echo "OK: boot path works (key:generate + migrate + router.php)"
