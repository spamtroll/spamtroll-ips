<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll SDK HTTP adapter for IPS
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

namespace IPS\spamtroll\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

use IPS\spamtroll\Scanner\ResponseHeaderSource;
use Spamtroll\Sdk\Exception\ConnectionException;
use Spamtroll\Sdk\Exception\TimeoutException;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * HTTP adapter that routes SDK requests through \IPS\Http\Url so IPS-level
 * HTTP settings (proxy, SSL overrides) still apply.
 *
 * Note: IPS resolves the underscore-prefixed runtime class name to
 * \IPS\spamtroll\Api\IpsHttpClient.
 */
class _IpsHttpClient implements HttpClientInterface, ResponseHeaderSource
{
    /**
     * IPS follows up to five redirects by default (docs/SUITE-FACTS.md, U6b).
     * A 30x from a hijacked DNS entry or a misconfigured proxy would therefore
     * replay the request — `X-API-Key` and all — against whatever host the
     * Location header names. Zero switches it off; both transports test the
     * value for truthiness (U6c).
     */
    public const FOLLOW_REDIRECTS = 0;

    /** @var array<string, string> Lowercased headers of the last response. */
    protected array $lastHeaders = [];

    /**
     * @return array<string, string>
     */
    public function lastHeaders(): array
    {
        return $this->lastHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $this->lastHeaders = [];

        try {
            $request = \IPS\Http\Url::external($url)->request($timeout, null, self::FOLLOW_REDIRECTS);
            $request = $request->setHeaders($headers);

            if ($method === 'POST') {
                $ipsResponse = $request->post($body ?? '');
            } else {
                $ipsResponse = $request->get();
            }
        } catch (\Throwable $t) {
            /* Was \IPS\Http\Request\Exception only. That is the exception the
             * framework documents, but not the only one that reaches here: a
             * \TypeError from a changed signature, or a \Error when the
             * package shipped without vendor/, would have escaped the SDK
             * entirely and taken the post with it. The SDK's contract is that
             * this method throws ConnectionException and nothing else. */
            $message = $t->getMessage();

            if (stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false) {
                throw TimeoutException::afterSeconds($timeout);
            }

            throw ConnectionException::fromMessage($message);
        }

        $this->lastHeaders = $this->normaliseHeaders($ipsResponse->httpHeaders);

        return new HttpResponse(
            (int) $ipsResponse->httpResponseCode,
            (string) $ipsResponse,
            $this->lastHeaders,
        );
    }

    /**
     * The Suite records headers with whatever case the server used; the SDK's
     * HttpResponse documents lowercase keys, and Retry-After / X-RateLimit-*
     * are unreadable otherwise.
     *
     * @param mixed $headers
     *
     * @return array<string, string>
     */
    protected function normaliseHeaders($headers): array
    {
        if (!\is_array($headers)) {
            return [];
        }

        $normalised = [];
        foreach ($headers as $name => $value) {
            if (!\is_string($name) || !\is_scalar($value)) {
                continue;
            }
            $normalised[mb_strtolower($name)] = (string) $value;
        }

        return $normalised;
    }
}
