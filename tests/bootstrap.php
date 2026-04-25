<?php

declare(strict_types=1);

/*
 * Tests bootstrap. The IPS framework is closed-source and can't be
 * standalone-loaded, so unit tests cover only the pure-logic helpers
 * — `Application::determineAction()` and friends — by stubbing
 * `\IPS\Settings::i()` with a tiny in-memory fake. Anything that
 * needs IPS hooks, ACP routing, or the database stays as integration
 * tests on a live forum.
 *
 * Composer autoload pulls in the SDK and the IPS stub file; no IPS
 * core is loaded.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../stubs/IPS.stub.php';

if (! class_exists('IPS\\spamtroll\\Application')) {
    require __DIR__ . '/../Application.php';
    if (! class_exists('IPS\\spamtroll\\Application')) {
        // IPS resolves `_Application` -> `Application` at runtime via its
        // autoloader. For tests we just register the alias manually.
        class_alias('IPS\\spamtroll\\_Application', 'IPS\\spamtroll\\Application');
    }
}
