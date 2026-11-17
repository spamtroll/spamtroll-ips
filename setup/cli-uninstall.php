<?php

declare(strict_types=1);

/** Uninstall through the same native lifecycle as the IPS AdminCP. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from CLI.\n");
    exit(1);
}
if (!file_exists('init.php')) {
    fwrite(STDERR, "Run from the IPS root directory (where init.php lives).\n");
    exit(1);
}

require 'init.php';

try {
    $application = \IPS\Application::load('spamtroll');
    $application->delete();
    echo "Spamtroll uninstalled through the native IPS lifecycle.\n";
    echo "Application metadata, owned resources and hooks have been removed.\n";
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Spamtroll uninstall failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
