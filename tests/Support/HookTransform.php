<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * The IPS hook transformation, in one place.
 *
 * Both the PHPStan preprocessor (`dev/preprocess-hooks.php`) and the runtime
 * harness (`tests/Support/HookHarness.php`) go through this class, so the
 * analyser and the tests can never end up looking at differently-shaped code.
 *
 * The Suite's version is (docs/SUITE-FACTS.md, U12 — `init.php:943-950`):
 *
 *     $contents = "namespace {$namespace}; " . str_replace('_HOOK_CLASS_', $realClass, file_get_contents($file));
 *
 *     @eval($contents);
 *
 * Three properties are load-bearing and must not be "tidied up":
 *
 *  - the leading `//` of a hook file stays. It comments out `<?php`, which
 *    `eval()` rejects. A harness that stripped it would be testing a file the
 *    Suite never runs;
 *  - `_HOOK_CLASS_` is a plain string replacement, not a parse;
 *  - after a successful `eval()` the Suite sets `$realClass` to the hook class
 *    it just defined, so several hooks on the same class chain by inheritance
 *    rather than all extending the framework class.
 */
final class HookTransform
{
    /**
     * Where the Suite's autoloader lands for each of our hooks
     * (docs/SUITE-FACTS.md, U12b). `\IPS\Content\Comment` splits into namespace
     * `IPS\Content` + class `Comment`, so the hook's parent is `_Comment`;
     * `\IPS\Member` splits into namespace `IPS` + class `Member`.
     *
     * @var array<string, array{namespace: string, parent: string, class: string}>
     */
    public const MAP = [
        'Comment' => [
            'namespace' => 'IPS\\Content',
            'parent' => '_Comment',
            'class' => '\\IPS\\Content\\Comment',
        ],
        'Member' => [
            'namespace' => 'IPS',
            'parent' => '_Member',
            'class' => '\\IPS\\Member',
        ],
    ];

    /**
     * Apply the Suite's transformation to a hook file's raw contents.
     *
     * The result has no opening tag, exactly like the string the Suite hands
     * to `eval()`. Callers that need a file prepend `<?php`.
     */
    public static function apply(string $contents, string $namespace, string $parentClass): string
    {
        return "namespace {$namespace}; " . str_replace('_HOOK_CLASS_', $parentClass, $contents);
    }

    /**
     * The class name the Suite gives a hook shipped by an application:
     * `"{$app}_hook_{$filename}"` (docs/SUITE-FACTS.md, U12c).
     */
    public static function hookClassName(string $hookName): string
    {
        return 'spamtroll_hook_' . $hookName;
    }
}
