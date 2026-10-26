#!/usr/bin/env bash
# Runs the performance tests against the application code as delivered (tag as-delivered) and checks
# that each of the five findings' tests fails there, i.e. that every slow spot was there before the fixes.
# Needs a clean tree under the paths below, a full clone (the tag) and `composer install`; the tree is
# put back to HEAD on exit.
set -euo pipefail
cd "$(dirname "$0")/.."

PATHS=(app bootstrap config database routes resources)
git diff --quiet HEAD -- "${PATHS[@]}" || { echo "uncommitted changes under ${PATHS[*]}: commit or stash first"; exit 1; }
added=$(git diff --name-only --diff-filter=A as-delivered HEAD -- "${PATHS[@]}")
removed=$(git diff --name-only --diff-filter=D as-delivered HEAD -- "${PATHS[@]}")
restore() {
  git checkout -q HEAD -- "${PATHS[@]}"
  for f in $removed; do rm -f "$f"; done
  rm -f junit-as-delivered.xml
}
trap restore EXIT

git checkout -q as-delivered -- "${PATHS[@]}"
# Files the fixes added (e.g. a migration) did not exist in the delivered app.
for f in $added; do rm -f "$f"; done

php artisan test --filter=PerformanceReviewTest --log-junit junit-as-delivered.xml >/dev/null 2>&1 || true

# One line per performance test: "failed" for a failed assertion (over budget), "error" for a crash
# (the test could not run on that code, which proves nothing), "passed" otherwise.
results=$(php -r '
  $xml = simplexml_load_file($argv[1]);
  foreach ($xml->xpath("//testcase") as $case) {
      $state = count($case->error) > 0 ? "error" : (count($case->failure) > 0 ? "failed" : "passed");
      echo $case["name"], " ", $state, PHP_EOL;
  }' junit-as-delivered.xml)

status=0
for t in test_1_ test_2_ test_3_ test_4_ test_5_; do
  line=$(grep "^${t}" <<<"$results" || true)
  case "$line" in
    *" failed") echo "as delivered, over budget: ${line% failed}" ;;
    *" passed") echo "as delivered, test passed (slow spot did not reproduce): ${line% passed}"; status=1 ;;
    *" error") echo "as delivered, test crashed (proves nothing): ${line% error}"; status=1 ;;
    *) echo "missing: ${t}"; status=1 ;;
  esac
done
exit $status
