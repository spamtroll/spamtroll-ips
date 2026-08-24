<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll verdict policy
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Scanner;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * Turns the backend's verdict into an action.
 *
 * The backend already decides whether something is spam; it applies the
 * account's own thresholds, which the owner can change from the Spamtroll
 * panel. Until now this application ignored that and re-derived a verdict
 * from the score, against thresholds of its own — on a *normalised* score,
 * so "balanced" only blocked at a raw 21.0 while the backend blocked at 15.0.
 * Everything between was let through, and the per-platform overrides in the
 * Spamtroll panel did nothing at all.
 *
 * So the preset no longer moves a threshold. It decides what this forum does
 * with each verdict, which is the question a forum admin can actually answer.
 */
final class _Policy
{
    public const SENSITIVITY_LENIENT = 'lenient';
    public const SENSITIVITY_BALANCED = 'balanced';
    public const SENSITIVITY_STRICT = 'strict';

    /**
     * @return string One of the Decision::ACTION_* constants
     */
    public static function actionFor(string $apiStatus, string $sensitivity): string
    {
        switch ($apiStatus) {
            case CheckSpamResponse::STATUS_BLOCKED:
                return $sensitivity === self::SENSITIVITY_LENIENT
                    ? Decision::ACTION_MODERATE
                    : Decision::ACTION_BLOCK;

            case CheckSpamResponse::STATUS_SUSPICIOUS:
                if ($sensitivity === self::SENSITIVITY_STRICT) {
                    return Decision::ACTION_BLOCK;
                }
                if ($sensitivity === self::SENSITIVITY_LENIENT) {
                    return Decision::ACTION_ALLOW;
                }
                return Decision::ACTION_MODERATE;

            default:
                return Decision::ACTION_ALLOW;
        }
    }

    /**
     * The pre-1.0.3 path: classify the normalised score against the two
     * numeric thresholds. Kept behind `spamtroll_override_thresholds` for
     * forums that tuned those numbers and want them to keep meaning what
     * they meant. Off by default — see CHANGELOG for how to switch back.
     *
     * @return array{0: string, 1: string} [status, action]
     */
    public static function legacyVerdict(float $normalisedScore): array
    {
        return [
            \IPS\spamtroll\Application::determineStatus($normalisedScore),
            \IPS\spamtroll\Application::determineAction($normalisedScore),
        ];
    }

    /**
     * The configured preset, falling back to `balanced` for an unset or
     * unrecognised value rather than picking the harshest option.
     */
    public static function sensitivity(): string
    {
        $configured = (string) \IPS\Settings::i()->spamtroll_sensitivity;

        return \in_array($configured, [
            self::SENSITIVITY_LENIENT,
            self::SENSITIVITY_BALANCED,
            self::SENSITIVITY_STRICT,
        ], true) ? $configured : self::SENSITIVITY_BALANCED;
    }

    public static function usesLegacyThresholds(): bool
    {
        return (bool) \IPS\Settings::i()->spamtroll_override_thresholds;
    }
}
