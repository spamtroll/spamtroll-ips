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

        // Build output HTML
        $html = $this->buildDashboardHtml($stats, $recentLogs, $apiStatus, $isConfigured, $isEnabled);

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_dashboard');
        \IPS\Output::i()->output = $html;
    }

    /**
     * Build dashboard HTML
     */
    protected function buildDashboardHtml($stats, $recentLogs, $apiStatus, $isConfigured, $isEnabled)
    {
        $settingsUrl = \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=settings');
        $logsUrl = \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs');

        // Warning messages
        $warnings = '';
        if (!$isConfigured) {
            $warnings .= '<div class="ipsMessage ipsMessage_warning"><i class="fa fa-exclamation-triangle"></i> ' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_not_configured_message') .
                ' <a href="' . $settingsUrl . '" class="ipsButton ipsButton_verySmall ipsButton_light">' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_go_to_settings') . '</a></div>';
        } elseif (!$isEnabled) {
            $warnings .= '<div class="ipsMessage ipsMessage_info"><i class="fa fa-info-circle"></i> ' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_disabled_message') .
                ' <a href="' . $settingsUrl . '" class="ipsButton ipsButton_verySmall ipsButton_light">' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_go_to_settings') . '</a></div>';
        }

        // API Status badge
        $apiStatusHtml = '';
        if ($apiStatus['status'] === 'online') {
            $apiStatusHtml = '<span class="ipsBadge ipsBadge_positive ipsBadge_large"><i class="fa fa-check"></i> ' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_online') . '</span>';
        } elseif ($apiStatus['status'] === 'not_configured') {
            $apiStatusHtml = '<span class="ipsBadge ipsBadge_neutral ipsBadge_large"><i class="fa fa-cog"></i> ' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_not_configured') . '</span>';
        } else {
            $apiStatusHtml = '<span class="ipsBadge ipsBadge_negative ipsBadge_large"><i class="fa fa-times"></i> ' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_api_error') . '</span>' .
                '<p class="ipsType_light ipsType_small">' . htmlspecialchars($apiStatus['message']) . '</p>';
        }

        // Stats cards
        $statsHtml = '
        <div class="ipsGrid ipsGrid_collapsePhone">
            <div class="ipsGrid_span3 ipsPad ipsAreaBackground_reset" style="text-align: center;">
                <div style="font-size: 2em; font-weight: bold; color: #3498db;">' . (int) $stats['total'] . '</div>
                <div class="ipsType_light">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_stat_total') . '</div>
            </div>
            <div class="ipsGrid_span3 ipsPad ipsAreaBackground_reset" style="text-align: center;">
                <div style="font-size: 2em; font-weight: bold; color: #e74c3c;">' . (int) $stats['blocked'] . '</div>
                <div class="ipsType_light">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_stat_blocked') . '</div>
            </div>
            <div class="ipsGrid_span3 ipsPad ipsAreaBackground_reset" style="text-align: center;">
                <div style="font-size: 2em; font-weight: bold; color: #f39c12;">' . (int) $stats['suspicious'] . '</div>
                <div class="ipsType_light">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_stat_suspicious') . '</div>
            </div>
            <div class="ipsGrid_span3 ipsPad ipsAreaBackground_reset" style="text-align: center;">
                <div style="font-size: 2em; font-weight: bold; color: #27ae60;">' . (int) $stats['safe'] . '</div>
                <div class="ipsType_light">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_stat_safe') . '</div>
            </div>
        </div>';

        // Recent logs table
        $logsTableHtml = '';
        if (\count($recentLogs)) {
            $logsTableHtml = '<table class="ipsTable ipsTable_responsive ipsTable_zebra">
                <thead>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_date') . '</th>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_content_type') . '</th>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_status') . '</th>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_spam_score') . '</th>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_action_taken') . '</th>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_ip_address') . '</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($recentLogs as $log) {
                $statusBadge = '';
                switch ($log['log_status']) {
                    case 'blocked':
                        $statusBadge = '<span class="ipsBadge ipsBadge_negative">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_status_blocked') . '</span>';
                        break;
                    case 'suspicious':
                        $statusBadge = '<span class="ipsBadge ipsBadge_warning">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_status_suspicious') . '</span>';
                        break;
                    default:
                        $statusBadge = '<span class="ipsBadge ipsBadge_positive">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_status_safe') . '</span>';
                }

                $date = \IPS\DateTime::ts($log['log_date'])->html();
                $contentType = \IPS\Member::loggedIn()->language()->addToStack('spamtroll_content_type_' . $log['log_content_type']);
                $action = \IPS\Member::loggedIn()->language()->addToStack('spamtroll_action_' . $log['log_action_taken']);
                $score = round($log['log_spam_score'] * 100) . '%';

                $logsTableHtml .= "<tr>
                    <td>{$date}</td>
                    <td>{$contentType}</td>
                    <td>{$statusBadge}</td>
                    <td>{$score}</td>
                    <td>{$action}</td>
                    <td>" . htmlspecialchars($log['log_ip_address']) . "</td>
                </tr>";
            }

            $logsTableHtml .= '</tbody></table>
                <div class="ipsPad ipsType_right">
                    <a href="' . $logsUrl . '" class="ipsButton ipsButton_small ipsButton_light">' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_logs_title') . ' &rarr;</a>
                </div>';
        } else {
            $logsTableHtml = '<div class="ipsPad ipsType_center ipsType_light">' .
                \IPS\Member::loggedIn()->language()->addToStack('spamtroll_no_data') . '</div>';
        }

        // Build final HTML
        return "
            {$warnings}
            <br>
            <div class='ipsGrid ipsGrid_collapsePhone'>
                <div class='ipsGrid_span4'>
                    <div class='ipsBox'>
                        <h2 class='ipsBox_title'>" . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_dashboard_api_status') . "</h2>
                        <div class='ipsBox_content ipsPad'>
                            {$apiStatusHtml}
                        </div>
                    </div>
                </div>
                <div class='ipsGrid_span8'>
                    <div class='ipsBox'>
                        <h2 class='ipsBox_title'>" . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_dashboard_stats') . "</h2>
                        <div class='ipsBox_content'>
                            {$statsHtml}
                        </div>
                    </div>
                </div>
            </div>
            <br>
            <div class='ipsBox'>
                <h2 class='ipsBox_title'>" . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_dashboard_recent') . "</h2>
                <div class='ipsBox_content'>
                    {$logsTableHtml}
                </div>
            </div>
        ";
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
