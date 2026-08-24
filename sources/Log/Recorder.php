<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll scan log writer
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Log;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

use IPS\spamtroll\Scanner\Decision;

/**
 * Writes a scan result to `spamtroll_logs`.
 *
 * Split out of the hooks so the scanner has one place to log from and the
 * tests have one thing to substitute. Not final: the suite subclasses it to
 * capture writes instead of reaching for a database.
 */
class _Recorder
{
    public function record(
        Decision $decision,
        ?int $memberId,
        string $contentType,
        ?int $contentId,
        ?string $ipAddress,
        ?string $contentPreview = null,
    ): void {
        try {
            \IPS\spamtroll\Application::log(
                $memberId,
                $contentType,
                $contentId,
                $ipAddress,
                $decision->status,
                $decision->score,
                $decision->symbols,
                $decision->threats,
                $decision->action,
                $contentPreview,
                $decision->submissionId,
            );
        } catch (\Throwable $t) {
            /* A log write must never be the reason a post fails. The gateway
             * would catch this anyway; catching it here keeps the failure
             * from aborting the rest of the scan's bookkeeping. */
            self::note('recorder', $t);
        }
    }

    /**
     * Last-resort logging that cannot itself throw, and cannot blow the
     * message size up either — the message is cut on a character boundary
     * with mb_strcut, because a byte-sliced UTF-8 string has killed a
     * request in a sibling integration before.
     */
    public static function note(string $where, \Throwable $throwable): void
    {
        try {
            \IPS\Log::log(
                'Spamtroll ' . $where . ': ' . mb_strcut($throwable->getMessage(), 0, 500),
                'spamtroll',
            );
        } catch (\Throwable $ignored) {
            /* Nothing left to try. Carry on in silence. */
        }
    }
}
