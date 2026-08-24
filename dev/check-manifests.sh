#!/bin/bash
#
# Consistency gate for the data/*.json manifests.
#
# The IPS Suite reads these files at install time and never validates them
# against the code that ships alongside. An entry pointing at a file that was
# renamed, or a class that was never written, fails silently: the hook is not
# registered, the extension is not loaded, the setting is not created. Every
# defect this script checks for has actually shipped in this repository at
# least once.
#
# Requires jq.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

fail=0
note() { printf '  %s\n' "$1"; }
bad() { printf 'FAIL  %s\n' "$1"; fail=1; }
ok() { printf 'ok    %s\n' "$1"; }

# ---------------------------------------------------------------- 1. hooks
# hooks/*.php <-> data/hooks.json. installHooks() iterates hooks.json and
# inserts one core_hooks row per entry (SUITE-FACTS U1); a file with no entry
# is dead code, an entry with no file is a hook the Suite tries to eval and
# cannot find.
declared=$(jq -r 'keys[]' data/hooks.json | sort)
onDisk=$(find hooks -maxdepth 1 -name '*.php' -exec basename {} .php \; | sort)

if [ "$declared" = "$onDisk" ]; then
  ok "hooks/ matches data/hooks.json"
else
  bad "hooks/ and data/hooks.json disagree"
  note "declared: $(echo "$declared" | tr '\n' ' ')"
  note "on disk : $(echo "$onDisk" | tr '\n' ' ')"
fi

# Every hook must still carry the `//<?php` opener and the `_HOOK_CLASS_`
# placeholder — without them the Suite's eval() either fails to parse or
# extends nothing (SUITE-FACTS U12).
while IFS= read -r hook; do
  if [ "$(head -c 7 "$hook")" != "//<?php" ]; then
    bad "$hook does not start with //<?php"
  fi
  if ! grep -q '_HOOK_CLASS_' "$hook"; then
    bad "$hook has no _HOOK_CLASS_ placeholder"
  fi
done < <(find hooks -maxdepth 1 -name '*.php')

# ------------------------------------------------------------- 2. settings
# data/settings.json <-> the $defaults table in setup/install.php. A key that
# reaches the AdminCP form but never reaches core_sys_conf_settings is
# discarded by Settings::changeValues() without a word (SUITE-FACTS U10).
settingsJson=$(jq -r '.[].key' data/settings.json | sort)
settingsInstall=$(sed -n "/\\\$defaults = \[/,/^        \];/p" setup/install.php \
  | grep -oE "'spamtroll_[a-z_]+'" | tr -d "'" | sort)

if [ "$settingsJson" = "$settingsInstall" ]; then
  ok "data/settings.json matches setup/install.php defaults"
else
  bad "data/settings.json and setup/install.php disagree"
  note "only in settings.json: $(comm -23 <(echo "$settingsJson") <(echo "$settingsInstall") | tr '\n' ' ')"
  note "only in install.php  : $(comm -13 <(echo "$settingsJson") <(echo "$settingsInstall") | tr '\n' ' ')"
fi

# Every setting the AdminCP form renders or writes must be installed, or the
# admin's choice vanishes on save.
while IFS= read -r key; do
  if ! echo "$settingsJson" | grep -qx "$key"; then
    bad "modules/admin/spamtroll/settings.php uses '$key', which data/settings.json does not declare"
  fi
done < <(grep -ohE "Settings::i\(\)->spamtroll_[a-z_]+|\\\$values\['spamtroll_[a-z_]+'\]" modules/admin/spamtroll/settings.php \
  | grep -oE 'spamtroll_[a-z_]+' | sort -u)

# ----------------------------------------------------------- 3. extensions
# Application::extensions() iterates `name => FQCN` (SUITE-FACTS U2). Empty
# objects, or a class name with no file behind it, mean the extension never
# runs — no member-delete cleanup, no uninstall cleanup.
extensionCount=$(jq -r '[.[] | .[] | length] | add // 0' data/extensions.json)
if [ "$extensionCount" -eq 0 ]; then
  bad "data/extensions.json declares no extension classes at all"
fi

# Unit separator rather than a tab: jq's @tsv escapes backslashes, and every
# one of these values is a namespaced class name.
while IFS=$'\x1f' read -r group extension name fqcn; do
  if [ -z "$fqcn" ] || [ "$fqcn" = "null" ]; then
    bad "extensions.json: $group/$extension/$name has no class"
    continue
  fi
  relative="${fqcn#IPS\\spamtroll\\}"
  path="${relative//\\//}.php"
  if [ ! -f "$path" ]; then
    bad "extensions.json: $fqcn -> $path does not exist"
    continue
  fi
  short="${fqcn##*\\}"
  if ! grep -qE "^(final )?class _${short}\b" "$path"; then
    bad "extensions.json: $path does not declare class _${short}"
    continue
  fi
  ok "extensions.json: $fqcn"
done < <(jq -r 'to_entries[] as $g | $g.value | to_entries[] as $e | $e.value | to_entries[]
  | "\($g.key)\u001f\($e.key)\u001f\(.key)\u001f\(.value)"' data/extensions.json)

# ------------------------------------------------------------- 4. versions
# The highest key in versions.json is the application's long version. The CLI
# installer used to carry its own literal, so a fresh install reported 1.0.0
# while the upgrade steps for 1.0.1 and 1.0.2 had already been skipped.
longVersion=$(jq -r 'keys | map(tonumber) | max' data/versions.json)
humanVersion=$(jq -r --arg k "$longVersion" '.[$k]' data/versions.json)

if grep -qE "'app_(long_)?version' => '?[0-9]" setup/cli-install.php; then
  bad "setup/cli-install.php hardcodes a version instead of reading data/versions.json"
else
  ok "setup/cli-install.php takes its version from data/versions.json"
fi

appVersionTag=$(grep -oE '@version +[0-9]+\.[0-9]+\.[0-9]+' Application.php | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
if [ "$appVersionTag" != "$humanVersion" ]; then
  bad "version drift: Application.php @version is $appVersionTag, versions.json says $humanVersion"
else
  ok "Application.php @version matches versions.json"
fi

constVersion=$(grep -oE "const VERSION = '[^']+'" Application.php | sed "s/.*'\(.*\)'/\1/")
constLong=$(grep -oE 'const VERSION_LONG = [0-9]+' Application.php | grep -oE '[0-9]+')
if [ "$constVersion" = "$humanVersion" ] && [ "$constLong" = "$longVersion" ]; then
  ok "Application::VERSION ($constVersion / $constLong) matches versions.json"
else
  bad "version drift: Application::VERSION is $constVersion/$constLong, versions.json says $humanVersion/$longVersion"
fi

# Every version above the first needs an upgrade step, or the Suite has
# nothing to run when it walks an install forward.
for v in $(jq -r 'keys[]' data/versions.json); do
  if [ "$v" = "10000" ]; then
    continue
  fi
  if [ ! -f "setup/upgrade/${v}/upgrade.php" ]; then
    bad "versions.json declares $v but setup/upgrade/${v}/upgrade.php does not exist"
  fi
done

# ----------------------------------------------------------------- 5. tasks
# Tasks come from data/tasks.json (SUITE-FACTS U9), one file per key.
while IFS= read -r taskKey; do
  if [ ! -f "tasks/${taskKey}.php" ]; then
    bad "tasks.json declares '$taskKey' but tasks/${taskKey}.php does not exist"
  fi
done < <(jq -r 'keys[]' data/tasks.json)

if [ "$fail" -ne 0 ]; then
  echo
  echo "Manifest check failed."
  exit 1
fi

echo
echo "All manifests consistent."
