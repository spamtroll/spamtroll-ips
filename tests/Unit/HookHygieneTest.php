<?php

declare(strict_types=1);

use IPS\spamtroll\Tests\Support\HookHarness;
use IPS\spamtroll\Tests\Support\HookTransform;

/**
 * Properties of the hook files that the Suite's loader depends on, and that
 * the harness depends on in turn.
 *
 * The harness compiles each hook into a per-run namespace so the same file
 * can be loaded more than once in a process. That is safe only while every
 * class reference in a hook is fully qualified — otherwise a name would
 * resolve differently under test than under the Suite, and the harness would
 * be quietly testing a different program. Asserted here rather than trusted.
 */

it('starts with the comment that hides the opening tag', function (string $hookName): void {
    /* eval() rejects `<?php`, so the Suite relies on the leading `//` to
     * comment it out (docs/SUITE-FACTS.md, U12). A file that lost it would
     * emit its own source to the page. */
    expect(substr(HookHarness::source($hookName), 0, 7))->toBe('//<?php');
})->with(array_keys(HookTransform::MAP));

it('still carries the placeholder the Suite substitutes', function (string $hookName): void {
    expect(HookHarness::source($hookName))->toContain('_HOOK_CLASS_');
})->with(array_keys(HookTransform::MAP));

it('refers to every class by its fully qualified name', function (string $hookName): void {
    $source = HookHarness::source($hookName);
    /* Give the tokenizer a real opening tag; the file's own is commented out. */
    $tokens = PhpToken::tokenize("<?php\n" . substr($source, 2));

    $relative = [];
    foreach ($tokens as $index => $token) {
        /* A namespaced name without a leading backslash: `IPS\Settings`. */
        if ($token->is(T_NAME_QUALIFIED)) {
            $relative[] = $token->text;
            continue;
        }

        /* A bare name used as a class: `Settings::i()`, `new Settings`. */
        if (!$token->is(T_STRING)) {
            continue;
        }

        $next = $tokens[$index + 1] ?? null;
        if ($next !== null && $next->is(T_WHITESPACE)) {
            $next = $tokens[$index + 2] ?? null;
        }

        if ($next !== null && $next->text === '::' && !\in_array(strtolower($token->text), ['parent', 'self', 'static'], true)) {
            $relative[] = $token->text;
        }
    }

    expect($relative)->toBe([]);
})->with(array_keys(HookTransform::MAP));

it('is thin enough that there is nothing left in it to get wrong', function (string $hookName): void {
    $source = HookHarness::source($hookName);

    /* No catch at all: the gateway owns fail-open, and a second opinion in
     * the hook is how the two hooks came to disagree in the first place. */
    expect($source)->not->toContain('catch');

    /* The framework's passthrough boilerplate calls the parent a second time.
     * Harmless around a bare parent call, a duplicated post row around
     * anything else. */
    expect($source)->not->toContain('call_user_func_array');
    expect($source)->not->toContain('get_parent_class');

    /* One call to the parent, and one entry point into our own code —
     * counted in code, not in prose, so a docblock that mentions either can
     * still say so. */
    $tokens = PhpToken::tokenize("<?php\n" . substr($source, 2));
    $parentCalls = 0;
    $ourCalls = 0;
    foreach ($tokens as $token) {
        if ($token->is(T_STRING) && strtolower($token->text) === 'parent') {
            $parentCalls++;
        }
        if ($token->is(T_NAME_FULLY_QUALIFIED) && str_starts_with($token->text, '\\IPS\\spamtroll\\')) {
            $ourCalls++;
        }
    }

    expect($parentCalls)->toBe(1);
    expect($ourCalls)->toBe(1);
})->with(array_keys(HookTransform::MAP));
