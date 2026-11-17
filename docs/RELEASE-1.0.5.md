# IPS 1.0.5 release candidate

IPS4.7.x candidate; verified runtime IPS4.7.24/PHP8.3.6/MariaDB10.11.14.
IPS5 support is not claimed. Private messages bypass scanning.

The native TAR is built by IPS Application::build and BuilderIterator and
contains the production PHP SDK, native theme/language/JavaScript resources,
1.0.5 metadata and additive repair migration. Install/update this TAR through
ACP -> System -> Applications. Keep an application/database backup before an
upgrade. Developer ZIPs are for isolated developer installs, not ACP uploads;
use the native TAR/ACP path for upgrades of existing installations.

Changes include the native frontend statistics widget template, repaired legacy
settings/hash-index updates without overwriting administrator values, strict
group bypass matching, UTC/fail-open statistics, malformed-response diagnostics
and complete native CLI uninstall lifecycle. The CLI uninstaller removes the
application directory in normal production mode as ACP does.

See SMOKE.md for native lifecycle, functional, browser and rollout evidence.
Exact package/source checksums and the final release decision belong to the
release record; do not infer publication from a candidate file in build/.

The unavailable /ips/version update-check URL has been removed from application
metadata. Native updates use the ACP TAR upload flow; no automatic update feed
is advertised until a supported published feed exists.


Final candidate source: b271a49d0e51876ea319a6f731c30adb3f72a6ad.
Final native TAR SHA256: de52b7f717c13a422233fae3d6fba65f3131b7682c8d596f48749375bfd5d39c.
The candidate is deployed on Dogomania and all132installed files match it.
Its GitHub release remains a draft with verified native TAR/devZIP/checksums;
publication is a separate release step. See the final SMOKE.md section for the
exact runtime, complete tests, production evidence and cleanup boundaries.
