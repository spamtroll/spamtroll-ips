# Spamtroll IPS 1.0.4 — developer prerelease

Fixes the log-column metadata warning, native IPS upgrade discovery, scanner class-loading failures and a cleanup-task collision with the core application.

## Package

Download `spamtroll-ips-1.0.4-dev.zip` and verify it against `SHA256SUMS`. The ZIP includes production dependencies, templates and operator guides; it contains no licensed IPS files. This is a developer bundle, **not an ACP-uploadable TAR**. Do not upload it through Applications or Plugins.

## Upgrade and rollback

Follow [the ordered upgrade guide](https://github.com/spamtroll/spamtroll-ips/blob/v1.0.4/docs/UPGRADING.md): back up the application and scoped metadata/logs, run applicable migrations before defaults, synchronize native metadata, then verify settings/schema and core/plugin tasks. The CLI installer alone does not dispatch upgrades.

A manual 1.0.2 → 1.0.4 upgrade passed on IPS 4.7.24 with zero repair queries, preserved settings and all 12,050 original log rows. Follow-up found 18 real traffic scans and no new plugin errors. See [the verification report](https://github.com/spamtroll/spamtroll-ips/blob/v1.0.4/docs/SMOKE.md).

## Compatibility

Local full QA passed 150 tests / 415 assertions plus style, PHPStan, spelling and manifest checks. CI covers PHP 8.2/8.3/8.4. Regression checks cover upgrades from 1.0.0–1.0.3, setting preservation, schema repair, native subclass loading and API fail-open behavior. Posts and registrations are scanned; private messages are excluded.

Fresh native ACP installation, ACP-driven upgrade/export and uninstall still need an isolated test forum. IPS 5 is unverified. The developer prerelease label remains until native distribution checks are completed.
