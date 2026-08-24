<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

/**
 * A content object that can be hidden.
 *
 * Records who each hide was credited to: passing NULL credits it to the
 * currently logged-in member, which during a post is the spammer
 * (docs/SUITE-FACTS.md, U8b), so the argument is worth asserting on.
 */
class FakeHideable
{
    public int $id = 0;

    public int $hideCount = 0;

    /** @var array<int, mixed> */
    public array $hiddenBy = [];

    public ?\Throwable $hideThrows = null;

    public function __construct(int $id = 0)
    {
        $this->id = $id;
    }

    /**
     * @param \IPS\Member|null|false $member
     */
    public function hide($member, ?string $reason = null): void
    {
        $this->hideCount++;
        $this->hiddenBy[] = $member;

        if ($this->hideThrows !== null) {
            throw $this->hideThrows;
        }
    }
}
