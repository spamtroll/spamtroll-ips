<?php
/**
 * @brief       Spamtroll Dashboard Controller
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 */

namespace IPS\spamtroll\modules\admin\spamtroll;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
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
    public static $csrfProtected = true;

    /**
     * Execute
     *
     * @return void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_dashboard');
        parent::execute();
    }

    /**
     * Dashboard view
     *
     * @return void
     */
    protected function manage()
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
            $chartTotal[]  = $day['total'];
            $chartBlocked[] = $day['blocked'];
        }

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_dashboard');
        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('spamtroll', 'spamtroll', 'admin')->dashboard(
            $stats, $recentLogs, $apiStatus, $isConfigured, $isEnabled,
            json_encode($chartLabels), json_encode($chartTotal), json_encode($chartBlocked)
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
