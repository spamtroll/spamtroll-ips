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
- Content-type filters in logs view (Posts, Messages, Registrations) using existing lang strings
- Null/guest guard in Post and Message hooks to prevent crashes with null authors
- Empty content check in Member hook before API call
- API client retry logic (max 3 attempts with backoff) for 5xx errors and connection failures
- HTTP status code handling in API client: 401 throws `invalidApiKey`, 429 returns error without retry, 5xx retries
- Timeout vs connection failure distinction in API client catch block
- JSON encoding validation in API client before POST requests
- Numeric validation for spam score in API Response
- Threshold bounds checking (0.0-1.0 clamping) in `determineStatus()` and `determineAction()`
- JSON encode failure safety in `Application::log()`
- Error logging in Uninstall extension catch blocks

### Changed
- Dashboard controller now uses `dashboard.phtml` template instead of inline HTML (`buildDashboardHtml()` removed)
- Dashboard chart (Chart.js) is now functional — chart data passed as template parameters
- Settings controller now uses `settings.phtml` template for test connection button
- Settings `testConnection()` reads `api_key`/`api_url` from request to test unsaved values
- Unified CSS class `.spamtroll-content-preview` (removed duplicate `.spamtroll-content-preview-php`)
- Task key in `data/tasks.json` changed from `spamtrollCleanup` to `cleanup` to match class name
- Merged `Installing_Plugin.md` into `README.md` (comprehensive installation, configuration, troubleshooting, and uninstallation guide)
- Extracted inline CSS from dashboard controller to `dev/css/admin/spamtroll.css`
- Extracted inline JavaScript from settings controller to `dev/js/admin/spamtroll.js`

### Removed
- `Installing_Plugin.md` (content moved to README.md)
- Inline `<style>` blocks from dashboard controller
- Inline `<script>` blocks from settings controller
- `buildDashboardHtml()` method from dashboard controller (~140 lines of inline HTML)

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
