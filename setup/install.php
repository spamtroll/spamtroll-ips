<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Anti-Spam Install Step
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

namespace IPS\spamtroll\setup\install;

/**
 * Install Step 1
 */
class _Install
{
    /**
     * Step 1 - Create database tables and insert default settings
     *
     * @param array $data Multi-redirector data
     *
     * @return array|null
     */
    public function step1($data)
    {
        /* Use the same definition as the native IPS installer and checker. */
        /** @var array<string, array{columns: array<string, array<string, mixed>>}> $schema */
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $table = $schema['spamtroll_logs'];

        if (!\IPS\Db::i()->checkForTable('spamtroll_logs')) {
            \IPS\Db::i()->createTable($table);
        } else {
            /* Keep the existing-install path additive. Comment repair belongs
             * to the versioned upgrade so rerunning this installer is cheap. */
            foreach (['log_submission_id', 'log_email_hash'] as $column) {
                if (!\IPS\Db::i()->checkForColumn('spamtroll_logs', $column)) {
                    \IPS\Db::i()->addColumn('spamtroll_logs', $table['columns'][$column]);
                }
            }
        }

        /* Insert default settings */
        $defaults = [
            'spamtroll_api_key' => '',
            'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
            'spamtroll_enabled' => '0',
            'spamtroll_sensitivity' => 'balanced',
            'spamtroll_scan_scope' => 'all',
            'spamtroll_override_thresholds' => '0',
            'spamtroll_spam_threshold' => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
            'spamtroll_check_posts' => '1',
            'spamtroll_check_registrations' => '1',
            'spamtroll_action_blocked' => 'block',
            'spamtroll_action_suspicious' => 'moderate',
            'spamtroll_bypass_groups' => '',
            'spamtroll_bypass_min_posts' => '0',
            'spamtroll_anonymize_ip' => '0',
            'spamtroll_log_retention_days' => '30',
            'spamtroll_timeout' => '5',
            'spamtroll_quota_skipped_log' => '',
        ];

        foreach ($defaults as $key => $value) {
            try {
                \IPS\Db::i()->insert('core_sys_conf_settings', [
                    'conf_key' => $key,
                    'conf_value' => $value,
                    'conf_default' => $value,
                    'conf_app' => 'spamtroll',
                ]);
            } catch (\IPS\Db\Exception $e) {
                /* Setting may already exist, skip */
            }
        }

        return true;
    }
}
