#!/usr/bin/env bash
set -uo pipefail

log() { echo "[entrypoint] $*" >&2; }

APP_ROOT="/var/www/html"
BACKEND="${APP_ROOT}/backend"
DATA_DIR="${APP_ROOT}/data"
DEFAULT_DB_FILE="${DATA_DIR}/app.sqlite"

# Coolify often imports local .env values. Host paths (e.g. /Users/...) do not
# exist in the container — always use the mounted data volume path instead.
resolve_db_file() {
  local candidate="${1:-}"
  if [[ -z "${candidate}" ]]; then
    echo "${DEFAULT_DB_FILE}"
    return
  fi
  case "${candidate}" in
    "${DATA_DIR}"/*|"${APP_ROOT}/data"/*)
      echo "${candidate}"
      ;;
    /Users/*|/home/*|/Volumes/*|[A-Za-z]:\\*|[A-Za-z]:/*)
      log "WARNING: ignoring host DB path '${candidate}' — using ${DEFAULT_DB_FILE}"
      echo "${DEFAULT_DB_FILE}"
      ;;
    *)
      if [[ "${candidate}" == /* && "${candidate}" != "${APP_ROOT}"/* ]]; then
        log "WARNING: ignoring non-container DB path '${candidate}' — using ${DEFAULT_DB_FILE}"
        echo "${DEFAULT_DB_FILE}"
      else
        echo "${candidate}"
      fi
      ;;
  esac
}

is_mysql() {
  local conn="${DB_CONNECTION:-}"
  local url="${DB_URL:-}"
  [[ "${conn}" == "mysql" || "${conn}" == "mariadb" ]] && return 0
  [[ "${url}" == mysql://* || "${url}" == mariadb://* ]] && return 0
  # Coolify sometimes sets host/user but leaves DB_CONNECTION unset/sqlite
  if [[ -n "${DB_HOST:-}" && -n "${DB_USERNAME:-}" ]]; then
    case "${DB_DATABASE:-}" in
      *.sqlite*|*"/"*) ;;
      *) return 0 ;;
    esac
    # Host set + password/user strongly implies MySQL even if DB_DATABASE is a leftover path
    if [[ -n "${DB_PASSWORD:-}" || "${DB_HOST}" == "mysql" || "${DB_PORT:-}" == "3306" || "${DB_PORT:-}" == "2248" ]]; then
      return 0
    fi
  fi
  return 1
}

# Coolify often leaves DB_DATABASE=/.../app.sqlite while switching to MySQL.
sanitize_mysql_database_name() {
  local name="${1:-default}"
  case "${name}" in
    ""|*.sqlite*|*"/"*|*:*)
      log "WARNING: DB_DATABASE='${name}' is not a MySQL database name — using 'default'"
      echo "default"
      ;;
    *)
      echo "${name}"
      ;;
  esac
}

log "starting (php $(php -r 'echo PHP_VERSION;'))"

mkdir -p \
  "${DATA_DIR}" \
  "${DATA_DIR}/booking-uploads" \
  "${BACKEND}/storage/framework/cache" \
  "${BACKEND}/storage/framework/sessions" \
  "${BACKEND}/storage/framework/views" \
  "${BACKEND}/storage/logs" \
  "${BACKEND}/bootstrap/cache"

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"

if is_mysql; then
  export DB_CONNECTION=mysql
  export DB_HOST="${DB_HOST:-mysql}"
  export DB_PORT="${DB_PORT:-3306}"
  export DB_DATABASE="$(sanitize_mysql_database_name "${DB_DATABASE:-default}")"
  export DB_USERNAME="${DB_USERNAME:-mysql}"
  export DB_PASSWORD="${DB_PASSWORD:-}"
  unset APP_DATABASE_PATH || true
  # Rebuild URL from discrete vars so a leftover sqlite path cannot win
  export DB_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
  if ! php -m 2>/dev/null | grep -qi pdo_mysql; then
    log "ERROR: pdo_mysql PHP extension is missing from this image — rebuild/redeploy latest Dockerfile"
    exit 1
  fi
  log "mysql database: ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
else
  DB_FILE="$(resolve_db_file "${APP_DATABASE_PATH:-${DB_DATABASE:-}}")"
  mkdir -p "$(dirname "${DB_FILE}")"
  if [[ ! -f "${DB_FILE}" ]]; then
    log "creating sqlite database at ${DB_FILE}"
    touch "${DB_FILE}"
  fi
  export DB_CONNECTION=sqlite
  export DB_DATABASE="${DB_FILE}"
  export APP_DATABASE_PATH="${DB_FILE}"
  unset DB_URL || true
  unset DB_HOST DB_PORT DB_USERNAME DB_PASSWORD || true
  log "sqlite database: ${DB_FILE}"
fi

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
  if is_mysql; then
    echo "DB_HOST=${DB_HOST}"
    echo "DB_PORT=${DB_PORT}"
    echo "DB_DATABASE=${DB_DATABASE}"
    echo "DB_USERNAME=${DB_USERNAME}"
    echo "DB_PASSWORD=\"${DB_PASSWORD//\"/\\\"}\""
    if [[ -n "${DB_URL:-}" ]]; then
      echo "DB_URL=\"${DB_URL//\"/\\\"}\""
    fi
  else
    echo "DB_DATABASE=${DB_DATABASE}"
    echo "APP_DATABASE_PATH=${APP_DATABASE_PATH}"
  fi
  echo "SESSION_DRIVER=${SESSION_DRIVER}"
  echo "QUEUE_CONNECTION=${QUEUE_CONNECTION}"
  echo "CACHE_STORE=${CACHE_STORE}"
  echo "LOG_CHANNEL=${LOG_CHANNEL}"
} > .env

optional_keys=(
  BREVO_API_KEY BREVO_SENDER_EMAIL BREVO_SENDER_NAME BREVO_EMAIL_ENABLED
  BREVO_CONTACT_EMAIL BREVO_LOGO_URL BREVO_QUOTE_ACCEPT_URL
  BREVO_RESUME_EMAIL_ENABLED BREVO_LEAD_NOTIFICATION_ENABLED
  MONDAY_API_TOKEN MONDAY_BOARD_ID MONDAY_ENABLED
  MONDAY_QUOTE_ACCEPTED_GROUP_NAME MONDAY_BOOKING_GROUP_NAME
  XERO_CLIENT_ID XERO_CLIENT_SECRET XERO_REDIRECT_URI XERO_TENANT_ID XERO_ENABLED XERO_WEBHOOK_KEY
  FORGE_WEBHOOK_ENABLED FORGE_WEBHOOK_URL FORGE_WEBHOOK_TOKEN
  IDEAL_POSTCODES_API_KEY KAJABI_COURSES_URL BOOKING_JOINING_INSTRUCTIONS_URL
  MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME
  AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION AWS_BUCKET
)

for key in "${optional_keys[@]}"; do
  if [[ -n "${!key:-}" ]]; then
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

if is_mysql; then
  log "running migrations on mysql ${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
else
  log "running migrations on ${DB_DATABASE}"
fi

if ! php artisan migrate --force --no-interaction; then
  log "ERROR: migrate failed — container will not start"
  if is_mysql; then
    log "Check DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD (or DB_URL) in Coolify"
    log "On the same Coolify server prefer DB_HOST=<mysql-service-name> and DB_PORT=3306"
  else
    log "Check DB path, volume mount (/var/www/html/data), and APP_KEY in Coolify"
  fi
  exit 1
fi

chown -R www-data:www-data \
  "${DATA_DIR}" \
  "${BACKEND}/storage" \
  "${BACKEND}/bootstrap/cache" \
  "${BACKEND}/.env" || true

if ! is_mysql; then
  chmod 664 "${DB_DATABASE}" 2>/dev/null || true
fi
chmod 775 "${DATA_DIR}" 2>/dev/null || true

LISTEN_PORT="${PORT:-80}"
if [[ ! "${LISTEN_PORT}" =~ ^[0-9]+$ ]]; then
  log "WARNING: invalid PORT='${LISTEN_PORT}', falling back to 80"
  LISTEN_PORT=80
fi
NGINX_SITE="/etc/nginx/sites-available/default"
if [[ -f "${NGINX_SITE}" ]]; then
  sed -i "s/__LISTEN_PORT__/${LISTEN_PORT}/g" "${NGINX_SITE}"
  sed -i -E "s/^(\\s*listen\\s+)[0-9]+(\\s+default_server;)/\\1${LISTEN_PORT}\\2/" "${NGINX_SITE}"
fi
log "nginx will listen on 0.0.0.0:${LISTEN_PORT} (set Coolify Ports Exposes to ${LISTEN_PORT})"

if ! nginx -t 2>&1; then
  log "WARNING: nginx -t failed (continuing — supervisord will surface nginx errors)"
fi

log "starting: $*"
exec "$@"
