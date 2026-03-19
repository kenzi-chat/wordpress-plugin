#!/usr/bin/env bash
#
# Build a distributable .zip for the Kenzi Chat plugin.
#
# Copies source to a temp directory, installs production-only deps there,
# then packages everything not in .distignore into a zip. This avoids
# mutating the source tree's vendor/ directory, which would strip dev
# dependencies and require a manual `composer install` to restore them.
#
set -euo pipefail

PLUGIN_SLUG="kenzi-chat"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BUILD_DIR="$(mktemp -d)"

trap 'rm -rf "$BUILD_DIR"' EXIT

echo "==> Copying plugin files"
rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$BUILD_DIR/$PLUGIN_SLUG/"

# Install production-only deps in the build copy (not the source tree).
# This prevents stripping dev dependencies from the developer's local vendor/.
echo "==> Installing production dependencies"
composer install --no-dev --optimize-autoloader --working-dir="$BUILD_DIR/$PLUGIN_SLUG" --quiet

# Remove dev-only composer files from the build
rm -f "$BUILD_DIR/$PLUGIN_SLUG/composer.json"
rm -f "$BUILD_DIR/$PLUGIN_SLUG/composer.lock"

ZIP_FILE="$PLUGIN_DIR/$PLUGIN_SLUG.zip"
echo "==> Creating $ZIP_FILE"
(cd "$BUILD_DIR" && zip -rq "$ZIP_FILE" "$PLUGIN_SLUG/")

echo "==> Built: $ZIP_FILE"
