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
class _IpsHttpClient implements HttpClientInterface
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        try {
            $request = \IPS\Http\Url::external($url)->request($timeout);
            $request = $request->setHeaders($headers);

            if ($method === 'POST') {
                $ipsResponse = $request->post($body ?? '');
            } else {
                $ipsResponse = $request->get();
            }
        } catch (\IPS\Http\Request\Exception $e) {
            $message = $e->getMessage();
            if (stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false) {
                throw TimeoutException::afterSeconds($timeout);
            }
            throw ConnectionException::fromMessage($message);
        }

        return new HttpResponse(
            (int) $ipsResponse->httpResponseCode,
            (string) $ipsResponse,
            [],
        );
    }
}
