#!/usr/bin/env bash
#
# Build a distributable .zip for the Kenzi Chat plugin.
# Installs production deps, then packages everything not in .distignore.
#
set -euo pipefail

PLUGIN_SLUG="kenzi-chat"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BUILD_DIR="$(mktemp -d)"

trap 'rm -rf "$BUILD_DIR"' EXIT

echo "==> Installing production dependencies"
composer install --no-dev --optimize-autoloader --working-dir="$PLUGIN_DIR" --quiet

echo "==> Copying plugin files"
rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$BUILD_DIR/$PLUGIN_SLUG/"

# Remove dev-only composer files from the build
rm -f "$BUILD_DIR/$PLUGIN_SLUG/composer.json"
rm -f "$BUILD_DIR/$PLUGIN_SLUG/composer.lock"

ZIP_FILE="$PLUGIN_DIR/$PLUGIN_SLUG.zip"
echo "==> Creating $ZIP_FILE"
(cd "$BUILD_DIR" && zip -rq "$ZIP_FILE" "$PLUGIN_SLUG/")

echo "==> Built: $ZIP_FILE"
