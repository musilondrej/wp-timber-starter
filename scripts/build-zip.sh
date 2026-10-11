#!/usr/bin/env bash
# Production zip: theme files + optimized Composer autoloader, no dev/source files.
# Staged in ./build/ so your local vendor/ is left untouched.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="$(basename "$ROOT")"
BUILD="$ROOT/build"
STAGE="$BUILD/$SLUG"

rm -rf "$BUILD"
mkdir -p "$STAGE"

rsync -a \
  --exclude='.DS_Store' \
  --exclude='/.git' \
  --exclude='/.gitignore' \
  --exclude='/.npmrc' \
  --exclude='/build' \
  --exclude='/node_modules' \
  --exclude='/vendor' \
  --exclude='/assets' \
  --exclude='/scripts' \
  --exclude='/stubs' \
  --exclude='/bin' \
  --exclude='/package.json' \
  --exclude='/package-lock.json' \
  --exclude='/README.md' \
  --exclude='/AGENTS.md' \
  --exclude='/CLAUDE.md' \
  --exclude='/.agents' \
  --exclude='/.claude' \
  --exclude='/.github' \
  --exclude='/.githooks' \
  --exclude='/.mcp.json' \
  --exclude='/.editorconfig' \
  --exclude='/.php-cs-fixer.dist.php' \
  --exclude='/.php-cs-fixer.cache' \
  --exclude='/phpstan.neon.dist' \
  --exclude='/.twig-cs-fixer.cache' \
  "$ROOT/" "$STAGE/"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress -d "$STAGE"

( cd "$BUILD" && zip -rq "$SLUG.zip" "$SLUG" )
rm -rf "$STAGE"

echo "Built $BUILD/$SLUG.zip"
