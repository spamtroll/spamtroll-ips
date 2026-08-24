<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll clock abstraction
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
 * Wall clock, injected so the circuit breaker's timing can be tested without
 * sleeping. IPS declares interfaces without the underscore prefix.
 */
interface Clock
{
    public function now(): int;
}
