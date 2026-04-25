<?php

// German translations use the informal "du" form throughout, matching
// dogomania-style community conventions.

$lang = array(
    // Main
    '__app_spamtroll' => 'Spamtroll Anti-Spam',
    'module__spamtroll_spamtroll' => 'Spamtroll',

    // Menu
    'menu__spamtroll_spamtroll' => 'Spamtroll Anti-Spam',
    'menu__spamtroll_spamtroll_dashboard' => 'Übersicht',
    'menu__spamtroll_spamtroll_settings' => 'Einstellungen',
    'menu__spamtroll_spamtroll_logs' => 'Protokoll',

    // Permissions
    'r__spamtroll' => 'Spamtroll Anti-Spam',
    'r__spamtroll_dashboard' => 'Darf die Übersicht ansehen?',
    'r__spamtroll_settings' => 'Darf Einstellungen verwalten?',
    'r__spamtroll_logs' => 'Darf das Protokoll ansehen?',

    // Settings Tabs
    'spamtroll_tab_api' => 'API-Konfiguration',
    'spamtroll_tab_detection' => 'Erkennungseinstellungen',
    'spamtroll_tab_actions' => 'Aktionen',
    'spamtroll_tab_bypass' => 'Umgehung',
    'spamtroll_tab_maintenance' => 'Wartung',

    // Settings Headers
    'spamtroll_header_api_config' => 'API-Konfiguration',
    'spamtroll_header_thresholds' => 'Erkennung',
    'spamtroll_header_content_types' => 'Was scannen',
    'spamtroll_header_actions' => 'Spam-Aktionen',
    'spamtroll_header_bypass' => 'Umgehung',
    'spamtroll_header_logs' => 'Protokoll-Einstellungen',

    // Settings Fields
    'spamtroll_enabled' => 'Spamtroll aktivieren',
    'spamtroll_enabled_desc' => 'Aktiviere oder deaktiviere die Spam-Prüfung global.',
    'spamtroll_api_key' => 'API-Schlüssel',
    'spamtroll_api_key_desc' => 'Trage deinen Spamtroll-API-Schlüssel ein. Hol ihn dir auf https://spamtroll.io',
    'spamtroll_api_url' => 'API-URL',
    'spamtroll_api_url_desc' => 'Basis-URL der Spamtroll-API.',
    'spamtroll_timeout' => 'API-Zeitlimit',
    'spamtroll_timeout_desc' => 'Zeitlimit für API-Anfragen.',
    'spamtroll_seconds' => 'Sekunden',
    'spamtroll_days' => 'Tage',
    'spamtroll_posts_unit' => 'Beiträge',

    'spamtroll_spam_threshold' => 'Spam-Schwelle',
    'spamtroll_spam_threshold_desc' => 'Werte über dieser Schwelle gelten als Spam (0,0 – 1,0).',
    'spamtroll_suspicious_threshold' => 'Verdächtig-Schwelle',
    'spamtroll_suspicious_threshold_desc' => 'Werte über dieser Schwelle gelten als verdächtig (0,0 – 1,0).',

    'spamtroll_check_posts' => 'Forenbeiträge prüfen',
    'spamtroll_check_posts_desc' => 'Spam-Prüfung für Forenbeiträge aktivieren.',
    'spamtroll_check_registrations' => 'Registrierungen prüfen',
    'spamtroll_check_registrations_desc' => 'Spam-Prüfung bei Benutzerregistrierungen aktivieren.',

    'spamtroll_action_blocked' => 'Aktion bei Spam',
    'spamtroll_action_blocked_desc' => 'Was bei erkanntem Spam passieren soll.',
    'spamtroll_action_suspicious' => 'Aktion bei Verdacht',
    'spamtroll_action_suspicious_desc' => 'Was bei verdächtigen Inhalten passieren soll.',

    'spamtroll_action_block' => 'Blockieren',
    'spamtroll_action_moderate' => 'Zur Moderation senden',
    'spamtroll_action_warn' => 'Nur warnen',
    'spamtroll_action_allow' => 'Zulassen',

    'spamtroll_bypass_groups' => 'Umgehungsgruppen',
    'spamtroll_bypass_groups_desc' => 'Mitglieder dieser Gruppen werden nicht auf Spam geprüft. Administratoren werden immer übersprungen.',

    'spamtroll_bypass_min_posts' => 'Benutzer mit mehr als X Beiträgen überspringen',
    'spamtroll_bypass_min_posts_desc' => 'Mitglieder mit mehr als dieser Anzahl an Forenbeiträgen werden nicht gescannt. Setze auf 0, um diese Schwelle zu deaktivieren.',

    'spamtroll_log_retention_days' => 'Protokoll-Aufbewahrung',
    'spamtroll_log_retention_days_desc' => 'Anzahl der Tage, die Spam-Protokolle aufbewahrt werden.',

    // Simplified settings: sensitivity preset + scan scope
    'spamtroll_sensitivity'          => 'Empfindlichkeit',
    'spamtroll_sensitivity_desc'     => 'Wie aggressiv Inhalte markiert werden. „Ausgewogen" ist für die meisten Foren empfehlenswert.',
    'spamtroll_sensitivity_lenient'  => 'Mild (weniger falsche Treffer)',
    'spamtroll_sensitivity_balanced' => 'Ausgewogen (empfohlen)',
    'spamtroll_sensitivity_strict'   => 'Streng (erfasst mehr Spam)',

    'spamtroll_scan_scope'           => 'Was scannen',
    'spamtroll_scan_scope_desc'      => 'Welche Inhaltstypen Spamtroll prüfen soll. Private Nachrichten werden nie gescannt.',
    'spamtroll_scope_all'            => 'Beiträge und neue Registrierungen',
    'spamtroll_scope_posts_only'     => 'Nur Forenbeiträge',
    'spamtroll_scope_off'            => 'Aus (installiert, aber nicht scannen)',

    // Dashboard
    'spamtroll_dashboard_title' => 'Spamtroll-Übersicht',
    'spamtroll_dashboard_stats' => 'Statistik (letzte 7 Tage)',
    'spamtroll_dashboard_recent' => 'Letzte Aktivität',
    'spamtroll_dashboard_api_status' => 'API-Status',

    'spamtroll_stat_total' => 'Scans gesamt',
    'spamtroll_stat_blocked' => 'Blockiert',
    'spamtroll_stat_suspicious' => 'Verdächtig',
    'spamtroll_stat_safe' => 'Unbedenklich',

    'spamtroll_api_online' => 'Online',
    'spamtroll_api_offline' => 'Offline',
    'spamtroll_api_error' => 'Fehler',
    'spamtroll_api_not_configured' => 'Nicht konfiguriert',

    'spamtroll_chart_title' => 'Scan-Aktivität',
    'spamtroll_chart_total' => 'Gesamt',
    'spamtroll_chart_blocked' => 'Blockiert',

    'spamtroll_not_configured_message' => 'Spamtroll ist nicht konfiguriert. Trage deinen API-Schlüssel in den Einstellungen ein.',
    'spamtroll_disabled_message' => 'Spamtroll ist aktuell deaktiviert. Aktiviere ihn in den Einstellungen, um deine Community zu schützen.',
    'spamtroll_go_to_settings' => 'Zu den Einstellungen',

    // Logs
    'spamtroll_logs_title' => 'Spam-Protokoll',
    'spamtroll_log_id' => 'ID',
    'spamtroll_log_member_id' => 'Mitglied',
    'spamtroll_log_content_type' => 'Typ',
    'spamtroll_log_status' => 'Status',
    'spamtroll_log_spam_score' => 'Wert',
    'spamtroll_log_action_taken' => 'Aktion',
    'spamtroll_log_ip_address' => 'IP-Adresse',
    'spamtroll_log_date' => 'Datum',
    'spamtroll_log_submission_id' => 'Scan-UUID',
    'spamtroll_copied' => 'Kopiert',
    'spamtroll_log_content_preview' => 'Inhaltsvorschau',
    'spamtroll_log_symbols' => 'Erkennungssymbole',
    'spamtroll_log_threat_categories' => 'Bedrohungskategorien',
    'spamtroll_log_details' => 'Eintrag-Details',

    'spamtroll_filter_all' => 'Alle Einträge',
    'spamtroll_filter_blocked' => 'Nur blockierte',
    'spamtroll_filter_suspicious' => 'Nur verdächtige',
    'spamtroll_filter_safe' => 'Nur unbedenkliche',
    'spamtroll_filter_posts' => 'Nur Beiträge',
    'spamtroll_filter_registrations' => 'Nur Registrierungen',

    'spamtroll_status_blocked' => 'Blockiert',
    'spamtroll_status_suspicious' => 'Verdächtig',
    'spamtroll_status_safe' => 'Unbedenklich',

    'spamtroll_content_type_post' => 'Forenbeitrag',
    'spamtroll_content_type_message' => 'Private Nachricht',
    'spamtroll_content_type_registration' => 'Registrierung',

    'spamtroll_view_details' => 'Details ansehen',
    'spamtroll_delete_log_confirm' => 'Möchtest du diesen Eintrag wirklich löschen?',
    'spamtroll_log_not_found' => 'Eintrag nicht gefunden.',
    'spamtroll_logs_cleared' => 'Alle Einträge wurden gelöscht.',
    'spamtroll_clear_all_logs' => 'Alle Einträge löschen',
    'spamtroll_export_logs' => 'Protokoll exportieren',

    'spamtroll_guest' => 'Gast',
    'spamtroll_deleted_member' => 'Gelöschtes Mitglied',

    // Connection Test
    'spamtroll_test_connection' => 'Verbindung testen',
    'spamtroll_testing' => 'Teste...',
    'spamtroll_connection_success' => 'Verbindung erfolgreich! Die API funktioniert.',
    'spamtroll_connection_failed' => 'Verbindung fehlgeschlagen. Prüfe deinen API-Schlüssel und die URL.',

    // Widget
    'block_spamtrollStats' => 'Spamtroll-Statistik',
    'block_spamtrollStats_desc' => 'Zeigt die letzten Spam-Erkennungsstatistiken.',

    // Misc
    'spamtroll_no_data' => 'Keine Daten verfügbar',
    'spamtroll_refresh' => 'Aktualisieren',
    'spamtroll_loading' => 'Lade...',
);
