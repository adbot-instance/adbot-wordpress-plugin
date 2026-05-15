#!/usr/bin/env bash
set -euo pipefail

# Repository root (this script lives in dev/tools/).
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_DIR="${ROOT}/adbot"
SLUG="adbot"
ZIP_NAME="adbot-tracking-platform"
DIST_DIR="${ROOT}/dist"
STAGE_DIR="${DIST_DIR}/${SLUG}"
EXCLUDES="${ROOT}/.plugin-build-excludes"

rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

echo "Installing PHP dependencies (prod)..."
if [ -f "${PLUGIN_DIR}/composer.json" ]; then
  (cd "${PLUGIN_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist)
fi

echo "Building admin assets..."
if [ -f "${ROOT}/package.json" ]; then
  (cd "${ROOT}" && npm ci && npm run build)
fi

echo "Staging plugin files..."
rsync -a \
  --exclude-from="${EXCLUDES}" \
  "${PLUGIN_DIR}/" \
  "${STAGE_DIR}/"

echo "Creating zip..."
(cd "${DIST_DIR}" && zip -r "${ZIP_NAME}.zip" "${SLUG}" >/dev/null)

echo "Done: ${DIST_DIR}/${ZIP_NAME}.zip"
