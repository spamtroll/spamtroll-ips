<?php

declare(strict_types=1);

use IPS\Settings;
use IPS\spamtroll\Tests\Mocks\FakeMember;

uses()
    ->beforeEach(function (): void {
        // Reset the Settings stub to its declared defaults so every test
        // starts from a known state. We poke values via property access
        // because that's how the production code reads them.
        $defaults = [
            'spamtroll_enabled' => false,
            'spamtroll_api_key' => '',
            'spamtroll_spam_threshold' => 0.7,
            'spamtroll_suspicious_threshold' => 0.4,
            'spamtroll_check_posts' => true,
            'spamtroll_check_registrations' => true,
            'spamtroll_action_blocked' => 'block',
            'spamtroll_action_suspicious' => 'moderate',
            'spamtroll_bypass_groups' => '',
            'spamtroll_bypass_min_posts' => 0,
        ];
        $settings = Settings::i();
        foreach ($defaults as $k => $v) {
            $settings->{$k} = $v;
        }
    })
    ->in('Unit');

function settings(): IPS\Settings
{
    return IPS\Settings::i();
}

function member(int $memberId = 1, int $memberPosts = 0, array $groups = [3]): FakeMember
{
    return new FakeMember($memberId, $memberPosts, $groups);
}
