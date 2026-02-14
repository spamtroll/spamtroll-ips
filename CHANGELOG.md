# Changelog

All notable changes to the IPS Community Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- MIT License file
- MemberSync extension (`extensions/core/MemberSync/`) for cleaning up logs on member deletion/merge
- Uninstall extension (`extensions/core/Uninstall/`) for proper cleanup on application removal
- Extensions registry (`data/extensions.json`)
- Installation setup script (`setup/install.php`)
- ACP search keywords (`data/acpsearch.json`) for finding Spamtroll settings via admin search bar
- Friendly URL definitions (`data/furl.json`)
- Spamtroll Statistics widget (`widgets/spamtrollStats.php`, `data/widgets.json`)
- Widget template (`dev/html/admin/spamtroll/widgetStats.phtml`)
- JavaScript language strings (`dev/jslang.php`) for translatable JS-facing text
- External CSS file (`dev/css/admin/spamtroll.css`) extracted from inline styles
- External JavaScript file (`dev/js/admin/spamtroll.js`) extracted from inline scripts

### Changed
- Merged `Installing_Plugin.md` into `README.md` (comprehensive installation, configuration, troubleshooting, and uninstallation guide)
- Extracted inline CSS from dashboard controller to `dev/css/admin/spamtroll.css`
- Extracted inline JavaScript from settings controller to `dev/js/admin/spamtroll.js`

### Removed
- `Installing_Plugin.md` (content moved to README.md)
- Inline `<style>` blocks from dashboard controller
- Inline `<script>` blocks from settings controller

## [0.1.0] - 2026-02-09

### Added
- IPS Community (Invision Power Suite) integration plugin
- Content scanning hooks for posts, messages, and member registration
- Admin settings module for API configuration
- Admin dashboard with scan statistics
- Admin logs viewer with detailed scan results
- Spamtroll API client library (`sources/Api/Client.php`)
- API response and exception handling classes
- Background cleanup task for old scan logs
- Installation guide with step-by-step instructions
- README with configuration and usage documentation
