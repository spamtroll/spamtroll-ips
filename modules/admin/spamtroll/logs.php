<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Logs Controller
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
 * Logs Controller
 */
class _logs extends \IPS\Dispatcher\Controller
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
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_logs');
        $cssV = (string) filemtime(\IPS\ROOT_PATH . '/applications/spamtroll/dev/css/admin/spamtroll/styles.css');
        \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, array_map(fn ($u) => ((string) $u) . '?v=' . $cssV, \IPS\Theme::i()->css('spamtroll/styles.css', 'spamtroll', 'admin')));
        \IPS\Output::i()->jsFiles = array_merge(\IPS\Output::i()->jsFiles, \IPS\Output::i()->js('spamtroll.js', 'spamtroll', 'admin'));
        parent::execute();
    }

    /**
     * Logs list
     */
    protected function manage(): void
    {
        // Create table
        $table = new \IPS\Helpers\Table\Db('spamtroll_logs', \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'));

        $table->langPrefix = 'spamtroll_';

        // Columns
        $table->include = [
            'log_id',
            'log_date',
            'log_member_id',
            'log_content_type',
            'log_status',
            'log_spam_score',
            'log_action_taken',
            'log_ip_address',
            'log_submission_id',
        ];

        $table->mainColumn = 'log_id';

        // Sorting
        $table->sortBy = $table->sortBy ?: 'log_date';
        $table->sortDirection = $table->sortDirection ?: 'desc';

        // Filters
        $table->filters = [
            'spamtroll_filter_all' => null,
            'spamtroll_filter_blocked' => "log_status='blocked'",
            'spamtroll_filter_suspicious' => "log_status='suspicious'",
            'spamtroll_filter_safe' => "log_status='safe'",
            'spamtroll_filter_posts' => "log_content_type='post'",
            'spamtroll_filter_messages' => "log_content_type='message'",
            'spamtroll_filter_registrations' => "log_content_type='registration'",
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
            'log_content_type' => fn ($val) => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_content_type_' . $val),
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
                return "<span class='{$class}'>" . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_status_' . $val) . '</span>';
            },
            'log_spam_score' => function ($val) {
                $percent = round($val * 100);
                return "{$percent}%";
            },
            'log_action_taken' => fn ($val) => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_action_' . $val),
            'log_date' => fn ($val) => \IPS\DateTime::ts($val)->html(),
            'log_submission_id' => function ($val) {
                if (!$val) {
                    return '<span class="ipsType_light">—</span>';
                }
                $escaped = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                $short = substr($val, 0, 8);
                return '<span class="spamtroll-uuid">'
                     . '<code title="' . $escaped . '">' . $short . '…</code>'
                     . '<button type="button" class="ipsButton ipsButton_verySmall ipsButton_light spamtroll-copy-btn" '
                     . 'data-clipboard="' . $escaped . '" title="Copy UUID">'
                     . '<i class="fa fa-copy"></i>'
                     . '</button>'
                     . '</span>';
            },
        ];

        // Row buttons
        $table->rowButtons = fn ($row) => [
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

        // Inline clipboard handler for the UUID copy button — delegates so it
        // still works after the table re-renders via AJAX filtering.
        $copiedLabel = htmlspecialchars(\IPS\Member::loggedIn()->language()->get('spamtroll_copied'), ENT_QUOTES, 'UTF-8');
        $copyScript = <<<HTML
            <script>
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.spamtroll-copy-btn');
                if (!btn) return;
                e.preventDefault();
                const value = btn.getAttribute('data-clipboard') || '';
                const done = () => {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fa fa-check"></i> {$copiedLabel}';
                    setTimeout(() => { btn.innerHTML = original; }, 1200);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(() => {
                        const ta = document.createElement('textarea');
                        ta.value = value; document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); } catch (_) {}
                        ta.remove(); done();
                    });
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = value; document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); } catch (_) {}
                    ta.remove(); done();
                }
            });
            </script>
            HTML;

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_logs');
        \IPS\Output::i()->output = $buttons . $table . $copyScript;
    }

    /**
     * View log details
     */
    protected function view(): void
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
                        <td><div class="spamtroll-content-preview">' . htmlspecialchars($log['log_content_preview']) . '</div></td>
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
     */
    protected function delete(): void
    {
        \IPS\Session::i()->csrfCheck();

        $id = \IPS\Request::i()->id;

        \IPS\Db::i()->delete('spamtroll_logs', ['log_id=?', $id]);

        \IPS\Output::i()->redirect(
            \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'),
            'deleted',
        );
    }

    /**
     * Clear all logs
     */
    protected function clearAll(): void
    {
        \IPS\Session::i()->csrfCheck();

        \IPS\Db::i()->delete('spamtroll_logs');

        \IPS\Output::i()->redirect(
            \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=logs'),
            'spamtroll_logs_cleared',
        );
    }

    /**
     * Export logs
     */
    protected function export(): void
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
