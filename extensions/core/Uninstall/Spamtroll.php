<?php
/**
 * @brief       Spamtroll Uninstall Extension
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 * @version     1.0.0
 */

namespace IPS\spamtroll\extensions\core\Uninstall;

/* To prevent direct access */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Uninstall callback
 */
class _Spamtroll
{
    /**
     * Code to execute before the application has been uninstalled
     *
     * @param string $application Application directory
     * @return void
     */
    public function preUninstall( $application )
    {
    }

    /**
     * Code to execute after the application has been uninstalled
     *
     * @param string $application Application directory
     * @return void
     */
    public function postUninstall( $application )
    {
        try
        {
            if ( \IPS\Db::i()->checkForTable( 'spamtroll_logs' ) )
            {
                \IPS\Db::i()->dropTable( 'spamtroll_logs' );
            }
        }
        catch ( \Exception $e ) {}

        try
        {
            \IPS\Db::i()->delete( 'core_tasks', [ 'app=?', 'spamtroll' ] );
        }
        catch ( \Exception $e ) {}

        try
        {
            \IPS\Db::i()->delete( 'core_log', [ 'category=?', 'spamtroll' ] );
        }
        catch ( \Exception $e ) {}
    }
}
