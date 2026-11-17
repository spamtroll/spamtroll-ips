#!/usr/bin/env bash
# Serialize the same manifest snapshot as native IPS Application::buildJsonData.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
php dev/build-xml.php
