<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll scan decision
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

/**
 * The single value a hook gets back from the gateway.
 *
 * Everything a caller needs to act and to log is here, so the hook never has
 * to reach back into the response, the settings, or the SDK. `action` is
 * always one of the four constants — there is no "unknown" state, because a
 * scan that could not produce a verdict is an `allow` with a `skipReason`.
 */
final class _Decision
{
    public const ACTION_ALLOW = 'allow';
    public const ACTION_WARN = 'warn';
    public const ACTION_MODERATE = 'moderate';
    public const ACTION_BLOCK = 'block';

    /** @var string One of the ACTION_* constants. */
    public string $action = self::ACTION_ALLOW;

    /** @var string Verdict from the API: blocked, suspicious or safe. */
    public string $status = 'safe';

    /** @var float Score normalised to 0.0-1.0. Kept for logging and the ACP only. */
    public float $score = 0.0;

    /** @var array<int, string> */
    public array $symbols = [];

    /** @var array<int, string> */
    public array $threats = [];

    public ?string $submissionId = null;

    /**
     * @var string Why no verdict was reached — 'quota_exceeded', 'breaker_open',
     *             'transport_error', 'gateway_error', 'disabled', 'bypassed', …
     *             Empty exactly when the API returned a verdict.
     */
    public string $skipReason = '';

    /** @var string Machine-readable API error code, when there was one. */
    public string $errorCode = '';

    /** @var string Human-readable error detail. Never contains credentials. */
    public string $errorMessage = '';

    /** @var array<string, mixed> The `usage` block from a 402 response. */
    public array $quotaUsage = [];

    /** True when the API answered with a verdict. */
    public function scanned(): bool
    {
        return $this->skipReason === '';
    }

    /** True when the content should be hidden or held for a moderator. */
    public function wantsHiding(): bool
    {
        return $this->action === self::ACTION_BLOCK || $this->action === self::ACTION_MODERATE;
    }

    /**
     * The fail-open answer. Everything that is not a verdict is one of these.
     */
    public static function allow(string $skipReason = '', string $errorCode = '', string $errorMessage = ''): self
    {
        $decision = new self();
        $decision->skipReason = $skipReason;
        $decision->errorCode = $errorCode;
        $decision->errorMessage = $errorMessage;

        return $decision;
    }

    /**
     * @param array<int, string> $symbols
     * @param array<int, string> $threats
     */
    public static function verdict(
        string $action,
        string $status,
        float $score,
        array $symbols = [],
        array $threats = [],
        ?string $submissionId = null,
    ): self {
        $decision = new self();
        $decision->action = $action;
        $decision->status = $status;
        $decision->score = $score;
        $decision->symbols = $symbols;
        $decision->threats = $threats;
        $decision->submissionId = $submissionId;

        return $decision;
    }
}
