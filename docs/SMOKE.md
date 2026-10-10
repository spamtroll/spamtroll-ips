# IPS 1.0.4 verification report

## Manual production upgrade — 2026-10-10

Dogomania was manually upgraded from Spamtroll 1.0.2 / 10002 to 1.0.4 / 10004 on IPS 4.7.24, PHP 8.3.6 and MariaDB 10.11.14 with native nginx/PHP-FPM. Runtime source commit: `b2dfd39ace1d392721a812274734a059a5655eb6`.

Deployed developer-bundle SHA256: `21d8266bf8b41682a7527d14f3112d3d574e6496539cd2ab42eb207b7d9803a3`. The follow-up release adds tests/documentation and includes these guides; its ZIP has a separate published checksum. Updated documentation files have not been redeployed.

| Check | Result |
| --- | --- |
| Version/application enabled | 1.0.4 / 10004; enabled |
| Ordered migrations and native imports/metadata synchronization | Passed |
| Native database checker | Zero repair queries |
| Preexisting settings | All values preserved by hash comparison |
| Original log rows | 12,050 preserved by digest of their original columns |
| Core task | Original core `cleanup` unchanged |
| Plugin task | `spamtrollCleanup` registered/enabled |
| Native hooks/scanner/extensions | Loaded through actual IPS subclass loader |
| API connection | HTTP 200 |
| Public forum/registration | HTTP 200 after canonical www redirect |
| Controlled scanner call | Safe/allow; no account or forum content created |
| Deployed files | All 129 matched deployment bundle |
| Recovery | Scoped backup and checksum-verified rollback evidence retained outside web root |

The first rollout was rolled back after detecting the core task-key collision. The final runtime uses `spamtrollCleanup` and preserves the original core task.

## Read-only follow-up — 2026-10-10 22:28 UTC

Approximately seven hours later, native verification again passed: version/enabled, settings hashes, original log digest, class loading, core/plugin tasks, API HTTP 200 and zero schema repairs.

| Normal community traffic since deployment | Status/action | Scans |
| --- | --- | --- |
| Post | safe / allow | 15 |
| Registration | blocked / block | 2 |
| Registration | suspicious / moderate | 1 |

No new Spamtroll-related system-log entries or native HTTP error-log entries were found in that interval. External forum/registration GET requests returned HTTP 200. Recorded decisions do not prove every moderation side effect.

The retention task was enabled, idle and unlocked, with its first daily execution due 2026-10-11 15:28 UTC. It had not executed yet; successful scheduled cleanup is not established here.

## Automated checks and remaining limits

Local PHP 8.2 full QA passed: 150 tests / 415 assertions, style, PHPStan, spelling
and manifest checks. Automated coverage includes ordered upgrades from 1.0.0–1.0.3, preservation of administrator settings, repeated imports, schema metadata/collation/index repair, native subclass loading, hooks and fail-open behavior. Doubles do not establish a fresh native installation.

Fresh ACP installation, TAR export/install, ACP-driven upgrade, uninstall, real widget rendering and cross-worker datastore behavior remain pending under T-048. Private messages are intentionally excluded; the remaining native test verifies bypass. See [SUITE-LAB.md](SUITE-LAB.md).
