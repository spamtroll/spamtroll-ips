<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\Decision;
use IPS\spamtroll\Scanner\Policy;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * Three statuses by three presets.
 *
 * The preset used to move the numeric thresholds this application then
 * applied to a normalised score, which is how "balanced" ended up blocking at
 * a raw 21.0 while the backend blocked at 15.0 — everything in between went
 * through unblocked, and the per-platform overrides in the Spamtroll panel
 * had no effect at all. Now the backend decides *what* something is and the
 * preset decides what this forum does about it.
 */
it('maps every status and preset to an action', function (string $status, string $preset, string $expected): void {
    expect(Policy::actionFor($status, $preset))->toBe($expected);
})->with([
    [CheckSpamResponse::STATUS_BLOCKED, Policy::SENSITIVITY_STRICT, Decision::ACTION_BLOCK],
    [CheckSpamResponse::STATUS_BLOCKED, Policy::SENSITIVITY_BALANCED, Decision::ACTION_BLOCK],
    [CheckSpamResponse::STATUS_BLOCKED, Policy::SENSITIVITY_LENIENT, Decision::ACTION_MODERATE],
    [CheckSpamResponse::STATUS_SUSPICIOUS, Policy::SENSITIVITY_STRICT, Decision::ACTION_BLOCK],
    [CheckSpamResponse::STATUS_SUSPICIOUS, Policy::SENSITIVITY_BALANCED, Decision::ACTION_MODERATE],
    [CheckSpamResponse::STATUS_SUSPICIOUS, Policy::SENSITIVITY_LENIENT, Decision::ACTION_ALLOW],
    [CheckSpamResponse::STATUS_SAFE, Policy::SENSITIVITY_STRICT, Decision::ACTION_ALLOW],
    [CheckSpamResponse::STATUS_SAFE, Policy::SENSITIVITY_BALANCED, Decision::ACTION_ALLOW],
    [CheckSpamResponse::STATUS_SAFE, Policy::SENSITIVITY_LENIENT, Decision::ACTION_ALLOW],
]);

it('treats a status it does not recognise as safe', function (): void {
    expect(Policy::actionFor('quarantined', Policy::SENSITIVITY_STRICT))->toBe(Decision::ACTION_ALLOW);
});

it('falls back to balanced rather than to the harshest preset', function (string $configured): void {
    settings()->spamtroll_sensitivity = $configured;

    expect(Policy::sensitivity())->toBe(Policy::SENSITIVITY_BALANCED);
})->with(['', 'aggressive', 'Balanced']);

it('keeps a configured preset', function (): void {
    settings()->spamtroll_sensitivity = 'strict';

    expect(Policy::sensitivity())->toBe(Policy::SENSITIVITY_STRICT);
});

it('uses the backend verdict by default', function (): void {
    expect(Policy::usesLegacyThresholds())->toBeFalse();
});

it('still classifies by threshold when the override is switched on', function (): void {
    settings()->spamtroll_override_thresholds = true;
    settings()->spamtroll_spam_threshold = 0.7;
    settings()->spamtroll_suspicious_threshold = 0.4;

    expect(Policy::legacyVerdict(0.9))->toBe(['blocked', 'block']);
    expect(Policy::legacyVerdict(0.5))->toBe(['suspicious', 'moderate']);
    expect(Policy::legacyVerdict(0.1))->toBe(['safe', 'allow']);
});
