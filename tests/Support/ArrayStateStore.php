<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

use IPS\spamtroll\Scanner\StateStore;

final class ArrayStateStore implements StateStore
{
    /** @var array<string, int> */
    public array $values = [];

    public bool $throwOnRead = false;

    public bool $throwOnWrite = false;

    public function getInt(string $key, int $default = 0): int
    {
        if ($this->throwOnRead) {
            throw new \RuntimeException('datastore unavailable');
        }

        return $this->values[$key] ?? $default;
    }

    public function setInt(string $key, int $value): void
    {
        if ($this->throwOnWrite) {
            throw new \RuntimeException('datastore unavailable');
        }

        $this->values[$key] = $value;
    }
}
