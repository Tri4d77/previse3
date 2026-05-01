#!/usr/bin/env bash
# =====================================================================
# Previse v2 — éles deployment script (CPanel-kompatibilis)
# =====================================================================
# Lokálisan futtatva: buildeli a frontend-et, optimalizálja a Laravel-t,
# majd FTP/SSH-rsync-kel feltölti a szerverre.
#
# Másold át a saját környezetedhez igazítva. NE commitold a credentials-eket!
#
# Előfeltételek:
#   - Lokálisan: composer, npm, ssh/sftp/rsync (vagy lftp)
#   - Szerveren: php 8.4, mysql, már létrehozott DB + user
# =====================================================================

set -euo pipefail

# ----- Konfiguráció (ezt kell módosítanod) ----------------------------
SSH_USER="cpanel_user"
SSH_HOST="example.hu"
SSH_PORT=22
REMOTE_API_PATH="/home/${SSH_USER}/previse-api"
REMOTE_WEB_PATH="/home/${SSH_USER}/public_html"     # vagy public_html/app/
# ----------------------------------------------------------------------

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="${ROOT_DIR}/previse-api"
WEB_DIR="${ROOT_DIR}/previse-web"

echo "════════════════════════════════════════════════════════════════"
echo " Previse v2 — éles deployment"
echo "════════════════════════════════════════════════════════════════"
echo "  Cél: ${SSH_USER}@${SSH_HOST}:${REMOTE_API_PATH}"
echo

# ===== 1. Backend build =====
echo "▶ 1/4  Backend (composer install --no-dev)..."
cd "${API_DIR}"
composer install --optimize-autoloader --no-dev --no-interaction

# ===== 2. Frontend build =====
echo "▶ 2/4  Frontend (npm run build)..."
cd "${WEB_DIR}"
npm ci
npm run build

# ===== 3. Backend feltöltés (rsync) =====
echo "▶ 3/4  Backend rsync → ${REMOTE_API_PATH}..."
cd "${ROOT_DIR}"

# Excludált fájlok
RSYNC_EXCLUDES=(
    --exclude='.git'
    --exclude='.env'                  # éles .env soha NEM cserélődik!
    --exclude='node_modules'
    --exclude='tests'
    --exclude='storage/logs/*'
    --exclude='storage/framework/cache/*'
    --exclude='storage/framework/sessions/*'
    --exclude='storage/framework/views/*'
    --exclude='.env.example'
    --exclude='.env.production.example'
    --exclude='phpunit.xml'
    --exclude='*.md'
)

rsync -avz --delete-after \
    "${RSYNC_EXCLUDES[@]}" \
    -e "ssh -p ${SSH_PORT}" \
    "${API_DIR}/" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_API_PATH}/"

# ===== 4. Frontend feltöltés =====
echo "▶ 4/4  Frontend rsync → ${REMOTE_WEB_PATH}..."
rsync -avz --delete-after \
    -e "ssh -p ${SSH_PORT}" \
    "${WEB_DIR}/dist/" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_WEB_PATH}/"

# ===== 5. Szerver-oldali post-deploy parancsok =====
echo "▶ 5/5  Szerver-oldali optimalizáció..."
ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" bash <<EOF
set -e
cd "${REMOTE_API_PATH}"
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
echo "✓ Server optimization done."
EOF

echo
echo "════════════════════════════════════════════════════════════════"
echo " ✓ Deploy kész."
echo "════════════════════════════════════════════════════════════════"
echo "  Next steps:"
echo "  - Health check: curl https://${SSH_HOST}/api/v1/health/details"
echo "  - Cron: ellenőrizd a CPanel Cron Jobs között a schedule:run perc-szintű futtatást"
echo "  - Mailpit/SMTP: küldj tesztemailt és nézd meg, megérkezik-e"
