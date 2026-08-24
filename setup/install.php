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
        /* Create spamtroll_logs table if it doesn't exist */
        if (!\IPS\Db::i()->checkForTable('spamtroll_logs')) {
            \IPS\Db::i()->createTable([
                'name' => 'spamtroll_logs',
                'columns' => [
                    [
                        'name' => 'log_id',
                        'type' => 'BIGINT',
                        'length' => 20,
                        'unsigned' => true,
                        'auto_increment' => true,
                        'allow_null' => false,
                    ],
                    [
                        'name' => 'log_member_id',
                        'type' => 'INT',
                        'length' => 11,
                        'unsigned' => true,
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_content_type',
                        'type' => 'VARCHAR',
                        'length' => 50,
                        'allow_null' => false,
                        'default' => '',
                    ],
                    [
                        'name' => 'log_content_id',
                        'type' => 'BIGINT',
                        'length' => 20,
                        'unsigned' => true,
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_ip_address',
                        'type' => 'VARCHAR',
                        'length' => 46,
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_status',
                        'type' => 'VARCHAR',
                        'length' => 20,
                        'allow_null' => false,
                        'default' => 'safe',
                    ],
                    [
                        'name' => 'log_spam_score',
                        'type' => 'DECIMAL',
                        'length' => 5,
                        'decimals' => 4,
                        'allow_null' => false,
                        'default' => '0.0000',
                    ],
                    [
                        'name' => 'log_symbols',
                        'type' => 'TEXT',
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_threat_categories',
                        'type' => 'TEXT',
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_action_taken',
                        'type' => 'VARCHAR',
                        'length' => 20,
                        'allow_null' => false,
                        'default' => 'allow',
                    ],
                    [
                        'name' => 'log_content_preview',
                        'type' => 'TEXT',
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_submission_id',
                        'type' => 'VARCHAR',
                        'length' => 36,
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_email_hash',
                        'type' => 'VARCHAR',
                        'length' => 64,
                        'allow_null' => true,
                        'default' => null,
                    ],
                    [
                        'name' => 'log_date',
                        'type' => 'INT',
                        'length' => 11,
                        'unsigned' => true,
                        'allow_null' => false,
                        'default' => 0,
                    ],
                ],
                'indexes' => [
                    [
                        'type' => 'primary',
                        'name' => 'PRIMARY',
                        'columns' => [ 'log_id' ],
                    ],
                    [
                        'type' => 'key',
                        'name' => 'log_member_id',
                        'columns' => [ 'log_member_id' ],
                    ],
                    [
                        'type' => 'key',
                        'name' => 'log_date',
                        'columns' => [ 'log_date' ],
                    ],
                    [
                        'type' => 'key',
                        'name' => 'log_status',
                        'columns' => [ 'log_status' ],
                    ],
                    [
                        'type' => 'key',
                        'name' => 'log_content_type',
                        'columns' => [ 'log_content_type' ],
                    ],
                    [
                        'type' => 'key',
                        'name' => 'log_email_hash',
                        'columns' => [ 'log_email_hash' ],
                    ],
                ],
                'collation' => 'utf8mb4_unicode_ci',
            ]);
        } else {
            /* Migration: ensure columns added after the first release exist
             * on installs created before them. */
            if (! \IPS\Db::i()->checkForColumn('spamtroll_logs', 'log_submission_id')) {
                \IPS\Db::i()->addColumn('spamtroll_logs', [
                    'name' => 'log_submission_id',
                    'type' => 'VARCHAR',
                    'length' => 36,
                    'allow_null' => true,
                    'default' => null,
                ]);
            }

            if (! \IPS\Db::i()->checkForColumn('spamtroll_logs', 'log_email_hash')) {
                \IPS\Db::i()->addColumn('spamtroll_logs', [
                    'name' => 'log_email_hash',
                    'type' => 'VARCHAR',
                    'length' => 64,
                    'allow_null' => true,
                    'default' => null,
                ]);
            }
        }

        /* Insert default settings */
        $defaults = [
            'spamtroll_api_key' => '',
            'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
            'spamtroll_enabled' => '0',
            'spamtroll_spam_threshold' => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
            'spamtroll_check_posts' => '1',
            'spamtroll_check_messages' => '1',
            'spamtroll_check_registrations' => '1',
            'spamtroll_action_blocked' => 'block',
            'spamtroll_action_suspicious' => 'moderate',
            'spamtroll_bypass_groups' => '',
            'spamtroll_log_retention_days' => '30',
            'spamtroll_timeout' => '5',
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
