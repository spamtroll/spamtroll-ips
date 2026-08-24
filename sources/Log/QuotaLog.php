<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll quota-skipped counter
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

use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * Counts scans the backend declined because the daily quota was spent, so
 * the AdminCP can say "X messages went unscanned" instead of leaving the
 * admin to wonder why detection stopped.
 *
 * Not final: the suite subclasses it to count in memory.
 */
class _QuotaLog
{
    public function record(CheckSpamResponse $response): void
    {
        try {
            \IPS\spamtroll\Application::recordQuotaSkipped($response);
        } catch (\Throwable $t) {
            Recorder::note('quota log', $t);
        }
    }
}
