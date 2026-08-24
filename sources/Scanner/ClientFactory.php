<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll SDK client factory
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

use IPS\spamtroll\Api\IpsHttpClient;
use Spamtroll\Sdk\Client;
use Spamtroll\Sdk\ClientConfig;
use Spamtroll\Sdk\Version;

/**
 * Builds the SDK client, with a latency budget that depends on who is waiting.
 *
 * The application used to pass nothing but a user agent, so every scan
 * inherited the SDK defaults: a 5 second timeout, three attempts, half a
 * second of backoff between them. With the API unreachable that is roughly
 * 16 seconds of a member staring at a spinner after clicking Post — and three
 * units of their daily quota spent on a scan that produced no verdict.
 *
 * So there are two profiles. On the path a person is waiting on, one attempt
 * and no backoff. On the paths nobody is watching — the AdminCP's connection
 * test, the cleanup task — retries are worth having.
 *
 * Arguments are passed by name on purpose: ClientConfig takes
 * (baseUrl, timeout, maxRetries, retryBaseDelayMs, userAgent, scoreDenominator)
 * positionally, and a future reordering would otherwise quietly turn the
 * timeout into the retry count.
 */
final class _ClientFactory
{
    /** One attempt. A member is waiting, and a retry costs quota either way. */
    public const INTERACTIVE_MAX_RETRIES = 1;

    /** Upper bound on the configured timeout, whatever the setting says. */
    public const MAX_TIMEOUT = 10;

    public const MANAGEMENT_TIMEOUT = 10;
    public const MANAGEMENT_MAX_RETRIES = 3;
    public const MANAGEMENT_RETRY_DELAY_MS = 500;

    /**
     * The scanner used from the hooks.
     */
    public static function interactiveScanner(): Scanner
    {
        $http = new IpsHttpClient();

        return new Scanner(
            new Client(self::apiKey(), self::interactiveConfig(), $http),
            new Breaker(),
            null,
            null,
            $http,
        );
    }

    /**
     * A client for the AdminCP and background work: slower, but allowed to
     * try again.
     */
    public static function managementClient(?string $apiKey = null): Client
    {
        return new Client($apiKey ?? self::apiKey(), self::managementConfig(), new IpsHttpClient());
    }

    public static function interactiveConfig(): ClientConfig
    {
        return new ClientConfig(
            baseUrl: self::baseUrl(),
            timeout: self::timeout(),
            maxRetries: self::INTERACTIVE_MAX_RETRIES,
            retryBaseDelayMs: 0,
            userAgent: self::userAgent(),
        );
    }

    public static function managementConfig(): ClientConfig
    {
        return new ClientConfig(
            baseUrl: self::baseUrl(),
            timeout: self::MANAGEMENT_TIMEOUT,
            maxRetries: self::MANAGEMENT_MAX_RETRIES,
            retryBaseDelayMs: self::MANAGEMENT_RETRY_DELAY_MS,
            userAgent: self::userAgent(),
        );
    }

    /**
     * `spamtroll_api_url` has been written by the installer since 1.0.0 and
     * read by nothing, so a forum pointed at a staging backend silently
     * talked to production.
     */
    public static function baseUrl(): string
    {
        $configured = trim((string) \IPS\Settings::i()->spamtroll_api_url);

        return $configured !== '' ? $configured : ClientConfig::DEFAULT_BASE_URL;
    }

    public static function timeout(): int
    {
        return max(1, min(self::MAX_TIMEOUT, (int) \IPS\Settings::i()->spamtroll_timeout));
    }

    public static function userAgent(): string
    {
        return 'Spamtroll-IPS/' . \IPS\spamtroll\Application::VERSION
            . ' spamtroll-php-sdk/' . Version::VERSION;
    }

    private static function apiKey(): string
    {
        return (string) \IPS\Settings::i()->spamtroll_api_key;
    }
}
