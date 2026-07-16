#!/usr/bin/env bash
set -uo pipefail

log() { echo "[entrypoint] $*" >&2; }

APP_ROOT="/var/www/html"
BACKEND="${APP_ROOT}/backend"
DATA_DIR="${APP_ROOT}/data"
DB_FILE="${APP_DATABASE_PATH:-${DATA_DIR}/app.sqlite}"

log "starting (php $(php -r 'echo PHP_VERSION;'))"

mkdir -p \
  "${DATA_DIR}" \
  "${DATA_DIR}/booking-uploads" \
  "${BACKEND}/storage/framework/cache" \
  "${BACKEND}/storage/framework/sessions" \
  "${BACKEND}/storage/framework/views" \
  "${BACKEND}/storage/logs" \
  "${BACKEND}/bootstrap/cache"

if [[ ! -f "${DB_FILE}" ]]; then
  log "creating sqlite database at ${DB_FILE}"
  touch "${DB_FILE}"
fi

export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-${DB_FILE}}"
export APP_DATABASE_PATH="${APP_DATABASE_PATH:-${DB_FILE}}"
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
# Prefer file/sync in Docker so boot does not depend on migrated cache/session tables
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"

cd "${BACKEND}"

# Always refresh .env from container env so Laravel + artisan see Coolify values
{
  echo "APP_NAME=\"${APP_NAME:-Safer Handling}\""
  echo "APP_ENV=${APP_ENV}"
  echo "APP_KEY=${APP_KEY:-}"
  echo "APP_DEBUG=${APP_DEBUG}"
  echo "APP_URL=${APP_URL:-http://localhost}"
  echo "FORM_BASE_URL=${FORM_BASE_URL:-${APP_URL:-http://localhost}}"
  echo "DB_CONNECTION=${DB_CONNECTION}"
  echo "DB_DATABASE=${DB_DATABASE}"
  echo "APP_DATABASE_PATH=${APP_DATABASE_PATH}"
  echo "SESSION_DRIVER=${SESSION_DRIVER}"
  echo "QUEUE_CONNECTION=${QUEUE_CONNECTION}"
  echo "CACHE_STORE=${CACHE_STORE}"
  echo "LOG_CHANNEL=${LOG_CHANNEL}"
} > .env

# Append optional secrets / integration vars when present (do not invent empties that override admin settings)
optional_keys=(
  BREVO_API_KEY BREVO_SENDER_EMAIL BREVO_SENDER_NAME BREVO_EMAIL_ENABLED
  BREVO_CONTACT_EMAIL BREVO_LOGO_URL BREVO_QUOTE_ACCEPT_URL
  BREVO_RESUME_EMAIL_ENABLED BREVO_LEAD_NOTIFICATION_ENABLED
  MONDAY_API_TOKEN MONDAY_BOARD_ID MONDAY_ENABLED
  MONDAY_QUOTE_ACCEPTED_GROUP_NAME MONDAY_BOOKING_GROUP_NAME
  XERO_CLIENT_ID XERO_CLIENT_SECRET XERO_REDIRECT_URI XERO_TENANT_ID XERO_ENABLED
  IDEAL_POSTCODES_API_KEY BOOKING_JOINING_INSTRUCTIONS_URL
  MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME
  AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION AWS_BUCKET
)

for key in "${optional_keys[@]}"; do
  if [[ -n "${!key:-}" ]]; then
    # Escape double-quotes in values
    val="${!key//\"/\\\"}"
    echo "${key}=\"${val}\"" >> .env
  fi
done

current_key="$(grep -E '^APP_KEY=' .env | head -1 | cut -d= -f2-)"
current_key="${current_key%\"}"
current_key="${current_key#\"}"
current_key="${current_key%\'}"
current_key="${current_key#\'}"
if [[ -z "${current_key}" || "${current_key}" == "base64:" ]]; then
  log "APP_KEY missing or incomplete — generating"
  if ! php artisan key:generate --force --no-interaction; then
    log "ERROR: key:generate failed"
    exit 1
  fi
  APP_KEY="$(grep -E '^APP_KEY=' .env | head -1 | cut -d= -f2-)"
  APP_KEY="${APP_KEY%\"}"
  APP_KEY="${APP_KEY#\"}"
  export APP_KEY
  log "APP_KEY generated — set this in Coolify (Runtime) so it stays stable across deploys:"
  log "APP_KEY=${APP_KEY}"
fi

log "running migrations on ${DB_DATABASE}"
if ! php artisan migrate --force --no-interaction; then
  log "ERROR: migrate failed — container will not start"
  log "Check DB path, volume mount (/var/www/html/data), and APP_KEY in Coolify"
  exit 1
fi

chown -R www-data:www-data \
  "${DATA_DIR}" \
  "${BACKEND}/storage" \
  "${BACKEND}/bootstrap/cache" \
  "${BACKEND}/.env" || true

chmod 664 "${DB_FILE}" 2>/dev/null || true
chmod 775 "${DATA_DIR}" 2>/dev/null || true

if ! nginx -t 2>&1; then
  log "WARNING: nginx -t failed (continuing — supervisord will surface nginx errors)"
fi

log "starting: $*"
exec "$@"
