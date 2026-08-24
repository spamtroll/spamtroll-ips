#!/bin/bash
#
# Runs the hook tests against the hooks as they were before the fix, and
# insists that they fail.
#
# A test that passes on the broken code is not a regression test, it is
# decoration. The hook harness reads its files from $SPAMTROLL_HOOKS_DIR, so
# the same tests can be pointed at the pre-fix versions without touching the
# working tree.
#
# Usage: bash dev/prove-regression.sh [baseRef]     (default: main)

set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

BASE_REF="${1:-main}"
OUT="build/regression-hooks"

rm -rf "$OUT"
mkdir -p "$OUT"

for hook in Comment Member; do
  if ! git show "${BASE_REF}:hooks/${hook}.php" > "${OUT}/${hook}.php" 2>/dev/null; then
    echo "Cannot read hooks/${hook}.php at ${BASE_REF}" >&2
    exit 2
  fi
done

echo "Hooks from ${BASE_REF} written to ${OUT}. Running the suite against them."
echo

SPAMTROLL_HOOKS_DIR="$OUT" vendor/bin/pest \
  tests/Hooks tests/Unit/HookHygieneTest.php tests/Unit/SuiteSignatureTest.php \
  --colors=never
status=$?

echo
if [ "$status" -eq 0 ]; then
  echo "REGRESSION PROOF FAILED: the suite passes against the pre-fix hooks."
  echo "Those tests do not distinguish the fix from the defect."
  exit 1
fi

echo "Regression proof holds: the suite is red against ${BASE_REF}'s hooks and green against the current ones."
