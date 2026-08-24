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

## How the hooks are analysed and tested

`hooks/Comment.php` and `hooks/Member.php` start with `//<?php`. That is a
literal comment, **not** a PHP tag — and it has to be, because the Suite
does not `include` a hook, it `eval()`s it, and `eval()` rejects an opening
tag. Before evaluating, the Suite prepends a namespace and substitutes the
parent class name (`docs/SUITE-FACTS.md`, U12):

```php
$contents = "namespace {$namespace}; " . str_replace('_HOOK_CLASS_', $realClass, file_get_contents($file));
@eval($contents);
```

Both the analyser and the test suite reproduce that transformation rather
than working around it, from one place — `tests/Support/HookTransform.php`:

- `dev/preprocess-hooks.php` writes the result to `build/hooks-preprocessed/`,
  which is what `phpstan.neon` analyses. `composer stan` runs it first. The
  hooks are covered by the same level-9 rules as everything else.
- `tests/Support/HookHarness.php` `eval()`s the same string over an
  instrumented stand-in for the framework class, then closes the chain with
  `class Member extends {$lastHook} {}` the way `init.php` does (U12d). The
  tests in `tests/Hooks/` therefore exercise the file that ships, not an
  impression of it. `$SPAMTROLL_HOOKS_DIR` points the harness elsewhere,
  which is how `dev/prove-regression.sh` runs the suite against the pre-fix
  hooks and requires it to fail.

Two things follow from this that are easy to undo by accident, so both are
asserted in `tests/Unit/HookHygieneTest.php`: the leading `//` stays, and
every class reference inside a hook is fully qualified (the harness compiles
into a per-run namespace so one file can be loaded more than once).

## Why the hooks contain almost nothing

A hook calls its parent once, outside any `try`, and then calls one method
on `\IPS\spamtroll\Scanner\Gateway`. That is the entire file.

Fail-open used to be a matter of remembering the right `catch` in each hook,
and the two hooks did not agree: `Member.php` caught `SpamtrollException`
inside and `\RuntimeException` outside, so a `\Error` — what a package
shipped without `vendor/` produces — escaped `spamService()` and nobody
could register, while `Comment.php` caught `\Throwable` and shrugged the
same failure off. Moving the boundary into `Gateway` makes it one thing that
either works or does not, and it is covered by a matrix
(`tests/Scanner/FailOpenMatrixTest.php`) with a reflective check that no
public scan path escapes it.

The hooks also no longer carry the framework's passthrough boilerplate —
`catch (\RuntimeException)` followed by calling the parent again through
`call_user_func_array`. That wrapper is written for a method that only
forwards; around a body that does anything else it duplicates whatever the
parent did, which here meant a second post row.

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
