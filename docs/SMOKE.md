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


## Native production checks on dogo — 2026-11-17

T-048 resumed using the authorized SSH endpoint `root@dogo:2022`. The identified
installation is the live community at `/home/dogo/public_html/forum`; no separate
IPS test installation was found under `/home`, `/opt`, `/var/www` or `/root`.
The following checks ran at 10:08:53 UTC with the real IPS subclass loader.
No accounts, posts or private messages were created, and no application settings
were changed. SQL diagnostics used a read-only transaction. Widget rendering used
an existing frontend theme and a separate transaction that was rolled back; normal
cache generation was the only allowed write activity.

| Native check | Result |
| --- | --- |
| Suite/application/runtime | IPS 4.7.24; Spamtroll 1.0.4 / 10004 enabled; PHP 8.3.6; MariaDB 10.11.14 |
| Schema | Zero repair queries; all 14 log columns present |
| Hooks | Comment and Member hooks installed and present in native inheritance chains |
| Class loading | All 10 checked classes loaded, including scanner, widget, cleanup and lifecycle extensions |
| Registration routing | Registration checking enabled and reachable through native spam service |
| Scheduled cleanup | Dedicated enabled `spamtrollCleanup`; original core `cleanup` preserved |
| API authentication/status | Configured client GET `/scan/status`: HTTP 200, success |
| Private-message exclusion | Unsaved real IPS Conversation returned allow / private_message / scanned=false |
| Diagnostics over seven days | Seven routine retention-cleanup messages; zero other matching plugin diagnostics; zero matching HTTP error records |
| Public forum/registration/API health | HTTP 200 with strict TLS validation from the workstation |
| Statistics widget | Failed: native initialization/rendering raises `ErrorException: template_store_missing` |

The private-message probe establishes the native gateway exclusion branch; it does
not establish a full browser message-send lifecycle or member side effects.

Retained seven-day scan decisions totaled 1,076:

| Content | Status/action | Count |
| --- | --- | ---: |
| Post | safe / allow | 1,055 |
| Post | blocked / block | 2 |
| Post | suspicious / moderate | 4 |
| Registration | safe / allow | 8 |
| Registration | blocked / block | 3 |
| Registration | suspicious / moderate | 4 |

These are recorded scan decisions, not a new controlled verification of every
posting, moderation or registration side effect. Current settings were unchanged
across diagnostics by an in-memory value hash; no secrets or member content were
included in the receipts.

All 41 selected runtime, manifest and migration files match deployed source
`b2dfd39ace1d392721a812274734a059a5655eb6`. Four differ from checkout `c5881b9`:
`Application.php`, `setup/upg_10002/upgrade.php`, `setup/upg_10003/upgrade.php` and
`widgets/spamtrollStats.php`. The later T-101 changes have not been deployed.
This comparison does not assert that every installed file was rechecked.

The widget failure is corroborated by native template metadata: the application
has only `admin/spamtroll/widgetStats`, whereas native `Widget::init()` requests
`front/widgets/spamtrollStats`. Track its correction and native rendering/package
verification in canonical T-160. The missing frontend template is also absent
from current plugin source; the T-101 widget exception guard does not add it.

The dogo host resolves the forum through an origin presenting an old self-signed
certificate. This prevented strict host-local forum curl checks; independent
public HTTPS verification passed, with the public certificate valid through
2027-02-14. This origin-only observation is not evidence of a public TLS outage.

Sanitized receipts remain outside the web root in
`/tmp/spamtroll-T048-codex-6711/{native-runtime,source-comparison}.json` on dogo
and the workstation. No production application deployment took place. Native ACP
fresh install/upgrade, TAR export/install, controlled failure injection, full
browser posting/registration/PM flows, cross-worker breaker behavior, member
removal and uninstall remain pending on an isolated licensed test forum. T-048
therefore remains open.


## Widget candidate repair — 2026-11-17 (T-160)

Source `1a5479d` adds the native `dev/html/front/widgets/spamtrollStats.phtml`
resource and a manifest regression covering every declared widget's native path.
Existing admin markup and the T-101 statistics fail-open guard remain available.
PHP 8.2 full QA passed: 163 tests / 476 assertions, formatting, PHPStan, spelling
and manifest checks. The developer ZIP includes the exact candidate template
(SHA256 `64bb63ce08057e6cff3b621078f1deaf8ff7ec23c358382809fcfe2f947441d8`).

On dogo at 2026-11-17T20:17:50Z, the real IPS 4.7.24 template compiler compiled
and rendered this resource with native language expansion: vertical, horizontal
and empty-statistics cases all passed. The diagnostic used a rolled-back SQL
transaction and did not install or deploy application code. Receipt:
`/tmp/spamtroll-T160-codex-6711/native-template.json` on dogo.

This establishes native compiler compatibility and developer-bundle inclusion.
The corrected resource still needs native application import/package verification
and rollout alongside T-101; that release work continues in T-161 and the broader
T-048 lifecycle checks.
