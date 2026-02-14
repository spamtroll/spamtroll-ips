<?php
/**
 * @brief       Spamtroll Logs Controller
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
 * Logs Controller
 */
class _logs extends \IPS\Dispatcher\Controller
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
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_logs');
        parent::execute();
    }

    /**
     * Logs list
     *
     * @return void
     */
    protected function manage()
    {
        // Create table
        $table = new \IPS\Helpers\Table\Db('spamtroll_logs', \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'));

        $table->langPrefix = 'spamtroll_log_';

        // Columns
        $table->include = [
            'log_id',
            'log_member_id',
            'log_content_type',
            'log_status',
            'log_spam_score',
            'log_action_taken',
            'log_ip_address',
            'log_date',
        ];

        $table->mainColumn = 'log_id';

        // Sorting
        $table->sortBy = $table->sortBy ?: 'log_date';
        $table->sortDirection = $table->sortDirection ?: 'desc';

        // Filters
        $table->filters = [
            'spamtroll_filter_all' => NULL,
            'spamtroll_filter_blocked' => "log_status='blocked'",
            'spamtroll_filter_suspicious' => "log_status='suspicious'",
            'spamtroll_filter_safe' => "log_status='safe'",
        ];

        // Quick search
        $table->quickSearch = 'log_ip_address';

        // Parsers
        $table->parsers = [
            'log_member_id' => function ($val, $row) {
                if (!$val) {
                    return \IPS\Member::loggedIn()->language()->addToStack('spamtroll_guest');
                }
                try {
                    $member = \IPS\Member::load($val);
                    return $member->link();
                } catch (\Exception $e) {
                    return \IPS\Member::loggedIn()->language()->addToStack('spamtroll_deleted_member');
                }
            },
            'log_content_type' => function ($val) {
                return \IPS\Member::loggedIn()->language()->addToStack('spamtroll_content_type_' . $val);
            },
            'log_status' => function ($val) {
                $class = 'ipsBadge';
                switch ($val) {
                    case 'blocked':
                        $class .= ' ipsBadge_negative';
                        break;
                    case 'suspicious':
                        $class .= ' ipsBadge_warning';
                        break;
                    case 'safe':
                        $class .= ' ipsBadge_positive';
                        break;
                }
                return "<span class='{$class}'>" . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_status_' . $val) . "</span>";
            },
            'log_spam_score' => function ($val) {
                $percent = round($val * 100);
                return "{$percent}%";
            },
            'log_action_taken' => function ($val) {
                return \IPS\Member::loggedIn()->language()->addToStack('spamtroll_action_' . $val);
            },
            'log_date' => function ($val) {
                return \IPS\DateTime::ts($val)->html();
            },
        ];

        // Row buttons
        $table->rowButtons = function ($row) {
            return [
                'view' => [
                    'icon' => 'search',
                    'title' => 'spamtroll_view_details',
                    'link' => \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs&do=view&id=' . $row['log_id']),
                    'data' => ['ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_details')],
                ],
                'delete' => [
                    'icon' => 'times-circle',
                    'title' => 'delete',
                    'link' => \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs&do=delete&id=' . $row['log_id'])->csrf(),
                    'data' => ['confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_delete_log_confirm')],
                ],
            ];
        };

        // Action buttons
        $clearUrl = \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs&do=clearAll')->csrf();
        $exportUrl = \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs&do=export')->csrf();

        $buttons = '<div class="ipsPad ipsAreaBackground_light">
            <a href="' . $exportUrl . '" class="ipsButton ipsButton_small ipsButton_light">
                <i class="fa fa-download"></i> ' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_export_logs') . '
            </a>
            <a href="' . $clearUrl . '" class="ipsButton ipsButton_small ipsButton_negative" data-confirm data-confirmMessage="' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_delete_log_confirm') . '">
                <i class="fa fa-trash"></i> ' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_clear_all_logs') . '
            </a>
        </div>';

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_logs');
        \IPS\Output::i()->output = $buttons . $table;
    }

    /**
     * View log details
     *
     * @return void
     */
    protected function view()
    {
        $id = \IPS\Request::i()->id;

        try {
            $log = \IPS\Db::i()->select('*', 'spamtroll_logs', ['log_id=?', $id])->first();

            $symbols = $log['log_symbols'] ? json_decode($log['log_symbols'], true) : [];
            $threats = $log['log_threat_categories'] ? json_decode($log['log_threat_categories'], true) : [];

            $member = null;
            if ($log['log_member_id']) {
                try {
                    $member = \IPS\Member::load($log['log_member_id']);
                } catch (\Exception $e) {
                    // Member deleted
                }
            }

            // Build details HTML
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

            $memberHtml = $member ? $member->link() : ($log['log_member_id'] ? \IPS\Member::loggedIn()->language()->addToStack('spamtroll_deleted_member') : \IPS\Member::loggedIn()->language()->addToStack('spamtroll_guest'));

            $symbolsHtml = '';
            if (!empty($symbols)) {
                foreach ($symbols as $symbol) {
                    $symbolsHtml .= '<span class="ipsBadge ipsBadge_neutral">' . htmlspecialchars($symbol) . '</span> ';
                }
            } else {
                $symbolsHtml = '-';
            }

            $threatsHtml = '';
            if (!empty($threats)) {
                foreach ($threats as $threat) {
                    $threatsHtml .= '<span class="ipsBadge ipsBadge_warning">' . htmlspecialchars($threat) . '</span> ';
                }
            } else {
                $threatsHtml = '-';
            }

            $html = '<div class="ipsPad">
                <table class="ipsTable ipsTable_zebra">
                    <tr>
                        <th class="spamtroll-detail-label">' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_id') . '</th>
                        <td>' . (int) $log['log_id'] . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_date') . '</th>
                        <td>' . \IPS\DateTime::ts($log['log_date'])->html() . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_member_id') . '</th>
                        <td>' . $memberHtml . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_content_type') . '</th>
                        <td>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_content_type_' . $log['log_content_type']) . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_status') . '</th>
                        <td>' . $statusBadge . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_spam_score') . '</th>
                        <td>' . round($log['log_spam_score'] * 100) . '%</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_action_taken') . '</th>
                        <td>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_action_' . $log['log_action_taken']) . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_ip_address') . '</th>
                        <td>' . htmlspecialchars($log['log_ip_address']) . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_symbols') . '</th>
                        <td>' . $symbolsHtml . '</td>
                    </tr>
                    <tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_threat_categories') . '</th>
                        <td>' . $threatsHtml . '</td>
                    </tr>';

            if ($log['log_content_preview']) {
                $html .= '<tr>
                        <th>' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_log_content_preview') . '</th>
                        <td><div class="spamtroll-content-preview-php">' . htmlspecialchars($log['log_content_preview']) . '</div></td>
                    </tr>';
            }

            $html .= '</table></div>';

            \IPS\Output::i()->output = $html;
        } catch (\UnderflowException $e) {
            \IPS\Output::i()->error('spamtroll_log_not_found', '2ST100/1', 404);
        }
    }

    /**
     * Delete log entry
     *
     * @return void
     */
    protected function delete()
    {
        \IPS\Session::i()->csrfCheck();

        $id = \IPS\Request::i()->id;

        \IPS\Db::i()->delete('spamtroll_logs', ['log_id=?', $id]);

        \IPS\Output::i()->redirect(
            \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'),
            'deleted'
        );
    }

    /**
     * Clear all logs
     *
     * @return void
     */
    protected function clearAll()
    {
        \IPS\Session::i()->csrfCheck();

        \IPS\Db::i()->delete('spamtroll_logs');

        \IPS\Output::i()->redirect(
            \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'),
            'spamtroll_logs_cleared'
        );
    }

    /**
     * Export logs
     *
     * @return void
     */
    protected function export()
    {
        \IPS\Session::i()->csrfCheck();

        $logs = [];
        foreach (\IPS\Db::i()->select('*', 'spamtroll_logs', null, 'log_date DESC', 10000) as $row) {
            $row['log_symbols'] = $row['log_symbols'] ? json_decode($row['log_symbols'], true) : [];
            $row['log_threat_categories'] = $row['log_threat_categories'] ? json_decode($row['log_threat_categories'], true) : [];
            $row['log_date_formatted'] = date('Y-m-d H:i:s', $row['log_date']);
            $logs[] = $row;
        }

        $output = json_encode($logs, JSON_PRETTY_PRINT);

        \IPS\Output::i()->sendHeader('Content-Type: application/json');
        \IPS\Output::i()->sendHeader('Content-Disposition: attachment; filename="spamtroll_logs_' . date('Y-m-d') . '.json"');
        \IPS\Output::i()->sendOutput($output);
    }
}
