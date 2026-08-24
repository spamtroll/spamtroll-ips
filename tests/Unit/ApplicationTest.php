<?php

declare(strict_types=1);

use IPS\spamtroll\Application;

describe('determineStatus', function (): void {
    it('returns blocked when score is at or above the spam threshold', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;

        expect(Application::determineStatus(0.7))->toBe('blocked');
        expect(Application::determineStatus(0.95))->toBe('blocked');
        expect(Application::determineStatus(1.0))->toBe('blocked');
    });

    it('returns suspicious when score is between thresholds', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;

        expect(Application::determineStatus(0.4))->toBe('suspicious');
        expect(Application::determineStatus(0.55))->toBe('suspicious');
        expect(Application::determineStatus(0.69))->toBe('suspicious');
    });

    it('returns safe when score is below the suspicious threshold', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;

        expect(Application::determineStatus(0.0))->toBe('safe');
        expect(Application::determineStatus(0.39))->toBe('safe');
    });

    it('clamps malformed thresholds to the 0..1 range', function (): void {
        settings()->spamtroll_spam_threshold = 5.0; // out-of-range
        settings()->spamtroll_suspicious_threshold = -1.0;

        expect(Application::determineStatus(1.0))->toBe('blocked');
        expect(Application::determineStatus(0.5))->toBe('suspicious');
    });
});

describe('determineAction', function (): void {
    it('uses the configured action for blocked content', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;
        settings()->spamtroll_action_blocked = 'block';
        settings()->spamtroll_action_suspicious = 'moderate';

        expect(Application::determineAction(0.9))->toBe('block');
    });

    it('uses the configured action for suspicious content', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;
        settings()->spamtroll_action_blocked = 'block';
        settings()->spamtroll_action_suspicious = 'warn';

        expect(Application::determineAction(0.5))->toBe('warn');
    });

    it('returns allow when content is below all thresholds', function (): void {
        settings()->spamtroll_spam_threshold = 0.7;
        settings()->spamtroll_suspicious_threshold = 0.4;

        expect(Application::determineAction(0.1))->toBe('allow');
    });
});

describe('shouldBypass', function (): void {
    it('always bypasses administrators', function (): void {
        $admin = member();
        $admin->isAdminFlag = true;

        expect(Application::shouldBypass($admin))->toBeTrue();
    });

    it('bypasses members in configured bypass groups', function (): void {
        settings()->spamtroll_bypass_groups = '4,7';
        $member = member(memberPosts: 5, groups: [3, 7]); // group 7 matches

        expect(Application::shouldBypass($member))->toBeTrue();
    });

    it('does not bypass when no group matches', function (): void {
        settings()->spamtroll_bypass_groups = '4,7';
        $member = member(memberPosts: 5, groups: [3, 5]);

        expect(Application::shouldBypass($member))->toBeFalse();
    });

    it('bypasses members whose post count exceeds the configured threshold', function (): void {
        settings()->spamtroll_bypass_min_posts = 30;
        $member = member(memberPosts: 31);

        expect(Application::shouldBypass($member))->toBeTrue();
    });

    it('does not bypass when post count is exactly the threshold', function (): void {
        settings()->spamtroll_bypass_min_posts = 30;
        $member = member(memberPosts: 30);

        expect(Application::shouldBypass($member))->toBeFalse();
    });

    it('treats threshold of 0 as disabled', function (): void {
        settings()->spamtroll_bypass_min_posts = 0;
        $member = member(memberPosts: 9999);

        expect(Application::shouldBypass($member))->toBeFalse();
    });
});

describe('registrationScanningIsReachable', function (): void {
    it('warns when the Suite spam defence is off but registration scanning is on', function (): void {
        /* spamService() is only called when spam_service_enabled is set
         * (docs/SUITE-FACTS.md, U4b), so the hook never runs and the AdminCP
         * used to report itself as working anyway. */
        settings()->spamtroll_check_registrations = true;
        settings()->spam_service_enabled = false;

        expect(Application::registrationScanningIsReachable())->toBeFalse();
    });

    it('says nothing when the Suite spam defence is on', function (): void {
        settings()->spamtroll_check_registrations = true;
        settings()->spam_service_enabled = true;

        expect(Application::registrationScanningIsReachable())->toBeTrue();
    });

    it('says nothing when registration scanning is off here too', function (): void {
        settings()->spamtroll_check_registrations = false;
        settings()->spam_service_enabled = false;

        expect(Application::registrationScanningIsReachable())->toBeTrue();
    });
});
