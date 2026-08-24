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
   `stubs/IPS.stub.php` plus a baseline (`phpstan-baseline.neon`) for IPS
   magic that isn't worth fixing in code. Runs `dev/preprocess-hooks.php`
   first, so `hooks/*.php` are analysed too.
3. `composer peck` — aspell spell-check.
4. `composer manifests` — `dev/check-manifests.sh`. Cross-checks
   `data/*.json` against the code it names: hooks against `hooks/`,
   settings against `setup/install.php` and the AdminCP form, every
   `extensions.json` class against a file that declares it, versions
   against each other. Each check exists because that manifest has been
   wrong at least once, and the Suite validates none of them.
5. `composer test` — Pest. Four layers: pure logic (`tests/Unit`), the
   fail-open matrix over the real SDK and a fake network
   (`tests/Scanner`), the hook files compiled the way the Suite compiles
   them (`tests/Hooks`), and — outside CI — the live-forum procedure.

CI runs the same set on every push / PR, plus a job that regenerates
`data/build.xml` and diffs it.

`composer regression` is not part of the gate. It points the hook suite at
the hooks as they were before a fix (`$SPAMTROLL_HOOKS_DIR`) and fails if
they pass — worth running when you add a test for a defect, to check the
test can tell the difference.

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

`stubs/aliases.php` registers the `_Foo` → `Foo` aliases the IPS autoloader
would create, so PHPStan, Pest and peck all resolve
`\IPS\spamtroll\Scanner\Gateway` instead of guessing.

`hooks/*.php` used to be excluded — they start with `//<?php`, which is a
comment rather than a tag, so PHPStan saw no PHP. They are analysed now:
`composer stan` runs `dev/preprocess-hooks.php`, which applies the Suite's
own transformation and writes the result to `build/hooks-preprocessed/`.
See `docs/ARCHITECTURE.md` for the mechanism.

One path is still **excluded entirely**:

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

Pest, in four layers. The Suite can't be bootstrapped in CI, but that turns
out to constrain far less than it looks.

1. **`tests/Unit/`** — pure logic and structure. `Policy`, `ApiError`,
   `Breaker` (with an injected clock and an in-memory store), `Recorder`'s
   hashing and IP masking, the manifests, and two guards: the hooks against
   the Suite signatures recorded in `tests/Support/suite-signatures.php`,
   and the hooks against the properties the Suite's loader depends on.
2. **`tests/Scanner/`** — the fail-open matrix. Eighteen rows through the
   *real* SDK — request building, retry loop, status handling, parsing —
   over a fake `HttpClientInterface`. Each asserts the action and that
   nothing propagated. A reflective test walks the gateway's public methods
   and fails if one is not exercised, so a new scan path cannot arrive
   without fail-open coverage.
3. **`tests/Hooks/`** — `hooks/*.php` themselves, read off disk and
   compiled the way the Suite compiles them, over parents that count calls
   and throw on demand and carry no defensive `method_exists()`. A stub
   more forgiving than the platform hides the defects the harness exists to
   find.
4. **A live forum** — installing the tar through the AdminCP, the widget,
   the theme templates. Not automatable here; `docs/SUITE-FACTS.md` lists
   what still needs one.

`tests/Mocks/FakeMember` extends the `\IPS\Member` stub with the few
properties `shouldBypass()` reads. `tests/Pest.php` resets the settings
singleton, the IPS log buffer, the gateway's scanner and the instrumented
parents before every test, and gives you `scannerOver(FakeHttpClient)` to
wire a scanner to a canned response.

## Release checklist

1. Bump `app_version` and add a `data/versions.json` entry +
   `setup/upgrade/<long_version>/upgrade.php` (no-op if no schema
   change).
2. Move `[Unreleased]` in `CHANGELOG.md` under a dated version.
3. `composer qa` — must be green. It includes `composer manifests`, which
   checks the version you just bumped against `data/versions.json`,
   `setup/cli-install.php` and `Application::VERSION`.
4. `bash dev/build-xml.sh` regenerates `data/build.xml`. CI diffs it, so a
   stale copy fails the build rather than shipping.
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
