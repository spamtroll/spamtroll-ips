# Contributing

This page documents the development setup for the Spamtroll IPS
Community Suite application. End users install the plugin through
ACP → Applications → Install/Upload (or by extracting the tar into
`applications/spamtroll/` on a dev forum and running
`setup/cli-install.php`).

## Local setup

The plugin runs on PHP 8.0+ in production. Dev tooling requires PHP
8.3+ (Pest 2 needs 8.2+ transitively, peck needs 8.3+); CI runs the
test matrix on 8.2/8.3/8.4.

```bash
git clone https://github.com/spamtroll/spamtroll-ips.git
cd spamtroll-ips
composer install
```

Plus `aspell` + `aspell-en` for the spell-check:

```bash
sudo apt install aspell aspell-en       # Debian / Ubuntu
brew install aspell                     # macOS
```

## Quality gate

```bash
composer qa
```

In order:

1. `composer lint` — php-cs-fixer dry-run. Failure → `composer lint:fix`.
2. `composer stan` — PHPStan level 9 against the IPS-shaped stubs in
   `stubs/IPS.stub.php` plus a thin baseline (`phpstan-baseline.neon`)
   for IPS-specific magic that's not worth fixing in code.
3. `composer peck` — aspell spell-check.
4. `composer test` — Pest unit suite for the pure-logic helpers in
   `Application.php`.

CI runs the same set on every push / PR.

## Why parts of the codebase are excluded from PHPStan

The IPS framework can't be standalone-loaded — it's closed-source and
has a custom autoloader that resolves `_ClassName` to
`\IPS\<app>\<ClassName>` at runtime. We approximate IPS via:

1. `stubs/IPS.stub.php` — a hand-written file that declares the IPS
   classes/properties our code touches. The stub is loaded as a
   bootstrap so PHPStan and Pest both see the symbols.
2. `phpstan-baseline.neon` — frozen list of remaining IPS-magic
   issues that the stubs don't fully cover (mostly
   covariance/inheritance edges around `_ClassName` classes
   extending stubs). Don't add to the baseline lightly — that's the
   "noise" budget; real bugs should still surface as new errors.

The following paths are **excluded entirely** from PHPStan:

- **`hooks/*.php`** — IPS hook files start with `//<?php`, a literal
  HTML comment, not a PHP tag. The hook preprocessor rewrites it
  before include. PHPStan parses the original file and sees no PHP,
  so we don't try.
- **`dev/lang*.php`** — pure data tables (return `$lang = […];`).
  Nothing to analyse.

## Coding standards

- **PSR-12** enforced by php-cs-fixer (`@PSR12 + @PSR12:risky +
  @PHP80Migration:risky`). The PSR-1 `StudlyCaps` rule for class
  names is disabled (`class_definition: false`) because the IPS
  autoloader requires the `_ClassName` prefix on source-file
  declarations.
- **PHPStan level 9** with the stubs + baseline above.
- All non-hook files declare `strict_types=1`.

## Tests

Pest, in `tests/Unit/`. The IPS framework can't be bootstrapped, so
unit tests cover only **pure-logic helpers**:

- `Application::determineStatus()`, `determineAction()` — score → label
- `Application::shouldBypass()` — admin/group/post-count short-circuit

Real flows (hooks, ACP routing, database) need a live forum.
Integration testing is documented in `cli-install.php` + the
existing dogomania.com staging procedure.

`tests/Mocks/FakeMember` extends the `\IPS\Member` stub with the few
properties `shouldBypass()` reads. `tests/Pest.php` defines a
`settings()` helper that wraps `\IPS\Settings::i()` (a singleton in
the stub so per-test mutations stick) and resets every property
before each test.

## Release checklist

1. Bump `app_version` and add a `data/versions.json` entry +
   `setup/upgrade/<long_version>/upgrade.php` (no-op if no schema
   change).
2. Move `[Unreleased]` in `CHANGELOG.md` under a dated version.
3. `composer qa` — must be green.
4. `bash dev/build-xml.sh` regenerates `data/build.xml`.
5. `composer install --no-dev --optimize-autoloader` so the release
   tar contains only production deps.
6. Build flat tar:
   ```bash
   tar -cf /tmp/spamtroll-ips.tar -C . \
     --exclude=.git --exclude=tests --exclude=stubs \
     --exclude='.php-cs-fixer*' --exclude='phpstan*' --exclude=peck.json *
   ```
   The IPS ACP upload validation reads `phar://<archive>/data/application.json`,
   so the tar must be **flat** — no top-level `spamtroll/` directory
   wrapping the contents.
7. Commit, tag `v<version>`, push tag.
8. Test the upload on a dev forum: ACP → Applications → upload tar →
   walk through the MultipleRedirect upgrade chain → run a smoke
   scan.
