<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll API error classification
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

use Spamtroll\Sdk\Response\Response;

/**
 * Reads an error body without trusting `$response->error`.
 *
 * The API answers in four shapes, and the SDK's own extractor collapses two
 * of them into nonsense: it tests `isset($decoded['error'])` before
 * `['message']`, so a rate-limit body `{"error":true,"message":"…"}` yields
 * the string `"1"`. That is what the AdminCP log has been showing —
 * `Spamtroll API error: 1` — for every 429 and every routing mistake.
 *
 *   A  envelope        {"success":false,"error":{"code":…,"message":…}}
 *   B  quota (402)     as A, plus error.usage
 *   C  rate limiter    {"error":true,"message":"Rate limit exceeded. …"}
 *   D  Fiber handler   {"error":true,"message":"Cannot POST /api/v1/scan/chek"}
 *
 * @deprecated Once the SDK ships a fixed `Client::extractError()` (>= 0.10),
 *             delete this file and read `$response->error` directly.
 */
final class _ApiError
{
    public const KIND_ENVELOPE = 'envelope';
    public const KIND_FLAT = 'flat';
    public const KIND_OPAQUE = 'opaque';

    /**
     * @param array<string, string> $headers Lowercased response headers
     *
     * @return array{kind: string, code: string, message: string, retryAfter: int|null, usage: array<string, mixed>}
     */
    public static function classify(Response $response, array $headers = []): array
    {
        $data = $response->data;
        $retryAfter = self::retryAfter($headers);

        /* A and B: `error` is an object. */
        if (isset($data['error']) && \is_array($data['error'])) {
            $error = $data['error'];

            return [
                'kind' => self::KIND_ENVELOPE,
                'code' => isset($error['code']) && \is_scalar($error['code']) ? (string) $error['code'] : '',
                'message' => isset($error['message']) && \is_scalar($error['message'])
                    ? (string) $error['message']
                    : 'HTTP ' . $response->httpCode,
                'retryAfter' => $retryAfter,
                'usage' => isset($error['usage']) && \is_array($error['usage']) ? $error['usage'] : [],
            ];
        }

        /* C and D: `error` is the boolean true and the text sits beside it. */
        if (isset($data['message']) && \is_scalar($data['message'])) {
            return [
                'kind' => self::KIND_FLAT,
                'code' => self::codeForStatus($response->httpCode),
                'message' => (string) $data['message'],
                'retryAfter' => $retryAfter,
                'usage' => [],
            ];
        }

        /* Empty body, HTML error page, or JSON we cannot read. */
        return [
            'kind' => self::KIND_OPAQUE,
            'code' => self::codeForStatus($response->httpCode),
            'message' => 'HTTP ' . $response->httpCode,
            'retryAfter' => $retryAfter,
            'usage' => [],
        ];
    }

    /**
     * Compact one-line form for the IPS log. Carries the code so a support
     * request can be matched to a backend request without pasting a body.
     *
     * @param array{kind: string, code: string, message: string, retryAfter: int|null, usage: array<string, mixed>} $error
     */
    public static function describe(array $error, int $httpCode): string
    {
        $code = $error['code'] !== '' ? $error['code'] : 'HTTP_' . $httpCode;

        return sprintf('Spamtroll API error [%d %s]: %s', $httpCode, $code, $error['message']);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function retryAfter(array $headers): ?int
    {
        if (!isset($headers['retry-after'])) {
            return null;
        }

        $value = trim($headers['retry-after']);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function codeForStatus(int $httpCode): string
    {
        switch ($httpCode) {
            case 402:
                return 'QUOTA_EXCEEDED';
            case 403:
                return 'FORBIDDEN';
            case 404:
                return 'NOT_FOUND';
            case 429:
                return 'RATE_LIMITED';
            default:
                return $httpCode >= 500 ? 'SERVER_ERROR' : 'HTTP_' . $httpCode;
        }
    }
}
