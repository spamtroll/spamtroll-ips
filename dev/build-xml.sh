#!/bin/bash
#
# Generates data/build.xml from the JSON manifests.
#
# IPS's application installer validates the uploaded archive against
# data/build.xml — a concise XML manifest listing every module, hook
# and setting the app ships. Normally this file is produced by IPS's
# own "Build" action in the ACP developer mode. We never run that in
# CI, so the archive was rejected with "not a valid application".
#
# This script is idempotent: pass no arguments and it rewrites
# data/build.xml to match the current JSON files.
#
# Requires jq.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
DATA_DIR="data"

out="$DATA_DIR/build.xml"
{
  printf '<?xml version="1.0" encoding="UTF-8"?>\n<build>\n'

  # Modules: from modules.json
  if [ -f "$DATA_DIR/modules.json" ]; then
    jq -r '
      to_entries[] as $scope |
      $scope.value | to_entries[] |
      "<module key=\"\($scope.key)/\(.key)\"><![CDATA[" +
        ({default_controller: (.value.default_controller // ""),
          protected: ((.value.protected // 0) == 1),
          default: (if (.value.default // 0) == 1 then true else null end)}
         | tojson) +
      "]]></module>"
    ' "$DATA_DIR/modules.json" | sed 's|^| |'
  fi

  # Hooks: from hooks.json
  if [ -f "$DATA_DIR/hooks.json" ]; then
    jq -r '
      to_entries[] |
      "<hook key=\"\(.key)\"><![CDATA[" +
        ({type: .value.type, class: .value.class} | tojson) +
      "]]></hook>"
    ' "$DATA_DIR/hooks.json" | sed 's|^| |'
  fi

  # Settings: from settings.json (array form)
  if [ -f "$DATA_DIR/settings.json" ]; then
    jq -r '
      .[] |
      "<setting key=\"\(.key)\"><![CDATA[" +
        ({key: .key, default: (.default // "")} | tojson) +
      "]]></setting>"
    ' "$DATA_DIR/settings.json" | sed 's|^| |'
  fi

  # Tasks: from tasks.json, a map of key -> frequency.
  #
  # This used to iterate `.[]`, which yields the *values* — so `.key` was
  # looked up on a frequency string and jq failed. The failure was then
  # swallowed by `2>/dev/null … || true`, defeating the `set -euo pipefail`
  # above, and data/build.xml simply had no <task> element at all.
  if [ -f "$DATA_DIR/tasks.json" ]; then
    jq -r '
      to_entries[] |
      "<task key=\"\(.key)\"><![CDATA[" +
        ({key: .key, frequency: .value} | tojson) +
      "]]></task>"
    ' "$DATA_DIR/tasks.json" | sed 's|^| |'
  fi

  # Widgets: from widgets.json, a map of key -> definition. Same defect as
  # tasks — the generated element read key="null".
  if [ -f "$DATA_DIR/widgets.json" ]; then
    jq -r '
      to_entries[] |
      "<widget key=\"\(.key)\"><![CDATA[" + (.value | tojson) + "]]></widget>"
    ' "$DATA_DIR/widgets.json" | sed 's|^| |'
  fi

  printf '</build>\n'
} > "$out"

echo "Wrote $out"
