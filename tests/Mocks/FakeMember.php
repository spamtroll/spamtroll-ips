<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Mocks;

/**
 * Minimal stand-in for `\IPS\Member` — just the surface
 * `Application::shouldBypass()` actually reads. Tests use `member()`
 * helper from `tests/Pest.php` to construct one.
 *
 * We extend `\IPS\Member` (the stub) so type hints in production code
 * (`shouldBypass(\IPS\Member $member)`) accept it.
 */
class FakeMember extends \IPS\Member
{
    public bool $isAdminFlag = false;

    /**
     * @param array<int, int> $groups
     */
    public function __construct(int $memberId = 1, int $memberPosts = 0, array $groups = [])
    {
        $this->member_id = $memberId;
        $this->member_posts = $memberPosts;
        $this->groups = $groups;
    }

    public function isAdmin(): bool
    {
        return $this->isAdminFlag;
    }
}
