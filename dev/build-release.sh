#!/usr/bin/env bash
# Developer bundle only. A native ACP TAR must be exported by IPS itself.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
version="$(sed -nE "s/.*const VERSION = '([^']+)'.*/\1/p" Application.php)"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Invalid application version' >&2; exit 1; }
stage="$(mktemp -d "${TMPDIR:-/tmp}/spamtroll-ips-release.XXXXXX")"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/spamtroll/dev" build
for path in Application.php data extensions hooks modules setup sources tasks widgets LICENSE README.md CHANGELOG.md docs composer.json; do
  cp -R "$path" "$stage/spamtroll/"
done
for path in dev/html dev/js dev/css dev/lang.php dev/lang_pl.php dev/lang_de.php dev/jslang.php dev/index.html; do
  cp -R "$path" "$stage/spamtroll/dev/"
done
# A local lock, when available, pins exactly the dependencies tested for this build.
if [[ -f composer.lock ]]; then cp composer.lock "$stage/spamtroll/"; fi
composer install --working-dir="$stage/spamtroll" --no-dev --no-scripts --no-plugins --prefer-dist --optimize-autoloader --no-interaction --quiet
rm -f "build/spamtroll-ips-${version}-dev.zip"
(cd "$stage" && zip -qr "$OLDPWD/build/spamtroll-ips-${version}-dev.zip" spamtroll)
echo "Built build/spamtroll-ips-${version}-dev.zip (developer bundle; not ACP TAR)"
