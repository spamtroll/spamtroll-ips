<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Settings Controller
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
 * Settings Controller
 */
class _settings extends \IPS\Dispatcher\Controller
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
        \IPS\Dispatcher::i()->checkAcpPermission('spamtroll_settings');
        $cssV = (string) filemtime(\IPS\ROOT_PATH . '/applications/spamtroll/dev/css/admin/spamtroll/styles.css');
        \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, array_map(fn ($u) => ((string) $u) . '?v=' . $cssV, \IPS\Theme::i()->css('spamtroll/styles.css', 'spamtroll', 'admin')));
        \IPS\Output::i()->jsFiles = array_merge(\IPS\Output::i()->jsFiles, \IPS\Output::i()->js('spamtroll.js', 'spamtroll', 'admin'));
        parent::execute();
    }

    /**
     * Settings form
     */
    protected function manage(): void
    {
        $form = new \IPS\Helpers\Form();

        // Simplified single-tab form. Everything the non-technical admin
        // needs: enable/disable, API key, sensitivity preset, what to
        // scan, and which groups to skip. Everything else (API URL,
        // timeout, numeric thresholds, action matrix, log retention) is
        // pinned to sensible defaults in code.
        $form->addHeader('spamtroll_header_api_config');

        $form->add(new \IPS\Helpers\Form\YesNo(
            'spamtroll_enabled',
            \IPS\Settings::i()->spamtroll_enabled,
            false,
            [],
            null,
            null,
            null,
            'spamtroll_enabled',
        ));

        $form->add(new \IPS\Helpers\Form\Text(
            'spamtroll_api_key',
            \IPS\Settings::i()->spamtroll_api_key,
            false,
            ['size' => 50],
            null,
            null,
            null,
            'spamtroll_api_key',
        ));

        // Written by the installer since 1.0.0 and read by nothing until
        // 1.0.3, so a forum pointed at a staging backend talked to
        // production. Now that ClientFactory reads it, it needs a way in —
        // and the connection-test script has always looked for this field.
        $form->add(new \IPS\Helpers\Form\Text(
            'spamtroll_api_url',
            \IPS\Settings::i()->spamtroll_api_url ?: \Spamtroll\Sdk\ClientConfig::DEFAULT_BASE_URL,
            false,
            ['size' => 50, 'placeholder' => \Spamtroll\Sdk\ClientConfig::DEFAULT_BASE_URL],
            null,
            null,
            null,
            'spamtroll_api_url',
        ));

        $form->addHeader('spamtroll_header_thresholds');

        // The preset decides what the forum does with each verdict the
        // backend returns — see \IPS\spamtroll\Scanner\Policy. It no longer
        // moves a numeric threshold: this application was classifying a
        // normalised score against thresholds of its own, so "Balanced"
        // blocked at a raw 21.0 while the backend blocked at 15.0.
        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_sensitivity',
            \IPS\Settings::i()->spamtroll_sensitivity ?: 'balanced',
            false,
            [
                'options' => [
                    'lenient' => 'spamtroll_sensitivity_lenient',
                    'balanced' => 'spamtroll_sensitivity_balanced',
                    'strict' => 'spamtroll_sensitivity_strict',
                ],
            ],
            null,
            null,
            null,
            'spamtroll_sensitivity',
        ));

        $form->addHeader('spamtroll_header_content_types');

        // Single scope dropdown replaces independent YesNo toggles. Posts
        // and registrations only — private messages were removed in 1.0.2
        // by request: scanning private mail surprised forum admins and
        // didn't carry its weight against the API quota.
        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_scan_scope',
            \IPS\Settings::i()->spamtroll_scan_scope ?: 'all',
            false,
            [
                'options' => [
                    'all' => 'spamtroll_scope_all',        // posts + registrations
                    'posts_only' => 'spamtroll_scope_posts_only', // posts only
                    'off' => 'spamtroll_scope_off',        // nothing (but still keep plugin installed)
                ],
            ],
            null,
            null,
            null,
            'spamtroll_scan_scope',
        ));

        $form->addHeader('spamtroll_header_bypass');

        $form->add(new \IPS\Helpers\Form\Select(
            'spamtroll_bypass_groups',
            \IPS\Settings::i()->spamtroll_bypass_groups ? explode(',', \IPS\Settings::i()->spamtroll_bypass_groups) : [],
            false,
            ['options' => \IPS\Member\Group::groups(true, false), 'multiple' => true, 'parse' => 'normal'],
            null,
            null,
            null,
            'spamtroll_bypass_groups',
        ));

        // Trust threshold: members with more than N forum posts skip
        // scanning entirely. Catches the "established member, never spams"
        // case without admins having to put them in a bypass group.
        $form->add(new \IPS\Helpers\Form\Number(
            'spamtroll_bypass_min_posts',
            (int) \IPS\Settings::i()->spamtroll_bypass_min_posts,
            false,
            ['min' => 0, 'max' => 100000],
            null,
            null,
            \IPS\Member::loggedIn()->language()->addToStack('spamtroll_posts_unit'),
            'spamtroll_bypass_min_posts',
        ));

        // Process form submission
        if ($values = $form->values(true)) {
            // Convert bypass groups array to comma-separated string
            if (isset($values['spamtroll_bypass_groups']) && \is_array($values['spamtroll_bypass_groups'])) {
                $values['spamtroll_bypass_groups'] = implode(',', $values['spamtroll_bypass_groups']);
            }

            // The numeric thresholds are only consulted when
            // spamtroll_override_thresholds is on. Keep them in step with the
            // preset anyway, so a forum that switches the override on gets
            // something coherent rather than whatever it last had.
            switch ($values['spamtroll_sensitivity'] ?? 'balanced') {
                case 'strict':
                    $values['spamtroll_spam_threshold'] = 0.5;
                    $values['spamtroll_suspicious_threshold'] = 0.3;
                    break;
                case 'lenient':
                    $values['spamtroll_spam_threshold'] = 0.85;
                    $values['spamtroll_suspicious_threshold'] = 0.6;
                    break;
                case 'balanced':
                default:
                    $values['spamtroll_spam_threshold'] = 0.7;
                    $values['spamtroll_suspicious_threshold'] = 0.4;
                    break;
            }

            $apiUrl = $values['spamtroll_api_url'] ?? '';
            $values['spamtroll_api_url'] = (\is_scalar($apiUrl) ? trim((string) $apiUrl) : '')
                ?: \Spamtroll\Sdk\ClientConfig::DEFAULT_BASE_URL;

            $scope = $values['spamtroll_scan_scope'] ?? 'all';
            $values['spamtroll_check_posts'] = $scope !== 'off';
            $values['spamtroll_check_registrations'] = ($scope === 'all');

            // Number form returns numeric; keep as int for cleaner storage
            // and so empty input lands as 0 instead of "" that would later
            // stringly-truthy itself into a bypass-everyone bug.
            $values['spamtroll_bypass_min_posts'] = max(0, (int) ($values['spamtroll_bypass_min_posts'] ?? 0));

            // Pin moderate/block actions — the 4x4 action matrix is
            // collapsed to a single sensible policy.
            $values['spamtroll_action_blocked'] = 'block';
            $values['spamtroll_action_suspicious'] = 'moderate';
            $values['spamtroll_timeout'] = 5;
            $values['spamtroll_log_retention_days'] = 30;

            // Not on the form, and not to be reset by saving it either.
            unset($values['spamtroll_override_thresholds'], $values['spamtroll_anonymize_ip']);

            $form->saveAsSettings($values);

            \IPS\Output::i()->redirect(
                \IPS\Http\Url::internal('app=spamtroll&module=spamtroll&controller=settings'),
                'saved',
            );
        }

        \IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__spamtroll_spamtroll_settings');
        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('spamtroll', 'spamtroll', 'admin')->settings($form);
    }

    /**
     * Test API connection
     */
    protected function testConnection(): void
    {
        \IPS\Session::i()->csrfCheck();

        try {
            $apiKey = \IPS\Request::i()->api_key ?: null;

            $client = $apiKey
                ? \IPS\spamtroll\Scanner\ClientFactory::managementClient((string) $apiKey)
                : \IPS\spamtroll\Application::apiClient();
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
        } catch (\Throwable $t) {
            /* The detail goes to the log, not to the browser: an exception
             * message from the HTTP layer can carry the request URL, and the
             * request carries the API key. */
            \IPS\spamtroll\Log\Recorder::note('test connection', $t);

            \IPS\Output::i()->json([
                'success' => false,
                'message' => \IPS\Member::loggedIn()->language()->addToStack('spamtroll_connection_failed'),
                'code' => $t instanceof \Spamtroll\Sdk\Exception\SpamtrollException ? $t->httpCode : 0,
            ]);
        }
    }
}
