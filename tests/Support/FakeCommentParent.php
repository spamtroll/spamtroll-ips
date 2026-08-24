<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * Stands in for \IPS\Content\_Comment under the hook.
 *
 * Instrumented, not accommodating. It counts calls, records arguments and
 * throws exactly what it is told to throw, and it has no defensive
 * `method_exists()` anywhere: a stub more forgiving than the platform hides
 * the defects the harness exists to catch.
 *
 * The signature is the Suite's, recorded in tests/Support/suite-signatures.php
 * and asserted against this class by tests/Unit/SuiteSignatureTest.php.
 */
class FakeCommentParent
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
     * @param mixed $item
     * @param mixed $comment
     * @param mixed $first
     * @param mixed $guestName
     * @param mixed $incrementPostCount
     * @param mixed $member
     * @param mixed $ipAddress
     * @param mixed $hiddenStatus
     * @param mixed $anonymous
     *
     * @return mixed
     */
    public static function create($item, $comment, $first = false, $guestName = null, $incrementPostCount = null, $member = null, ?\IPS\DateTime $time = null, $ipAddress = null, $hiddenStatus = null, $anonymous = null)
    {
        static::$callCount++;
        static::$calls[] = \func_get_args();

        if (static::$throw !== null) {
            throw static::$throw;
        }

        return static::$result;
    }
}
