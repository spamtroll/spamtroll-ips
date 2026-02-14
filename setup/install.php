<?php
/**
 * @brief       Spamtroll Anti-Spam Install Step
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
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
     * @return array|null
     */
    public function step1( $data )
    {
        /* Create spamtroll_logs table if it doesn't exist */
        if ( !\IPS\Db::i()->checkForTable( 'spamtroll_logs' ) )
        {
            \IPS\Db::i()->createTable( array(
                'name'      => 'spamtroll_logs',
                'columns'   => array(
                    array(
                        'name'           => 'log_id',
                        'type'           => 'BIGINT',
                        'length'         => 20,
                        'unsigned'       => true,
                        'auto_increment' => true,
                        'allow_null'     => false,
                    ),
                    array(
                        'name'       => 'log_member_id',
                        'type'       => 'INT',
                        'length'     => 11,
                        'unsigned'   => true,
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_content_type',
                        'type'       => 'VARCHAR',
                        'length'     => 50,
                        'allow_null' => false,
                        'default'    => '',
                    ),
                    array(
                        'name'       => 'log_content_id',
                        'type'       => 'BIGINT',
                        'length'     => 20,
                        'unsigned'   => true,
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_ip_address',
                        'type'       => 'VARCHAR',
                        'length'     => 46,
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_status',
                        'type'       => 'VARCHAR',
                        'length'     => 20,
                        'allow_null' => false,
                        'default'    => 'safe',
                    ),
                    array(
                        'name'       => 'log_spam_score',
                        'type'       => 'DECIMAL',
                        'length'     => 5,
                        'decimals'   => 4,
                        'allow_null' => false,
                        'default'    => '0.0000',
                    ),
                    array(
                        'name'       => 'log_symbols',
                        'type'       => 'TEXT',
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_threat_categories',
                        'type'       => 'TEXT',
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_action_taken',
                        'type'       => 'VARCHAR',
                        'length'     => 20,
                        'allow_null' => false,
                        'default'    => 'allow',
                    ),
                    array(
                        'name'       => 'log_content_preview',
                        'type'       => 'TEXT',
                        'allow_null' => true,
                        'default'    => null,
                    ),
                    array(
                        'name'       => 'log_date',
                        'type'       => 'INT',
                        'length'     => 11,
                        'unsigned'   => true,
                        'allow_null' => false,
                        'default'    => 0,
                    ),
                ),
                'indexes'   => array(
                    array(
                        'type'    => 'primary',
                        'name'    => 'PRIMARY',
                        'columns' => array( 'log_id' ),
                    ),
                    array(
                        'type'    => 'key',
                        'name'    => 'log_member_id',
                        'columns' => array( 'log_member_id' ),
                    ),
                    array(
                        'type'    => 'key',
                        'name'    => 'log_date',
                        'columns' => array( 'log_date' ),
                    ),
                    array(
                        'type'    => 'key',
                        'name'    => 'log_status',
                        'columns' => array( 'log_status' ),
                    ),
                    array(
                        'type'    => 'key',
                        'name'    => 'log_content_type',
                        'columns' => array( 'log_content_type' ),
                    ),
                ),
                'collation' => 'utf8mb4_unicode_ci',
            ) );
        }

        /* Insert default settings */
        $defaults = array(
            'spamtroll_api_key'              => '',
            'spamtroll_api_url'              => 'http://spamtroll-api.local/api/v1',
            'spamtroll_enabled'              => '0',
            'spamtroll_spam_threshold'       => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
            'spamtroll_check_posts'          => '1',
            'spamtroll_check_messages'       => '1',
            'spamtroll_check_registrations'  => '1',
            'spamtroll_action_blocked'       => 'block',
            'spamtroll_action_suspicious'    => 'moderate',
            'spamtroll_bypass_groups'        => '',
            'spamtroll_log_retention_days'   => '30',
            'spamtroll_timeout'              => '5',
        );

        foreach ( $defaults as $key => $value )
        {
            try
            {
                \IPS\Db::i()->insert( 'core_sys_conf_settings', array(
                    'conf_key'     => $key,
                    'conf_value'   => $value,
                    'conf_default' => $value,
                    'conf_app'     => 'spamtroll',
                ) );
            }
            catch ( \IPS\Db\Exception $e )
            {
                /* Setting may already exist, skip */
            }
        }

        return true;
    }
}
