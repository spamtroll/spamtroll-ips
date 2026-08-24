<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Anti-Spam Application Class
 *
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 *
 * @since       01 Jan 2024
 *
 * @version     1.0.0
 */

namespace IPS\spamtroll;

/* Load the Spamtroll SDK (installed via Composer into applications/spamtroll/vendor/).
 * This runs the first time IPS's autoloader pulls in \IPS\spamtroll\Application,
 * which happens before any hook or admin controller can reach the API client. */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Spamtroll Anti-Spam Application Class
 */
class _Application extends \IPS\Application
{
    /**
     * Human-readable version. Kept in step with the highest key in
     * data/versions.json and with setup/cli-install.php by
     * dev/check-manifests.sh, which is a CI gate — the three used to drift,
     * so a fresh install reported 1.0.0 while its upgrade steps had already
     * run.
     */
    public const VERSION = '1.0.2';

    /** IPS long version: the highest key in data/versions.json. */
    public const VERSION_LONG = 10002;

    /**
     * @var \Spamtroll\Sdk\Client|null Singleton instance
     */
    protected static $apiClient = null;

    /**
     * [Node] Get Icon for tree
     *
     * @return string|null
     */
    protected function get__icon()
    {
        return 'shield';
    }

    /**
     * The client for AdminCP and background work.
     *
     * Not the one the hooks use: those go through
     * \IPS\spamtroll\Scanner\ClientFactory::interactiveScanner(), which
     * trades retries for latency because a member is waiting. See
     * ClientFactory for the two budgets.
     *
     * @return \Spamtroll\Sdk\Client
     */
    public static function apiClient(): \Spamtroll\Sdk\Client
    {
        if (static::$apiClient === null) {
            static::$apiClient = \IPS\spamtroll\Scanner\ClientFactory::managementClient();
        }

        return static::$apiClient;
    }

    /**
     * Check if Spamtroll is enabled and configured
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return (bool) \IPS\Settings::i()->spamtroll_enabled
            && !empty(\IPS\Settings::i()->spamtroll_api_key);
    }

    /**
     * Whether the registration hook can actually run.
     *
     * The Suite only calls `\IPS\Member::spamService()` when its own spam
     * defence is switched on (docs/SUITE-FACTS.md, U4b). With
     * `spam_service_enabled` off, this application's registration hook is
     * installed, enabled, and never reached — and until now the AdminCP
     * reported registration scanning as working.
     *
     * Returns true when registration scanning is switched off here too, since
     * then there is nothing to warn about.
     */
    public static function registrationScanningIsReachable(): bool
    {
        if (!\IPS\Settings::i()->spamtroll_check_registrations) {
            return true;
        }

        return (bool) \IPS\Settings::i()->spam_service_enabled;
    }

    /**
     * Check if member should bypass spam checking
     *
     * @param \IPS\Member $member Member to check
     *
     * @return bool
     */
    public static function shouldBypass(\IPS\Member $member): bool
    {
        if ($member->isAdmin()) {
            return true;
        }

        $bypassGroups = \IPS\Settings::i()->spamtroll_bypass_groups;
        if (!empty($bypassGroups)) {
            $groups = explode(',', $bypassGroups);
            foreach ($member->groups as $groupId) {
                if (\in_array($groupId, $groups)) {
                    return true;
                }
            }
        }

        // Established-member trust threshold: a member with more than N
        // forum posts is skipped. 0 disables the check (default).
        $minPosts = (int) \IPS\Settings::i()->spamtroll_bypass_min_posts;
        if ($minPosts > 0 && (int) $member->member_posts > $minPosts) {
            return true;
        }

        return false;
    }

