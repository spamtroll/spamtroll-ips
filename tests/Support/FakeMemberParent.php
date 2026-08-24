<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * Stands in for \IPS\_Member under the registration hook.
 *
 * Extends \IPS\Member so the class the harness builds on top of it still
 * satisfies the `\IPS\Member` parameter types in the gateway — which is what
 * the Suite ends up with too, since it closes the chain with
 * `class Member extends {$lastHook} {}` (docs/SUITE-FACTS.md, U12d).
 */
class FakeMemberParent extends \IPS\Member
{
    public static int $callCount = 0;

    /** @var array<int, array<int, mixed>> */
    public static array $calls = [];

    public static ?\Throwable $throw = null;

    /** @var mixed */
    public static $result = null;

    public static function reset(): void
    {
        static::$callCount = 0;
        static::$calls = [];
        static::$throw = null;
        static::$result = null;
    }

    /**
     * @param mixed $type
     * @param mixed $emailAddress
     * @param mixed $spamCode
     * @param mixed $disposable
     * @param mixed $geoBlock
     *
     * @return mixed
     */
    public function spamService($type = 'register', $emailAddress = null, &$spamCode = null, &$disposable = false, &$geoBlock = false)
    {
        static::$callCount++;
        static::$calls[] = [$type, $emailAddress];

        if (static::$throw !== null) {
            throw static::$throw;
        }

        return static::$result;
    }
}
