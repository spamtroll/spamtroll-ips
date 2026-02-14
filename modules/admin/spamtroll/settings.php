<?php
/**
 * @brief       Spamtroll Settings Controller
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
 * Settings Controller
 */
class _settings extends \IPS\Dispatcher\Controller
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
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_settings');
        parent::execute();
    }

    /**
     * Settings form
     *
     * @return void
     */
    protected function manage()
    {
        $form = new \IPS\Helpers\Form;

        // API Configuration Tab
        $form->addTab('spamtroll_tab_api');
        $form->addHeader('spamtroll_header_api_config');

        $form->add(new \IPS\Helpers\Form\YesNo(
            'spamtroll_enabled',
            \IPS\Settings::i()->spamtroll_enabled,
            false,
            [],
            null,
            null,
            null,
            'spamtroll_enabled'
        ));

        $form->add(new \IPS\Helpers\Form\Text(
            'spamtroll_api_key',
            \IPS\Settings::i()->spamtroll_api_key,
            false,
            ['size' => 50],
            null,
            null,
            null,
            'spamtroll_api_key'
        ));

        $form->add(new \IPS\Helpers\Form\Url(
            'spamtroll_api_url',
            \IPS\Settings::i()->spamtroll_api_url ?: 'http://spamtroll-api.local/api/v1',
            false,
            [],
            null,
            null,
            null,
            'spamtroll_api_url'
        ));

        $form->add(new \IPS\Helpers\Form\Number(
            'spamtroll_timeout',
            \IPS\Settings::i()->spamtroll_timeout ?: 5,
            false,
            ['min' => 1, 'max' => 30],
            null,
            null,
            \IPS\Member::loggedIn()->language()->addToStack('spamtroll_seconds'),
            'spamtroll_timeout'
        ));

        // Detection Settings Tab
        $form->addTab('spamtroll_tab_detection');
        $form->addHeader('spamtroll_header_thresholds');

        $form->add(new \IPS\Helpers\Form\Number(
            'spamtroll_spam_threshold',
            \IPS\Settings::i()->spamtroll_spam_threshold ?: 0.7,
            false,
            ['min' => 0, 'max' => 1, 'decimals' => 2],
            null,
            null,
            null,
            'spamtroll_spam_threshold'
        ));

        $form->add(new \IPS\Helpers\Form\Number(
            'spamtroll_suspicious_threshold',
            \IPS\Settings::i()->spamtroll_suspicious_threshold ?: 0.4,
            false,
            ['min' => 0, 'max' => 1, 'decimals' => 2],
            null,
            null,
            null,
            'spamtroll_suspicious_threshold'
        ));

        $form->addHeader('spamtroll_header_content_types');

        $form->add(new \IPS\Helpers\Form\YesNo(
            'spamtroll_check_posts',
            \IPS\Settings::i()->spamtroll_check_posts ?? true,
            false,
            [],
            null,
            null,
            null,
            'spamtroll_check_posts'
        ));

        $form->add(new \IPS\Helpers\Form\YesNo(
            'spamtroll_check_messages',
            \IPS\Settings::i()->spamtroll_check_messages ?? true,
            false,
            [],
            null,
            null,
            null,
            'spamtroll_check_messages'
        ));

        $form->add(new \IPS\Helpers\Form\YesNo(
            'spamtroll_check_registrations',
            \IPS\Settings::i()->spamtroll_check_registrations ?? true,
            false,
            [],
            null,
            null,
            null,
            'spamtroll_check_registrations'
        ));

        // Actions Tab
        $form->addTab('spamtroll_tab_actions');
        $form->addHeader('spamtroll_header_actions');

        $actionOptions = [
            'block' => 'spamtroll_action_block',
            'moderate' => 'spamtroll_action_moderate',
            'warn' => 'spamtroll_action_warn',
            'allow' => 'spamtroll_action_allow',
        ];

        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_action_blocked',
            \IPS\Settings::i()->spamtroll_action_blocked ?: 'block',
            false,
            ['options' => $actionOptions],
            null,
            null,
            null,
            'spamtroll_action_blocked'
        ));

        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_action_suspicious',
            \IPS\Settings::i()->spamtroll_action_suspicious ?: 'moderate',
            false,
            ['options' => $actionOptions],
            null,
            null,
            null,
            'spamtroll_action_suspicious'
        ));

        // Bypass Settings Tab
        $form->addTab('spamtroll_tab_bypass');
        $form->addHeader('spamtroll_header_bypass');

        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_bypass_groups',
            \IPS\Settings::i()->spamtroll_bypass_groups ? explode(',', \IPS\Settings::i()->spamtroll_bypass_groups) : [],
            false,
            ['options' => \IPS\Member\Group::groups(true, false), 'multiple' => true, 'parse' => 'normal'],
            null,
            null,
            null,
            'spamtroll_bypass_groups'
        ));

        // Maintenance Tab
        $form->addTab('spamtroll_tab_maintenance');
        $form->addHeader('spamtroll_header_logs');

        $form->add(new \IPS\Helpers\Form\Number(
            'spamtroll_log_retention_days',
            \IPS\Settings::i()->spamtroll_log_retention_days ?: 30,
            false,
            ['min' => 1, 'max' => 365],
            null,
            null,
            \IPS\Member::loggedIn()->language()->addToStack('spamtroll_days'),
            'spamtroll_log_retention_days'
        ));

        // Process form submission
        if ($values = $form->values(true)) {
            // Convert bypass groups array to comma-separated string
            if (isset($values['spamtroll_bypass_groups']) && \is_array($values['spamtroll_bypass_groups'])) {
                $values['spamtroll_bypass_groups'] = implode(',', $values['spamtroll_bypass_groups']);
            }

            $form->saveAsSettings($values);

            \IPS\Output::i()->redirect(
                \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=settings'),
                'saved'
            );
        }

        // Test connection button
        $testButton = '<div class="ipsPad">
            <button type="button" class="ipsButton ipsButton_primary" onclick="testSpamtrollConnection()">
                <i class="fa fa-plug"></i> ' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_test_connection') . '
            </button>
            <span id="spamtrollTestResult" class="ipsType_light" style="margin-left: 10px;"></span>
        </div>
        <script>
        function testSpamtrollConnection() {
            var resultSpan = document.getElementById("spamtrollTestResult");
            resultSpan.innerHTML = "<i class=\"fa fa-spinner fa-spin\"></i> ' . \IPS\Member::loggedIn()->language()->addToStack('spamtroll_testing') . '";

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "' . \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=settings&do=testConnection')->csrf() . '", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resultSpan.innerHTML = "<span class=\"ipsType_success\"><i class=\"fa fa-check\"></i> " + response.message + "</span>";
                        } else {
                            resultSpan.innerHTML = "<span class=\"ipsType_warning\"><i class=\"fa fa-times\"></i> " + response.message + "</span>";
                        }
                    } catch(e) {
                        resultSpan.innerHTML = "<span class=\"ipsType_warning\"><i class=\"fa fa-times\"></i> Error</span>";
                    }
                }
            };
            xhr.send("");
        }
        </script>';

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_settings');
        \IPS\Output::i()->output = $form . $testButton;
    }

    /**
     * Test API connection
     *
     * @return void
     */
    protected function testConnection()
    {
        \IPS\Session::i()->csrfCheck();

        try {
            $client = \IPS\spamtroll\Application::apiClient();
            $response = $client->testConnection();

            if ($response->success) {
                \IPS\Output::i()->json([
                    'success' => true,
                    'message' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_connection_success'),
                ]);
            } else {
                \IPS\Output::i()->json([
                    'success' => false,
                    'message' => $response->error ?: \IPS\Member::loggedIn()->language()->addToStack('spamtroll_connection_failed'),
                ]);
            }
        } catch (\Exception $e) {
            \IPS\Output::i()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
