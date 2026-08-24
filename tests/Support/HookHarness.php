<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * Compiles a hook file the way the IPS Suite compiles it.
 *
 * Not a stand-in for the hook: the actual file from `hooks/` is read off disk,
 * put through the transformation recorded in HookTransform, and evaluated. If
 * a change makes the Suite unable to compile a hook, this fails in the same
 * place and for the same reason.
 *
 * `eval()` is the mechanism under test, not an implementation shortcut — the
 * Suite runs `@eval()` on exactly this string (docs/SUITE-FACTS.md, U12), so
 * anything else would be testing a different program. The input is a file
 * from this repository, chosen by name from a fixed map.
 *
 * Two steps, because the Suite does two (U12, U12d):
 *
 *   1. eval the hook, with `_HOOK_CLASS_` replaced by the parent;
 *   2. eval `class {$finalClass} extends {$lastHook} {}` to close the chain,
 *      which is what makes `$this` inside the Member hook an `\IPS\Member`.
 *
 * The one deliberate deviation is the namespace, which gets a per-compile
 * suffix so the same file can be compiled more than once in a process. Every
 * class reference inside our hooks is fully qualified, so the suffix cannot
 * change their meaning — and HookHygieneTest asserts that, rather than
 * leaving it to trust.
 */
final class HookHarness
{
    private static int $compilations = 0;

    /**
     * Directory to read hooks from. Overridable so the regression proof in
     * dev/prove-regression.sh can point the same tests at the pre-fix files
     * and show them failing.
     */
    public static function hooksDirectory(): string
    {
        $configured = getenv('SPAMTROLL_HOOKS_DIR');

        return \is_string($configured) && $configured !== ''
            ? rtrim($configured, '/')
            : \dirname(__DIR__, 2) . '/hooks';
    }

    public static function source(string $hookName): string
    {
        $path = self::hooksDirectory() . '/' . $hookName . '.php';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Cannot read hook file ' . $path);
        }

        return $contents;
    }

    /**
     * @param class-string $parentClass Instrumented stand-in for the framework class
     *
     * @return class-string The class the Suite would expose, e.g. `\IPS\Member`
     */
    public static function compile(string $hookName, string $parentClass): string
    {
        if (!isset(HookTransform::MAP[$hookName])) {
            throw new \RuntimeException('Unknown hook ' . $hookName);
        }

        $target = HookTransform::MAP[$hookName];
        $namespace = $target['namespace'] . '\\Compiled' . (++self::$compilations);

        /* Step 1 — the hook itself. */
        $code = HookTransform::apply(self::source($hookName), $namespace, '\\' . ltrim($parentClass, '\\'));
        if (eval($code) === false) {
            throw new \RuntimeException('Hook ' . $hookName . ' did not compile');
        }

        $hookClass = $namespace . '\\' . HookTransform::hookClassName($hookName);

        /* Step 2 — close the chain, exactly as init.php does. */
        $finalShort = substr(strrchr($target['class'], '\\') ?: '', 1);
        /* The Suite decides this from the *framework* class, not from the
         * hook: `$reflection = new ReflectionClass("{$ns}\\_{$finalClass}")`. */
        $abstract = (new \ReflectionClass($parentClass))->isAbstract() ? 'abstract ' : '';
        $closing = "namespace {$namespace}; {$abstract}class {$finalShort} extends \\{$hookClass} {}";

        if (eval($closing) === false) {
            throw new \RuntimeException('Could not close the hook chain for ' . $hookName);
        }

        /** @var class-string $final */
        $final = $namespace . '\\' . $finalShort;

        return $final;
    }
}
