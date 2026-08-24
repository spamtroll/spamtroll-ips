<?php

declare(strict_types=1);

/**
 * Registers `IPS\spamtroll\…\_Foo` → `IPS\spamtroll\…\Foo` for every class
 * this application ships.
 *
 * IPS declares classes with a leading underscore and its autoloader resolves
 * the unprefixed name at runtime (see docs/SUITE-FACTS.md, U12b). Neither
 * PHPStan nor Pest runs that autoloader, so without this file every
 * `\IPS\spamtroll\Scanner\Gateway` reads as an unknown class.
 *
 * Load order matters: `stubs/IPS.stub.php` must already be in memory, because
 * the application classes extend framework classes declared there.
 */

require_once __DIR__ . '/IPS.stub.php';

$spamtrollRoot = \dirname(__DIR__);

$spamtrollFiles = array_merge(
    [$spamtrollRoot . '/Application.php'],
    glob($spamtrollRoot . '/sources/*/*.php') ?: [],
    glob($spamtrollRoot . '/extensions/*/*/*.php') ?: [],
);

foreach ($spamtrollFiles as $spamtrollFile) {
    require_once $spamtrollFile;
}

foreach (get_declared_classes() as $spamtrollClass) {
    if (strpos($spamtrollClass, 'IPS\\spamtroll\\') !== 0) {
        continue;
    }

    $spamtrollSeparator = strrpos($spamtrollClass, '\\');
    if ($spamtrollSeparator === false) {
        continue;
    }

    $spamtrollShort = substr($spamtrollClass, $spamtrollSeparator + 1);
    if ($spamtrollShort === '' || $spamtrollShort[0] !== '_') {
        continue;
    }

    $spamtrollAlias = substr($spamtrollClass, 0, $spamtrollSeparator + 1) . substr($spamtrollShort, 1);
    if (!class_exists($spamtrollAlias, false)) {
        class_alias($spamtrollClass, $spamtrollAlias);
    }
}
