<?php
// IPS security check
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

return array(
    // Test Connection (settings page)
    'spamtroll_testing'             => 'Testing...',
    'spamtroll_connection_success'  => 'Connection successful! API is working correctly.',
    'spamtroll_connection_failed'   => 'Connection failed. Please check your API key and URL.',
    'spamtroll_connection_error'    => 'Connection error',

    // Dashboard chart labels
    'spamtroll_chart_total'         => 'Total',
    'spamtroll_chart_blocked'       => 'Blocked',

    // Confirmation dialogs (logs page)
    'spamtroll_delete_log_confirm'  => 'Are you sure you want to delete this log entry?',
);
