<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * A content object with no hide() at all — a plugin's own comment class, or
 * anything else that reaches Comment::create() without being hideable.
 */
class FakeUnhideable
{
    public int $id = 0;

    public function __construct(int $id = 0)
    {
        $this->id = $id;
    }
}
