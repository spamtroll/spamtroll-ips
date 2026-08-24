<?php

declare(strict_types=1);

/**
 * A stand-in for the part of the IPS autoloader this application relies on.
 *
 * IPS declares every class with a leading underscore and resolves the
 * unprefixed name at runtime: `\IPS\spamtroll\Scanner\Gateway` is loaded from
 * `sources/Scanner/Gateway.php`, which declares `_Gateway`
 * (docs/SUITE-FACTS.md, U12b). Neither PHPStan nor Pest runs that autoloader,
 * so without this file every reference to a shipped class reads as unknown.
 *
 * The path rule mirrors the framework's: a segment starting with a lowercase
 * letter is a directory as written (`modules`, `extensions`, `tasks`,
 * `widgets`), anything else lives under `sources/` — except a single segment,
 * which is the application class itself.
 *
 * Loaded through Composer's dev autoloader, so it is never shipped.
 */

require_once __DIR__ . '/IPS.stub.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'IPS\\spamtroll\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $segments = explode('\\', substr($class, \strlen($prefix)));
    $short = array_pop($segments);
    if ($short === null) {
        return;
    }

    /* `_Gateway` and `Gateway` come from the same file. */
    $declared = $short[0] === '_' ? $short : '_' . $short;
    $wanted = ltrim($short, '_');

    if ($segments === []) {
        $path = \dirname(__DIR__) . '/' . $wanted . '.php';
    } else {
        $first = $segments[0];
        $root = preg_match('/^[a-z0-9]/', $first) === 1 ? '' : 'sources/';
        $path = \dirname(__DIR__) . '/' . $root . implode('/', $segments) . '/' . $wanted . '.php';
    }

    if (!is_file($path)) {
        return;
    }

    require_once $path;

    $namespace = $prefix . ($segments === [] ? '' : implode('\\', $segments) . '\\');

    /* Interfaces and traits are declared unprefixed and need no alias. */
    if (interface_exists($namespace . $wanted, false) || trait_exists($namespace . $wanted, false)) {
        return;
    }

    if (class_exists($namespace . $declared, false) && !class_exists($namespace . $wanted, false)) {
        class_alias($namespace . $declared, $namespace . $wanted);
    }
});
