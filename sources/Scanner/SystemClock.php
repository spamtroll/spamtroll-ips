<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll system clock
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

class _SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
