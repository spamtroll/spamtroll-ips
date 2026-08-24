<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll circuit breaker
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
 * Stops the forum from paying for an API that is already known to be down.
 *
 * Without it, every post during an outage waits out the full HTTP timeout,
 * one after another, for as long as the outage lasts. With it, three
 * consecutive transport failures buy a minute of silence, and a 429 is
 * honoured for as long as the server asked for.
 *
 * Every method is total: the breaker can slow the scanner down, never stop
 * it. A store that throws, a nonsense `Retry-After`, a clock that misbehaves
 * — all of them read as "no backoff".
 */
class _Breaker
{
    public const KEY_BACKOFF_UNTIL = 'spamtroll_backoff_until';
    public const KEY_FAIL_STREAK = 'spamtroll_fail_streak';

    /** Cap on a server-supplied Retry-After, so a bad header cannot mute scanning for a day. */
    public const MAX_BACKOFF = 300;

    /** Used when the server rate-limited us without saying for how long. */
    public const DEFAULT_BACKOFF = 60;

    /** Backoff after consecutive transport failures. */
    public const FAILURE_BACKOFF = 60;

    /** Short pause taken *before* a 429, when the quota headers say we are close. */
    public const SOFT_BACKOFF = 10;

    /** Consecutive transport failures that trip the breaker. */
    public const FAILURE_THRESHOLD = 3;

    /** Remaining-request count at or below which we start easing off. */
    public const REMAINING_FLOOR = 5;

    protected StateStore $store;

    protected Clock $clock;

    public function __construct(?StateStore $store = null, ?Clock $clock = null)
    {
        $this->store = $store ?? new DataStore();
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * True while the scanner should skip the network entirely.
     */
    public function isOpen(): bool
    {
        try {
            return $this->store->getInt(self::KEY_BACKOFF_UNTIL) > $this->clock->now();
        } catch (\Throwable $t) {
            return false;
        }
    }

    /**
     * How many seconds are left on the current backoff. 0 when closed.
     */
    public function secondsRemaining(): int
    {
        try {
            return max(0, $this->store->getInt(self::KEY_BACKOFF_UNTIL) - $this->clock->now());
        } catch (\Throwable $t) {
            return 0;
        }
    }

    /**
     * Open the breaker for `$seconds`, clamped to a sane window. A null or
     * unusable value falls back to the default. Never shortens a backoff that
     * is already longer.
     */
    public function open(?int $seconds): void
    {
        try {
            $window = $seconds === null || $seconds < 1 ? self::DEFAULT_BACKOFF : min($seconds, self::MAX_BACKOFF);
            $until = $this->clock->now() + $window;

            if ($until > $this->store->getInt(self::KEY_BACKOFF_UNTIL)) {
                $this->store->setInt(self::KEY_BACKOFF_UNTIL, $until);
            }
            $this->store->setInt(self::KEY_FAIL_STREAK, 0);
        } catch (\Throwable $t) {
            /* Deliberately silent — see the class docblock. */
        }
    }

    /**
     * A timeout, a refused connection or a 5xx. Three in a row and we stop
     * asking for a while.
     */
    public function recordTransportFailure(): void
    {
        try {
            $streak = $this->store->getInt(self::KEY_FAIL_STREAK) + 1;

            if ($streak >= self::FAILURE_THRESHOLD) {
                $this->open(self::FAILURE_BACKOFF);
                return;
            }

            $this->store->setInt(self::KEY_FAIL_STREAK, $streak);
        } catch (\Throwable $t) {
        }
    }

    public function recordSuccess(): void
    {
        try {
            if ($this->store->getInt(self::KEY_FAIL_STREAK) !== 0) {
                $this->store->setInt(self::KEY_FAIL_STREAK, 0);
            }
        } catch (\Throwable $t) {
        }
    }

    /**
     * Back off *before* the server has to say no. The rate-limit headers tell
     * us how much room is left; below the floor we take a short pause so the
     * forum's own traffic does not spend the last of it.
     *
     * @param array<string, string> $headers Lowercased response headers
     */
    public function observeRateLimitHeaders(array $headers): void
    {
        try {
            if (!isset($headers['x-ratelimit-remaining'])) {
                return;
            }

            $remaining = trim($headers['x-ratelimit-remaining']);
            if ($remaining === '' || !ctype_digit($remaining)) {
                return;
            }

            if ((int) $remaining <= self::REMAINING_FLOOR) {
                $this->open(self::SOFT_BACKOFF);
            }
        } catch (\Throwable $t) {
        }
    }
}
