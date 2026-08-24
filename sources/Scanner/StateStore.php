<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll cross-request state store
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Scanner;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * The few integers the circuit breaker has to remember between requests.
 *
 * Deliberately tiny: whether \IPS\Data\Store is shared across php-fpm workers
 * depends on the configured backend and is not something this application can
 * find out (docs/SUITE-FACTS.md, U14). If it is per-worker the breaker
 * degrades to per-worker throttling, which is still a large improvement over
 * none, and nothing else depends on the value.
 */
interface StateStore
{
    public function getInt(string $key, int $default = 0): int;

    public function setInt(string $key, int $value): void;
}
