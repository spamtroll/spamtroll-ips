<?php
/**
 * @brief       Spamtroll Statistics Widget
 * @author      Spamtroll
 * @copyright   (c) 2024 Spamtroll
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 * @since       01 Jan 2024
 * @version     1.0.0
 */

namespace IPS\spamtroll\widgets;

/* To prevent direct access */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * spamtrollStats Widget
 */
class _spamtrollStats extends \IPS\Widget
{
    /**
     * @brief Widget Key
     */
    public $key = 'spamtrollStats';

    /**
     * @brief App
     */
    public $app = 'spamtroll';

    /**
     * @brief Plugin
     */
    public $plugin = '';

    /**
     * Render a widget
     *
     * @return string
     */
    public function render()
    {
        $stats = \IPS\spamtroll\Application::getStatistics( 7 );

        return $this->output( $stats );
    }
}
