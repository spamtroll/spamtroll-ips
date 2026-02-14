<?php
/**
 * @brief       Spamtroll Anti-Spam Application Class
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 * @version     1.0.0
 */

namespace IPS\spamtroll;

/**
 * Spamtroll Anti-Spam Application Class
 */
class _Application extends \IPS\Application
{
    /**
     * @var \IPS\spamtroll\Api\Client|null Singleton instance
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
     * Get API Client singleton
     *
     * @return \IPS\spamtroll\Api\Client
     */
    public static function apiClient(): \IPS\spamtroll\Api\Client
    {
        if (static::$apiClient === null) {
            static::$apiClient = new \IPS\spamtroll\Api\Client();
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
     * Check if member should bypass spam checking
     *
     * @param \IPS\Member $member Member to check
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

        return false;
    }

    /**
     * Determine action based on spam score
     *
     * @param float $score Spam score
     * @return string Action: block, moderate, warn, allow
     */
    public static function determineAction(float $score): string
    {
        $spamThreshold = (float) \IPS\Settings::i()->spamtroll_spam_threshold;
        $suspiciousThreshold = (float) \IPS\Settings::i()->spamtroll_suspicious_threshold;

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
     * @return string Status: blocked, suspicious, safe
     */
    public static function determineStatus(float $score): string
    {
        $spamThreshold = (float) \IPS\Settings::i()->spamtroll_spam_threshold;
        $suspiciousThreshold = (float) \IPS\Settings::i()->spamtroll_suspicious_threshold;

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
     * @param int|null     $memberId     Member ID
     * @param string       $contentType  Content type (post, message, registration)
     * @param int|null     $contentId    Content ID
     * @param string|null  $ipAddress    IP address
     * @param string       $status       Status (blocked, suspicious, safe)
     * @param float        $spamScore    Spam score
     * @param array|null   $symbols      Detection symbols
     * @param array|null   $threats      Threat categories
     * @param string       $actionTaken  Action taken
     * @param string|null  $contentPreview Content preview
     * @return void
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
        ?string $contentPreview = null
    ): void {
        try {
            \IPS\Db::i()->insert('spamtroll_logs', [
                'log_member_id' => $memberId,
                'log_content_type' => $contentType,
                'log_content_id' => $contentId,
                'log_ip_address' => $ipAddress,
                'log_status' => $status,
                'log_spam_score' => $spamScore,
                'log_symbols' => $symbols ? json_encode($symbols) : null,
                'log_threat_categories' => $threats ? json_encode($threats) : null,
                'log_action_taken' => $actionTaken,
                'log_content_preview' => $contentPreview ? mb_substr($contentPreview, 0, 500) : null,
                'log_date' => time(),
            ]);
        } catch (\Exception $e) {
            \IPS\Log::log($e, 'spamtroll');
        }
    }

    /**
     * Get statistics for dashboard
     *
     * @param int $days Number of days to get stats for
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
     *
     * @return void
     */
    public function installOther()
    {
        // Nothing to do on install
    }
}
