<?php

$lang = array(
    // Main
    '__app_spamtroll' => 'Spamtroll Antyspam',
    'module__spamtroll_spamtroll' => 'Spamtroll',

    // Menu
    'menu__spamtroll_spamtroll' => 'Spamtroll Antyspam',
    'menu__spamtroll_spamtroll_dashboard' => 'Panel',
    'menu__spamtroll_spamtroll_settings' => 'Ustawienia',
    'menu__spamtroll_spamtroll_logs' => 'Dziennik',

    // Permissions
    'r__spamtroll' => 'Spamtroll Antyspam',
    'r__spamtroll_dashboard' => 'Może wyświetlać panel?',
    'r__spamtroll_settings' => 'Może zarządzać ustawieniami?',
    'r__spamtroll_logs' => 'Może wyświetlać dziennik?',

    // Settings Tabs
    'spamtroll_tab_api' => 'Konfiguracja API',
    'spamtroll_tab_detection' => 'Ustawienia wykrywania',
    'spamtroll_tab_actions' => 'Akcje',
    'spamtroll_tab_bypass' => 'Pomijanie',
    'spamtroll_tab_maintenance' => 'Konserwacja',

    // Settings Headers
    'spamtroll_header_api_config' => 'Konfiguracja API',
    'spamtroll_header_thresholds' => 'Wykrywanie',
    'spamtroll_header_content_types' => 'Co skanować',
    'spamtroll_header_actions' => 'Akcje przy spamie',
    'spamtroll_header_bypass' => 'Pomijanie',
    'spamtroll_header_logs' => 'Ustawienia dziennika',

    // Settings Fields
    'spamtroll_enabled' => 'Włącz Spamtroll',
    'spamtroll_enabled_desc' => 'Globalnie włącz lub wyłącz sprawdzanie spamu.',
    'spamtroll_api_key' => 'Klucz API',
    'spamtroll_api_key_desc' => 'Wpisz swój klucz API Spamtroll. Pobierz go ze strony https://spamtroll.io',
    'spamtroll_api_url' => 'URL API',
    'spamtroll_api_url_desc' => 'Bazowy adres URL API Spamtroll.',
    'spamtroll_timeout' => 'Limit czasu API',
    'spamtroll_timeout_desc' => 'Limit czasu żądań API.',
    'spamtroll_seconds' => 'sekund',
    'spamtroll_days' => 'dni',
    'spamtroll_posts_unit' => 'wiadomości',

    'spamtroll_spam_threshold' => 'Próg spamu',
    'spamtroll_spam_threshold_desc' => 'Wynik powyżej tej wartości zostanie potraktowany jako spam (0,0 – 1,0).',
    'spamtroll_suspicious_threshold' => 'Próg podejrzanych',
    'spamtroll_suspicious_threshold_desc' => 'Wynik powyżej tej wartości zostanie potraktowany jako podejrzany (0,0 – 1,0).',

    'spamtroll_check_posts' => 'Sprawdzaj posty na forum',
    'spamtroll_check_posts_desc' => 'Włącz sprawdzanie spamu w postach forum.',
    'spamtroll_check_registrations' => 'Sprawdzaj rejestracje',
    'spamtroll_check_registrations_desc' => 'Włącz sprawdzanie spamu podczas rejestracji użytkowników.',

    'spamtroll_action_blocked' => 'Akcja dla spamu',
    'spamtroll_action_blocked_desc' => 'Akcja podejmowana po wykryciu spamu.',
    'spamtroll_action_suspicious' => 'Akcja dla podejrzanych',
    'spamtroll_action_suspicious_desc' => 'Akcja podejmowana po wykryciu podejrzanej treści.',

    'spamtroll_action_block' => 'Zablokuj',
    'spamtroll_action_moderate' => 'Wyślij do moderacji',
    'spamtroll_action_warn' => 'Tylko ostrzeżenie',
    'spamtroll_action_allow' => 'Dopuść',

    'spamtroll_bypass_groups' => 'Pomijane grupy',
    'spamtroll_bypass_groups_desc' => 'Użytkownicy z tych grup nie będą sprawdzani pod kątem spamu. Administratorzy są zawsze pomijani.',

    'spamtroll_bypass_min_posts' => 'Pomijaj użytkowników z więcej niż X wiadomościami',
    'spamtroll_bypass_min_posts_desc' => 'Użytkownicy, którzy napisali więcej niż tę liczbę wiadomości na forum, nie będą skanowani. Wpisz 0, aby wyłączyć ten próg.',

    'spamtroll_log_retention_days' => 'Przechowywanie dziennika',
    'spamtroll_log_retention_days_desc' => 'Liczba dni przechowywania wpisów dziennika spamu.',

    // Simplified settings: sensitivity preset + scan scope
    'spamtroll_sensitivity'          => 'Czułość',
    'spamtroll_sensitivity_desc'     => 'Jak agresywnie oznaczać treści. „Zrównoważona" jest zalecana dla większości forów.',
    'spamtroll_sensitivity_lenient'  => 'Łagodna (mniej fałszywych alarmów)',
    'spamtroll_sensitivity_balanced' => 'Zrównoważona (zalecana)',
    'spamtroll_sensitivity_strict'   => 'Surowa (wykrywa więcej spamu)',

    'spamtroll_scan_scope'           => 'Co skanować',
    'spamtroll_scan_scope_desc'      => 'Jakie typy treści ma sprawdzać Spamtroll. Prywatne wiadomości nigdy nie są skanowane.',
    'spamtroll_scope_all'            => 'Posty i nowe rejestracje',
    'spamtroll_scope_posts_only'     => 'Tylko posty na forum',
    'spamtroll_scope_off'            => 'Wyłączone (zachowaj instalację, ale nie skanuj)',

    // Dashboard
    'spamtroll_dashboard_title' => 'Panel Spamtroll',
    'spamtroll_dashboard_stats' => 'Statystyki (ostatnie 7 dni)',
    'spamtroll_dashboard_recent' => 'Ostatnia aktywność',
    'spamtroll_dashboard_api_status' => 'Status API',

    'spamtroll_stat_total' => 'Łącznie skanów',
    'spamtroll_stat_blocked' => 'Zablokowane',
    'spamtroll_stat_suspicious' => 'Podejrzane',
    'spamtroll_stat_safe' => 'Bezpieczne',

    'spamtroll_api_online' => 'Online',
    'spamtroll_api_offline' => 'Offline',
    'spamtroll_api_error' => 'Błąd',
    'spamtroll_api_not_configured' => 'Nie skonfigurowano',

    'spamtroll_chart_title' => 'Aktywność skanowania',
    'spamtroll_chart_total' => 'Łącznie',
    'spamtroll_chart_blocked' => 'Zablokowane',

    'spamtroll_not_configured_message' => 'Spamtroll nie jest skonfigurowany. Wpisz klucz API w ustawieniach.',
    'spamtroll_disabled_message' => 'Spamtroll jest aktualnie wyłączony. Włącz go w ustawieniach, aby zacząć chronić swoją społeczność.',
    'spamtroll_go_to_settings' => 'Przejdź do ustawień',

    // Logs
    'spamtroll_logs_title' => 'Dziennik spamu',
    'spamtroll_log_id' => 'ID',
    'spamtroll_log_member_id' => 'Użytkownik',
    'spamtroll_log_content_type' => 'Typ',
    'spamtroll_log_status' => 'Status',
    'spamtroll_log_spam_score' => 'Wynik',
    'spamtroll_log_action_taken' => 'Akcja',
    'spamtroll_log_ip_address' => 'Adres IP',
    'spamtroll_log_date' => 'Data',
    'spamtroll_log_submission_id' => 'UUID skanu',
    'spamtroll_copied' => 'Skopiowano',
    'spamtroll_log_content_preview' => 'Podgląd treści',
    'spamtroll_log_symbols' => 'Symbole detekcji',
    'spamtroll_log_threat_categories' => 'Kategorie zagrożeń',
    'spamtroll_log_details' => 'Szczegóły wpisu',

    'spamtroll_filter_all' => 'Wszystkie',
    'spamtroll_filter_blocked' => 'Tylko zablokowane',
    'spamtroll_filter_suspicious' => 'Tylko podejrzane',
    'spamtroll_filter_safe' => 'Tylko bezpieczne',
    'spamtroll_filter_posts' => 'Tylko posty',
    'spamtroll_filter_registrations' => 'Tylko rejestracje',

    'spamtroll_status_blocked' => 'Zablokowane',
    'spamtroll_status_suspicious' => 'Podejrzane',
    'spamtroll_status_safe' => 'Bezpieczne',

    'spamtroll_content_type_post' => 'Post na forum',
    'spamtroll_content_type_message' => 'Wiadomość prywatna',
    'spamtroll_content_type_registration' => 'Rejestracja',

    'spamtroll_view_details' => 'Pokaż szczegóły',
    'spamtroll_delete_log_confirm' => 'Czy na pewno chcesz usunąć ten wpis dziennika?',
    'spamtroll_log_not_found' => 'Nie znaleziono wpisu dziennika.',
    'spamtroll_logs_cleared' => 'Wszystkie wpisy zostały usunięte.',
    'spamtroll_clear_all_logs' => 'Usuń wszystkie wpisy',
    'spamtroll_export_logs' => 'Eksportuj dziennik',

    'spamtroll_guest' => 'Gość',
    'spamtroll_deleted_member' => 'Usunięty użytkownik',

    // Connection Test
    'spamtroll_test_connection' => 'Testuj połączenie',
    'spamtroll_testing' => 'Testowanie...',
    'spamtroll_connection_success' => 'Połączenie udane! API działa poprawnie.',
    'spamtroll_connection_failed' => 'Połączenie nieudane. Sprawdź klucz API oraz adres URL.',

    // Widget
    'block_spamtrollStats' => 'Statystyki Spamtroll',
    'block_spamtrollStats_desc' => 'Pokazuje ostatnie statystyki wykrywania spamu.',

    // Misc
    'spamtroll_no_data' => 'Brak danych',
    'spamtroll_refresh' => 'Odśwież',
    'spamtroll_loading' => 'Ładowanie...',
);
