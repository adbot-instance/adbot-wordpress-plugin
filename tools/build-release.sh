#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="adbot"
DIST_DIR="${ROOT}/dist"
STAGE_DIR="${DIST_DIR}/${SLUG}"

rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

echo "Installing PHP dependencies (prod)..."
if [ -f "${ROOT}/composer.json" ]; then
  (cd "${ROOT}" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist)
fi

echo "Building admin assets..."
if [ -f "${ROOT}/package.json" ]; then
  (cd "${ROOT}" && npm ci && npm run build)
fi

echo "Staging files..."
rsync -a \
  --exclude-from="${ROOT}/.distignore" \
  "${ROOT}/" \
  "${STAGE_DIR}/"

echo "Creating zip..."
(cd "${DIST_DIR}" && zip -r "${SLUG}.zip" "${SLUG}" >/dev/null)

echo "Done: ${DIST_DIR}/${SLUG}.zip"