    /**
     * Determine action based on spam score
     *
     * @param float $score Spam score
     *
     * @return string Action: block, moderate, warn, allow
     */
    public static function determineAction(float $score): string
    {
        $spamThreshold = max(0.0, min(1.0, (float) \IPS\Settings::i()->spamtroll_spam_threshold));
        $suspiciousThreshold = max(0.0, min(1.0, (float) \IPS\Settings::i()->spamtroll_suspicious_threshold));

        if ($score >= $spamThreshold) {
            return \IPS\Settings::i()->spamtroll_action_blocked ?: 'block';
        }

        if ($score >= $suspiciousThreshold) {
            return \IPS\Settings::i()->spamtroll_action_suspicious ?: 'moderate';
        }

        return 'allow';
    }

    /**
     * Determine status based on spam score
     *
     * @param float $score Spam score
     *
     * @return string Status: blocked, suspicious, safe
     */
    public static function determineStatus(float $score): string
    {
        $spamThreshold = max(0.0, min(1.0, (float) \IPS\Settings::i()->spamtroll_spam_threshold));
        $suspiciousThreshold = max(0.0, min(1.0, (float) \IPS\Settings::i()->spamtroll_suspicious_threshold));

        if ($score >= $spamThreshold) {
            return 'blocked';
        }

        if ($score >= $suspiciousThreshold) {
            return 'suspicious';
        }

        return 'safe';
    }

    /**
     * Log spam check result
     *
     * @param int|null $memberId Member ID
     * @param string $contentType Content type (post, message, registration)
     * @param int|null $contentId Content ID
     * @param string|null $ipAddress IP address
     * @param string $status Status (blocked, suspicious, safe)
     * @param float $spamScore Spam score
     * @param array|null $symbols Detection symbols
     * @param array|null $threats Threat categories
     * @param string $actionTaken Action taken
     * @param string|null $contentPreview Content preview
     */
    public static function log(
        ?int $memberId,
        string $contentType,
        ?int $contentId,
        ?string $ipAddress,
        string $status,
        float $spamScore,
        ?array $symbols,
        ?array $threats,
        string $actionTaken,
        ?string $contentPreview = null,
        ?string $submissionId = null,
    ): void {
        try {
            \IPS\Db::i()->insert('spamtroll_logs', [
                'log_member_id' => $memberId,
                'log_content_type' => $contentType,
                'log_content_id' => $contentId,
                'log_ip_address' => $ipAddress,
                'log_status' => $status,
                'log_spam_score' => $spamScore,
                'log_symbols' => $symbols ? (json_encode($symbols) ?: null) : null,
                'log_threat_categories' => $threats ? (json_encode($threats) ?: null) : null,
                'log_action_taken' => $actionTaken,
                'log_content_preview' => $contentPreview ? mb_substr($contentPreview, 0, 500) : null,
                'log_submission_id' => $submissionId,
                'log_date' => time(),
            ]);
        } catch (\Exception $e) {
            \IPS\Log::log($e, 'spamtroll');
        }
    }

    /**
     * Record a quota-exhausted scan (HTTP 402 response from the API)
     * in the application config so the AdminCP dashboard can render
     * "X messages were skipped because you hit your daily quota —
     * upgrade your plan". JSON-encoded payload is intentionally
     * compact: a per-day counter pruned to 30 days plus the most
     * recent usage block from the API. No DB schema changes needed.
     */
    public static function recordQuotaSkipped(\Spamtroll\Sdk\Response\CheckSpamResponse $response): void
    {
        $today = gmdate('Y-m-d');
        $stored = json_decode((string) \IPS\Settings::i()->spamtroll_quota_skipped_log, true);
        if (!is_array($stored)) {
            $stored = [];
        }
        $byDay = isset($stored['days']) && is_array($stored['days']) ? $stored['days'] : [];
        $byDay[$today] = (isset($byDay[$today]) ? (int) $byDay[$today] : 0) + 1;

        $cutoff = gmdate('Y-m-d', time() - (30 * 86400));
        foreach (array_keys($byDay) as $day) {
            if (!is_string($day) || $day < $cutoff) {
                unset($byDay[$day]);
            }
        }

        $usage = method_exists($response, 'getQuotaUsage') ? $response->getQuotaUsage() : [];

        \IPS\Settings::i()->changeValues([
            'spamtroll_quota_skipped_log' => json_encode([
                'days' => $byDay,
                'last_at' => time(),
                'last_usage' => is_array($usage) ? $usage : [],
            ]),
        ]);
    }

