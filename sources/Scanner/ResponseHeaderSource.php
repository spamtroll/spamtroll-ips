<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll response-header accessor
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
 * Lets the caller see the headers of the last HTTP response.
 *
 * `Spamtroll\Sdk\Http\HttpResponse` carries them, but `Client::dispatch()`
 * drops them on the way to `CheckSpamResponse`, so `Retry-After` and the
 * `X-RateLimit-*` family never reach a plugin. Until the SDK passes them
 * through, the HTTP adapter keeps the last set and the scanner reads it from
 * here.
 *
 * @deprecated Remove once the SDK exposes response headers on its responses.
 */
interface ResponseHeaderSource
{
    /**
     * @return array<string, string> Lowercased header name => value
     */
    public function lastHeaders(): array;
}
