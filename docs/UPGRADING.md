# Upgrading to 1.0.4

The ZIP is a developer bundle with production dependencies, not an ACP TAR. An ordered manual 1.0.2 → 1.0.4 upgrade was verified on IPS 4.7.24. Rehearse this operator procedure on an isolated forum first.

## Before replacing files

Record the installed long version, application enabled flag and all existing Spamtroll settings. Back up the application directory, log table and Spamtroll-owned application/module/hook/task/widget/settings/language/template/CSS metadata, plus the generated hook registry. Restrict backups outside the web root: settings/logs can contain credentials and personal data. Verify backup readability and keep the previous application available for rollback.

Record the original core `cleanup` task identity. Task keys are globally unique, and that task must stay owned by `core`. Pause Spamtroll scanning while retaining its original toggle. Use a consistent snapshot or a maintenance window for row-preservation verification.

## Manual upgrade order

1. Verify the ZIP against `SHA256SUMS`, then replace the complete `applications/spamtroll/` directory, including `vendor/`, with appropriate ownership/permissions. An overlay can leave obsolete files behind.
2. From the forum root, start a fresh PHP CLI process and load `init.php`. Run every versioned handler newer than the recorded installed version in ascending order. From 1.0.2, require `setup/upg_10003/upgrade.php`, instantiate its namespaced `_Upgrade` and call `step1()`; do the same for `setup/upg_10004/upgrade.php`. Stop on failure.
3. Run `php applications/spamtroll/setup/cli-install.php` from the forum root to import language/templates/CSS. Use the native application's `installJsonData()` to synchronize metadata and installed version, rebuild the hook registry and invalidate relevant IPS caches through the native APIs. Check runtime loading in a fresh process.
4. Verify version `1.0.4` / `10004`, application enabled flag, native hooks/classes and API connection. The application's `databaseCheck()` must return an empty array. Confirm the original core `cleanup` remains unchanged and plugin task `spamtrollCleanup` is enabled.
5. Restore the original scan toggle. Compare preexisting settings and original log rows/digest with the backup. Check public forum/registration and observe real scans and errors.

Migrations must run before installer defaults are imported. Version 1.0.3 derives sensitivity, scope and legacy threshold behavior from old values. The CLI installer alone does not dispatch migrations or update an existing application's version.

Version 1.0.4 repairs comments using live types, nullability, defaults and collation, and adds the missing email-hash lookup index. DDL may lock/rebuild a large table; choose an appropriate window. Investigate failures before advancing the installed version.

## Rollback

Pause scanning, restore the complete prior application and matching Spamtroll metadata/settings, then restore the recorded schema and hook registry/caches. Preserve unrelated applications and the core task. Restore original enabled flags and verify the prior runtime and public pages.

Preserve newer log rows by reversing additive column/index and comment changes using exact pre-upgrade definitions. Replacing the table from its dump is a last-resort recovery that can discard newer scans. Never infer rollback SQL from a different forum.

Fresh ACP installation, native export/installation, ACP-driven upgrade and uninstall remain pending under T-048. See [SMOKE.md](SMOKE.md) and [SUITE-LAB.md](SUITE-LAB.md). Private messages are intentionally excluded from scanning.
