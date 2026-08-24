<?php

declare(strict_types=1);

/*
 * Tests bootstrap.
 *
 * The IPS framework is closed-source and can't be loaded standalone, so
 * `stubs/IPS.stub.php` declares just enough of its surface for the
 * application code to run, and `stubs/aliases.php` registers the
 * `_Foo` -> `Foo` aliases the IPS autoloader would create at runtime
 * (docs/SUITE-FACTS.md, U12b). Both are pulled in by Composer's dev
 * autoloader, so requiring the autoloader is all this file has to do.
 *
 * What that buys the suite:
 *  - `tests/Unit`     — pure logic, no IPS at all;
 *  - `tests/Scanner`  — the real SDK over a fake HttpClientInterface;
 *  - `tests/Hooks`    — the hook files, transformed exactly as the Suite
 *                       transforms them, over an instrumented fake parent.
 * Only the live-forum checks in docs/SMOKE.md stay outside CI.
 */

require __DIR__ . '/../vendor/autoload.php';