    /**
     * Snapshot of the quota-skipped log for the AdminCP panel. Always
     * returns the canonical shape so the template can render without
     * extra null-checks.
     *
     * @return array{total: int, today: int, days: array<string,int>, last_usage: array<string,mixed>, last_at: int}
     */
    public static function getQuotaSkippedStats(int $days = 7): array
    {
        $stored = json_decode((string) \IPS\Settings::i()->spamtroll_quota_skipped_log, true);
        if (!is_array($stored)) {
            $stored = [];
        }
        $byDay = isset($stored['days']) && is_array($stored['days']) ? $stored['days'] : [];
        $cutoff = gmdate('Y-m-d', time() - (max(1, $days) * 86400));

        $window = [];
        $total = 0;
        foreach ($byDay as $day => $count) {
            if (!is_string($day) || !is_int($count) || $day < $cutoff) {
                continue;
            }
            $window[$day] = $count;
            $total += $count;
        }

        $today = gmdate('Y-m-d');
        return [
            'total' => $total,
            'today' => isset($byDay[$today]) && is_int($byDay[$today]) ? $byDay[$today] : 0,
            'days' => $window,
            'last_usage' => isset($stored['last_usage']) && is_array($stored['last_usage']) ? $stored['last_usage'] : [],
            'last_at' => isset($stored['last_at']) && is_int($stored['last_at']) ? $stored['last_at'] : 0,
        ];
    }

    /**
     * Get statistics for dashboard
     *
     * @param int $days Number of days to get stats for
     *
     * @return array
     */
    public static function getStatistics(int $days = 7): array
    {
        $since = time() - ($days * 86400);

        $total = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date > ?', $since])->first();
        $blocked = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date > ? AND log_status = ?', $since, 'blocked'])->first();
        $suspicious = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date > ? AND log_status = ?', $since, 'suspicious'])->first();
        $safe = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date > ? AND log_status = ?', $since, 'safe'])->first();

        $dailyStats = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayStart = strtotime("-{$i} days midnight");
            $dayEnd = $dayStart + 86400;

            $dayTotal = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date >= ? AND log_date < ?', $dayStart, $dayEnd])->first();
            $dayBlocked = \IPS\Db::i()->select('COUNT(*)', 'spamtroll_logs', ['log_date >= ? AND log_date < ? AND log_status = ?', $dayStart, $dayEnd, 'blocked'])->first();

            $dailyStats[] = [
                'date' => date('Y-m-d', $dayStart),
                'total' => (int) $dayTotal,
                'blocked' => (int) $dayBlocked,
            ];
        }

        return [
            'total' => (int) $total,
            'blocked' => (int) $blocked,
            'suspicious' => (int) $suspicious,
            'safe' => (int) $safe,
            'daily' => $dailyStats,
        ];
    }

    /**
     * Get recent logs
     *
     * @param int $limit Number of logs to retrieve
     *
     * @return array
     */
    public static function getRecentLogs(int $limit = 20): array
    {
        $logs = [];

        foreach (\IPS\Db::i()->select('*', 'spamtroll_logs', null, 'log_date DESC', $limit) as $row) {
            $row['log_symbols'] = $row['log_symbols'] ? json_decode($row['log_symbols'], true) : [];
            $row['log_threat_categories'] = $row['log_threat_categories'] ? json_decode($row['log_threat_categories'], true) : [];
            $logs[] = $row;
        }

        return $logs;
    }

    /**
     * Install routine
     */
    public function installOther(): void
    {
        // Nothing to do on install
    }
}
