<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

use IPS\spamtroll\Scanner\Clock;

final class FixedClock implements Clock
{
    public function __construct(private int $timestamp = 1_700_000_000)
    {
    }

    public function now(): int
    {
        return $this->timestamp;
    }

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }
}
