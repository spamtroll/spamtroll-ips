<?php

$lang = array(
    // Main
    '__app_spamtroll' => 'Spamtroll Anti-Spam',
    'module__spamtroll_spamtroll' => 'Spamtroll',

    // Menu
    'menu__spamtroll_spamtroll' => 'Spamtroll Anti-Spam',
    'menu__spamtroll_spamtroll_dashboard' => 'Dashboard',
    'menu__spamtroll_spamtroll_settings' => 'Settings',
    'menu__spamtroll_spamtroll_logs' => 'Logs',

    // Permissions
    'r__spamtroll' => 'Spamtroll Anti-Spam',
    'r__spamtroll_dashboard' => 'Can view dashboard?',
    'r__spamtroll_settings' => 'Can manage settings?',
    'r__spamtroll_logs' => 'Can view logs?',

    // Settings Tabs
    'spamtroll_tab_api' => 'API Configuration',
    'spamtroll_tab_detection' => 'Detection Settings',
    'spamtroll_tab_actions' => 'Actions',
    'spamtroll_tab_bypass' => 'Bypass',
    'spamtroll_tab_maintenance' => 'Maintenance',

    // Settings Headers
    'spamtroll_header_api_config' => 'API Configuration',
    'spamtroll_header_thresholds' => 'Spam Thresholds',
    'spamtroll_header_content_types' => 'Content Types to Check',
    'spamtroll_header_actions' => 'Spam Actions',
    'spamtroll_header_bypass' => 'Bypass Settings',
    'spamtroll_header_logs' => 'Log Settings',

    // Settings Fields
    'spamtroll_enabled' => 'Enable Spamtroll',
    'spamtroll_enabled_desc' => 'Enable or disable spam checking globally.',
    'spamtroll_api_key' => 'API Key',
    'spamtroll_api_key_desc' => 'Enter your Spamtroll API key. Get one at https://spamtroll.io',
    'spamtroll_api_url' => 'API URL',
    'spamtroll_api_url_desc' => 'Base URL for the Spamtroll API.',
    'spamtroll_timeout' => 'API Timeout',
    'spamtroll_timeout_desc' => 'Timeout for API requests.',
    'spamtroll_seconds' => 'seconds',
    'spamtroll_days' => 'days',

    'spamtroll_spam_threshold' => 'Spam Threshold',
    'spamtroll_spam_threshold_desc' => 'Score above this value will be treated as spam (0.0 - 1.0).',
    'spamtroll_suspicious_threshold' => 'Suspicious Threshold',
    'spamtroll_suspicious_threshold_desc' => 'Score above this value will be treated as suspicious (0.0 - 1.0).',

    'spamtroll_check_posts' => 'Check Forum Posts',
    'spamtroll_check_posts_desc' => 'Enable spam checking for forum posts.',
    'spamtroll_check_messages' => 'Check Private Messages',
    'spamtroll_check_messages_desc' => 'Enable spam checking for private messages.',
    'spamtroll_check_registrations' => 'Check Registrations',
    'spamtroll_check_registrations_desc' => 'Enable spam checking during member registration.',

    'spamtroll_action_blocked' => 'Action for Spam',
    'spamtroll_action_blocked_desc' => 'Action to take when spam is detected.',
    'spamtroll_action_suspicious' => 'Action for Suspicious',
    'spamtroll_action_suspicious_desc' => 'Action to take when suspicious content is detected.',

    'spamtroll_action_block' => 'Block',
    'spamtroll_action_moderate' => 'Send to Moderation',
    'spamtroll_action_warn' => 'Warn Only',
    'spamtroll_action_allow' => 'Allow',

    'spamtroll_bypass_groups' => 'Bypass Groups',
    'spamtroll_bypass_groups_desc' => 'Members in these groups will not be checked for spam. Administrators are always bypassed.',

    'spamtroll_log_retention_days' => 'Log Retention',
    'spamtroll_log_retention_days_desc' => 'Number of days to keep spam logs.',

    // Dashboard
    'spamtroll_dashboard_title' => 'Spamtroll Dashboard',
    'spamtroll_dashboard_stats' => 'Statistics (Last 7 Days)',
    'spamtroll_dashboard_recent' => 'Recent Activity',
    'spamtroll_dashboard_api_status' => 'API Status',

    'spamtroll_stat_total' => 'Total Scans',
    'spamtroll_stat_blocked' => 'Blocked',
    'spamtroll_stat_suspicious' => 'Suspicious',
    'spamtroll_stat_safe' => 'Safe',

    'spamtroll_api_online' => 'Online',
    'spamtroll_api_offline' => 'Offline',
    'spamtroll_api_error' => 'Error',
    'spamtroll_api_not_configured' => 'Not Configured',

    'spamtroll_chart_title' => 'Scan Activity',
    'spamtroll_chart_total' => 'Total',
    'spamtroll_chart_blocked' => 'Blocked',

    'spamtroll_not_configured_message' => 'Spamtroll is not configured. Please enter your API key in the settings.',
    'spamtroll_disabled_message' => 'Spamtroll is currently disabled. Enable it in the settings to start protecting your community.',
    'spamtroll_go_to_settings' => 'Go to Settings',

    // Logs
    'spamtroll_logs_title' => 'Spam Logs',
    'spamtroll_log_id' => 'ID',
    'spamtroll_log_member_id' => 'Member',
    'spamtroll_log_content_type' => 'Type',
    'spamtroll_log_status' => 'Status',
    'spamtroll_log_spam_score' => 'Score',
    'spamtroll_log_action_taken' => 'Action',
    'spamtroll_log_ip_address' => 'IP Address',
    'spamtroll_log_date' => 'Date',
    'spamtroll_log_content_preview' => 'Content Preview',
    'spamtroll_log_symbols' => 'Detection Symbols',
    'spamtroll_log_threat_categories' => 'Threat Categories',
    'spamtroll_log_details' => 'Log Details',

    'spamtroll_filter_all' => 'All Logs',
    'spamtroll_filter_blocked' => 'Blocked Only',
    'spamtroll_filter_suspicious' => 'Suspicious Only',
    'spamtroll_filter_safe' => 'Safe Only',
    'spamtroll_filter_posts' => 'Posts Only',
    'spamtroll_filter_messages' => 'Messages Only',
    'spamtroll_filter_registrations' => 'Registrations Only',

    'spamtroll_status_blocked' => 'Blocked',
    'spamtroll_status_suspicious' => 'Suspicious',
    'spamtroll_status_safe' => 'Safe',

    'spamtroll_content_type_post' => 'Forum Post',
    'spamtroll_content_type_message' => 'Private Message',
    'spamtroll_content_type_registration' => 'Registration',

    'spamtroll_view_details' => 'View Details',
    'spamtroll_delete_log_confirm' => 'Are you sure you want to delete this log entry?',
    'spamtroll_log_not_found' => 'Log entry not found.',
    'spamtroll_logs_cleared' => 'All logs have been cleared.',
    'spamtroll_clear_all_logs' => 'Clear All Logs',
    'spamtroll_export_logs' => 'Export Logs',

    'spamtroll_guest' => 'Guest',
    'spamtroll_deleted_member' => 'Deleted Member',

    // Connection Test
    'spamtroll_test_connection' => 'Test Connection',
    'spamtroll_testing' => 'Testing...',
    'spamtroll_connection_success' => 'Connection successful! API is working correctly.',
    'spamtroll_connection_failed' => 'Connection failed. Please check your API key and URL.',

    // Misc
    'spamtroll_no_data' => 'No data available',
    'spamtroll_refresh' => 'Refresh',
    'spamtroll_loading' => 'Loading...',
);
