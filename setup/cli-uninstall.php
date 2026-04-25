<?php

declare(strict_types=1);
/**
 * Spamtroll Anti-Spam — CLI uninstaller for IPS Community Suite.
 *
 * Removes every trace of the application from the database. Does NOT remove
 * the application directory on disk — delete applications/spamtroll/ manually
 * after this script finishes.
 *
 * Usage (from the IPS root directory, where init.php lives):
 *   php applications/spamtroll/setup/cli-uninstall.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

if (! file_exists('init.php')) {
    fwrite(STDERR, "Run from the IPS root directory (where init.php lives).\n");
    exit(1);
}

require 'init.php';

$appDir = 'spamtroll';

\IPS\Db::i()->dropTable('spamtroll_logs', true);
echo "[-] table spamtroll_logs dropped\n";

foreach ([
    'core_sys_conf_settings' => [ 'conf_app=?', $appDir ],
    'core_modules' => [ 'sys_module_application=?', $appDir ],
    'core_hooks' => [ 'app=?', $appDir ],
    'core_tasks' => [ 'app=?', $appDir ],
    'core_widgets' => [ 'app=?', $appDir ],
    'core_sys_lang_words' => [ 'word_app=?', $appDir ],
    'core_theme_templates' => [ 'template_app=?', $appDir ],
    'core_applications' => [ 'app_directory=?', $appDir ],
] as $table => $where) {
    $affected = \IPS\Db::i()->delete($table, $where);
    echo "[-] $table: $affected row(s) deleted\n";
}

\IPS\Data\Store::i()->clearAll();
\IPS\Data\Cache::i()->clearAll();
foreach (glob(\IPS\ROOT_PATH . '/datastore/*.php') as $f) {
    @unlink($f);
}
echo "[-] caches cleared\n";

echo "\n=== UNINSTALL COMPLETE ===\n";
echo "Now remove the folder manually: rm -rf applications/$appDir\n";
