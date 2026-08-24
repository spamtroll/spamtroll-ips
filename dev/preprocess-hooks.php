<?php

declare(strict_types=1);

/**
 * Reproduces the IPS hook transformation so that `hooks/*.php` can be fed to
 * a plain PHP parser.
 *
 * The Suite does exactly this before `eval()`-ing a hook (docs/SUITE-FACTS.md,
 * U12 — `init.php:943-950`):
 *
 *     "namespace {$namespace}; " . str_replace('_HOOK_CLASS_', $realClass, $source)
 *
 * The only thing added here is the opening `<?php`, which `eval()` supplies
 * implicitly and a file has to carry. In particular the leading `//` of the
 * hook file is **not** stripped: it is what comments out the `<?php` that
 * `eval()` would reject, so removing it here would mean analysing a different
 * file from the one the Suite runs.
 *
 * Usage:
 *   php dev/preprocess-hooks.php [outputDir]
 *
 * Default output directory is `build/hooks-preprocessed`. The same
 * transformation is used by the hook harness in `tests/Support/HookHarness.php`,
 * so the analyser and the tests can never drift apart.
 */

require_once __DIR__ . '/../tests/Support/HookTransform.php';

use IPS\spamtroll\Tests\Support\HookTransform;

$root = \dirname(__DIR__);
$outputDir = $argv[1] ?? $root . '/build/hooks-preprocessed';

if (!is_dir($outputDir) && !mkdir($outputDir, 0o777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create {$outputDir}\n");
    exit(1);
}

$written = 0;
foreach (HookTransform::MAP as $hookName => $target) {
    $source = $root . '/hooks/' . $hookName . '.php';
    if (!is_file($source)) {
        fwrite(STDERR, "Missing hook file {$source}\n");
        exit(1);
    }

    $contents = file_get_contents($source);
    if ($contents === false) {
        fwrite(STDERR, "Cannot read {$source}\n");
        exit(1);
    }

    $code = "<?php\n\n" . HookTransform::apply($contents, $target['namespace'], $target['parent']);
    file_put_contents($outputDir . '/' . $hookName . '.php', $code);
    $written++;
}

echo "Preprocessed {$written} hook(s) into {$outputDir}\n";
