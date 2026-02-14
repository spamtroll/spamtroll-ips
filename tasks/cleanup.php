<?php
/**
 * @brief       Spamtroll Cleanup Task
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 */

namespace IPS\spamtroll\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Cleanup Task
 *
 * Removes old spam logs based on retention settings.
 */
class _cleanup extends \IPS\Task
{
    /**
     * Execute
     *
     * If ran successfully, should return null. If there is an error, should return
     * an error message which will be logged.
     *
     * @return string|null Error message or null on success
     */
    public function execute()
    {
        try {
            $retentionDays = (int) \IPS\Settings::i()->spamtroll_log_retention_days;

            if ($retentionDays < 1) {
                $retentionDays = 30;
            }

            $cutoffTime = time() - ($retentionDays * 86400);

            $deleted = \IPS\Db::i()->delete('spamtroll_logs', ['log_date < ?', $cutoffTime]);

            if ($deleted > 0) {
                \IPS\Log::log("Spamtroll cleanup: Deleted {$deleted} log entries older than {$retentionDays} days", 'spamtroll');
            }

            return null;
        } catch (\Exception $e) {
            return "Spamtroll cleanup error: " . $e->getMessage();
        }
    }

    /**
     * Cleanup
     *
     * If your task takes longer than 15 minutes to run, this method
     * will be called before execute(). Use it to clean up anything which
     * may not have been done.
     *
     * @return void
     */
    public function cleanup()
    {
        // Nothing to clean up
    }
}
