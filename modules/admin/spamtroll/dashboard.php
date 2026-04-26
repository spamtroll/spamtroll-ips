<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Dashboard Controller
 *
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 *
 * @since       01 Jan 2024
 */

namespace IPS\spamtroll\modules\admin\spamtroll;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Dashboard Controller
 */
class _dashboard extends \IPS\Dispatcher\Controller
{
    /**
     * @var bool Has been CSRF-protected
     */
    public static bool $csrfProtected = true;

    /**
     * Execute
     */
    public function execute(): void
    {
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_dashboard');
        $cssV = (string) filemtime(\IPS\ROOT_PATH . '/applications/spamtroll/dev/css/admin/spamtroll/styles.css');
        \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, array_map(fn ($u) => ((string) $u) . '?v=' . $cssV, \IPS\Theme::i()->css('spamtroll/styles.css', 'spamtroll', 'admin')));
        \IPS\Output::i()->jsFiles = array_merge(\IPS\Output::i()->jsFiles, \IPS\Output::i()->js('spamtroll.js', 'spamtroll', 'admin'));
        parent::execute();
    }

    /**
     * Dashboard view
     */
    protected function manage(): void
    {
        // Get statistics
        $stats = \IPS\spamtroll\Application::getStatistics(7);

        // Get recent logs
        $recentLogs = \IPS\spamtroll\Application::getRecentLogs(20);

        // Check API status
        $apiStatus = $this->checkApiStatus();

        // Check if configured
        $isConfigured = !empty(\IPS\Settings::i()->spamtroll_api_key);
        $isEnabled = (bool) \IPS\Settings::i()->spamtroll_enabled;

        // Build chart data from daily stats
        $chartLabels = $chartTotal = $chartBlocked = [];
        foreach ($stats['daily'] as $day) {
            $chartLabels[] = $day['date'];
            $chartTotal[] = $day['total'];
            $chartBlocked[] = $day['blocked'];
        }

        // Quota-skipped panel — surfaces "X messages were not scanned
        // because daily quota was exhausted" with an upgrade CTA.
        // Empty unless something hit the limit in the last 7 days.
        $quotaSkipped = \IPS\spamtroll\Application::getQuotaSkippedStats(7);
        if ($quotaSkipped['total'] > 0) {
            $usage = $quotaSkipped['last_usage'];
            $current = isset($usage['current']) && is_numeric($usage['current']) ? (int) $usage['current'] : 0;
            $limit = isset($usage['limit']) && is_numeric($usage['limit']) ? (int) $usage['limit'] : 0;
            $plan = isset($usage['plan']) && is_string($usage['plan']) ? $usage['plan'] : 'free';
            \IPS\Output::i()->sidebar['actions']['quota'] = [
                'title' => sprintf(
                    '%d messages skipped (last 7d) — %d/%d on %s. Upgrade →',
                    $quotaSkipped['total'],
                    $current,
                    $limit,
                    $plan,
                ),
                'icon' => 'exclamation-triangle',
                'link' => \IPS\Http\Url::external('https://spamtroll.io/dashboard/billing'),
                'target' => '_blank',
            ];
        }

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_dashboard');
        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('spamtroll', 'spamtroll', 'admin')->dashboard(
            $stats,
            $recentLogs,
            $apiStatus,
            $isConfigured,
            $isEnabled,
            json_encode($chartLabels),
            json_encode($chartTotal),
            json_encode($chartBlocked),
        );
    }

    /**
     * Check API status
     *
     * @return array
     */
    protected function checkApiStatus(): array
    {
        if (empty(\IPS\Settings::i()->spamtroll_api_key)) {
            return [
                'status' => 'not_configured',
                'message' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_not_configured'),
            ];
        }

        try {
            $client = \IPS\spamtroll\Application::apiClient();
            $response = $client->testConnection();

            if ($response->success) {
                return [
                    'status' => 'online',
                    'message' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_online'),
                ];
            }

            return [
                'status' => 'error',
                'message' => $response->error ?: \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_error'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
