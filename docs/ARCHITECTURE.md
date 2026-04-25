# Architecture

This document explains *why* the IPS plugin is structured the way it
is. For day-to-day setup, see [CONTRIBUTING.md](CONTRIBUTING.md).

## High-level flow

```
                 Member writes a post / replies / registers / etc.
                                       │
                                       ▼
         ┌──────────────────────────────────────────────────┐
         │  IPS framework calls the hooked method (e.g.    │
         │  \IPS\Content\Comment::create or                 │
         │  \IPS\Member::spamService).                      │
         └──────────────────────────────────────────────────┘
                                       │
                                       ▼
         ┌──────────────────────────────────────────────────┐
         │  hooks/Comment.php / hooks/Member.php intercept │
         │   and call \IPS\spamtroll\Application::          │
         │     - isEnabled()                                │
         │     - shouldBypass($member)                      │
         │     - apiClient()->checkSpam($request)           │
         │     - determineStatus($score)                    │
         │     - determineAction($score)                    │
         │     - log(...)                                   │
         └──────────────────────────────────────────────────┘
                                       │
                                       ▼
         ┌──────────────────────────────────────────────────┐
         │  apiClient() returns a single Spamtroll\Sdk\     │
         │   Client built once per request, configured with │
         │   IpsHttpClient (so Spamtroll calls flow through │
         │   \IPS\Http\Url and inherit IPS HTTP settings).  │
         └──────────────────────────────────────────────────┘
                                       │
                                       ▼
         ┌──────────────────────────────────────────────────┐
         │  Hook applies the verdict:                       │
         │    block / moderate / warn / allow               │
         │  via $result->hide(null) / IPS spamService codes.│
         └──────────────────────────────────────────────────┘
```

## Why the SDK lives outside the plugin

`spamtroll/php-sdk` is a separate Composer package with **zero
runtime dependencies**. The IPS plugin treats the SDK like any other
library: `composer install --no-dev` pulls the matching version into
`vendor/` at packaging time, the plugin's bootstrap loads
`vendor/autoload.php` from `Application.php`. Two reasons:

1. **Sharing.** The same SDK is used by the WordPress plugin. Bug
   fixes in the API client land in one place, not two.
2. **Testability.** `spamtroll/php-sdk` is unit-testable in isolation
   (PHP 8.0 + ext-curl is enough). The plugin doesn't have to mock
   HTTP — it injects an IPS-flavoured `HttpClientInterface` adapter.

## Why hooks are excluded from PHPStan

`hooks/Comment.php` and `hooks/Member.php` start with `//<?php`. That's
a literal HTML comment, **not** a PHP tag. The IPS hook preprocessor
rewrites the first line before each `include`, swapping in a real
`<?php` plus `extends _HOOK_CLASS_` resolution.

PHPStan parses files as PHP. With `//<?php` it sees inline HTML, no
class declaration, nothing to analyse. We exclude these two files in
`phpstan.neon` rather than fight the framework. The trade-off:

- **Cost:** PHPStan can't catch a bug in the hook bodies. Roughly 250
  LOC across the two files, mostly try/catch + delegation to
  `Application::*` helpers (which **are** analysed).
- **Mitigation:** the hooks are intentionally thin — every non-trivial
  decision (bypass logic, action policy, log payload) lives in
  `Application::*` static methods that PHPStan does check, and the
  `tests/Unit/ApplicationTest.php` Pest suite covers them.

## Why `\IPS\spamtroll\Application` exists at all

IPS resolves `class _Application extends \IPS\Application` (the
declaration in `Application.php`) into the runtime FQCN
`\IPS\spamtroll\Application` via its custom autoloader. PHPStan
doesn't know the autoloader, so it would otherwise see
`_Application` and `\IPS\spamtroll\Application` as two unrelated
symbols.

We bridge with `stubs/IPS.stub.php`. The stub:

- Declares `\IPS\Application` as an abstract base.
- Adds explicit properties on `\IPS\Settings` (one per
  `spamtroll_*` setting) so reads like
  `\IPS\Settings::i()->spamtroll_enabled` are typed.
- Declares `\IPS\Member`, `\IPS\Db`, `\IPS\Db\Select`,
  `\IPS\Http\Url`, etc. — every IPS class our code references.

The stub is loaded as a bootstrap (`bootstrapFiles`) rather than via
`stubFiles` because PHPStan's stub system overrides existing
classes; we need it to *introduce* IPS classes that have no other
declaration.

## How settings flow

ACP form (`modules/admin/spamtroll/settings.php`) writes to
`\IPS\Settings` via `$form->saveAsSettings($values)`. Reads happen
all over (`Application::isEnabled()`, hooks, dashboard, scanner) via
`\IPS\Settings::i()->spamtroll_*`.

The settings registered in `data/settings.json` get default values
inserted on first install via `setup/install.php::step1`. Subsequent
upgrades that *add* settings should add them to both `data/settings.json`
(for fresh installs) and a versioned upgrade handler under
`setup/upgrade/<long_version>/upgrade.php` (for existing installs).
The 1.0.2 release demonstrates the pattern: it adds
`spamtroll_bypass_min_posts` to the JSON and deletes the now-obsolete
`spamtroll_check_messages` from existing DBs.

## Score normalization (delegated to the SDK)

The SDK normalises raw API scores via
`min(1.0, raw / scoreDenominator)` with a default `scoreDenominator`
of `30`. The plugin calls `\IPS\spamtroll\Application::determineStatus($score)`
with the **already-normalised** value (0.0–1.0). The plugin's
configurable thresholds (`spamtroll_spam_threshold`,
`spamtroll_suspicious_threshold`) are also in the 0–1 space.

If the raw scoring scale ever changes, the SDK absorbs it; the
plugin's UI thresholds stay where admins set them.
