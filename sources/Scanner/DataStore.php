<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll state store backed by \IPS\Data\Store
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
 * \IPS\Data\Store behind the StateStore interface.
 *
 * Both methods swallow everything: a datastore backend that is down must not
 * be able to stop a post from being made. A read that fails reads as "no
 * backoff recorded", which is the permissive answer.
 */
class _DataStore implements StateStore
{
    public function getInt(string $key, int $default = 0): int
    {
        try {
            $store = \IPS\Data\Store::i();
            if (!isset($store->{$key})) {
                return $default;
            }
            $value = $store->{$key};

            return is_numeric($value) ? (int) $value : $default;
        } catch (\Throwable $t) {
            return $default;
        }
    }

    public function setInt(string $key, int $value): void
    {
        try {
            \IPS\Data\Store::i()->{$key} = $value;
        } catch (\Throwable $t) {
            /* Losing breaker state is a performance problem, never a
             * correctness one. Nothing to do but carry on. */
        }
    }
}
