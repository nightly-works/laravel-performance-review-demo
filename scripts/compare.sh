#!/usr/bin/env bash
# Measures the heavy pages on the app as delivered (tag as-delivered) and on the fixed app, with the same
# 20,000-order shop (scripts/measure.php), and prints both tables. Same tree rules as prove-flaws.sh.
set -euo pipefail
cd "$(dirname "$0")/.."

PATHS=(app bootstrap config database routes resources)
git diff --quiet HEAD -- "${PATHS[@]}" || { echo "uncommitted changes under ${PATHS[*]}: commit or stash first"; exit 1; }
added=$(git diff --name-only --diff-filter=A as-delivered HEAD -- "${PATHS[@]}")
removed=$(git diff --name-only --diff-filter=D as-delivered HEAD -- "${PATHS[@]}")
restore() {
  git checkout -q HEAD -- "${PATHS[@]}"
  for f in $removed; do rm -f "$f"; done
}
trap restore EXIT

echo "As delivered"
echo
git checkout -q as-delivered -- "${PATHS[@]}"
for f in $added; do rm -f "$f"; done
php -d memory_limit=-1 scripts/measure.php
restore

echo
echo "After the fixes"
echo
php -d memory_limit=-1 scripts/measure.php
