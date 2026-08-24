<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\ApiError;
use Spamtroll\Sdk\Response\Response;

/**
 * The four error body shapes.
 *
 * The SDK's own extractor checks `isset($decoded['error'])` before
 * `['message']`, so shapes C and D — where `error` is the boolean true —
 * stringify to `"1"`. `Spamtroll API error: 1` is what the AdminCP log has
 * been showing for every rate limit and every routing mistake.
 */

function errorResponse(int $httpCode, array $body): Response
{
    return new Response(false, $httpCode, $body, null);
}

it('reads shape A: the standard error envelope', function (): void {
    $error = ApiError::classify(errorResponse(400, envelopeBody('VALIDATION_ERROR', 'content is required')));

    expect($error['kind'])->toBe(ApiError::KIND_ENVELOPE);
    expect($error['code'])->toBe('VALIDATION_ERROR');
    expect($error['message'])->toBe('content is required');
    expect($error['usage'])->toBe([]);
});

it('reads shape B: the quota envelope, with its usage block', function (): void {
    $error = ApiError::classify(errorResponse(402, envelopeBody('QUOTA_EXCEEDED', 'Daily quota exhausted', [
        'usage' => ['current' => 200, 'limit' => 200, 'plan' => 'free', 'reset_at' => '2026-08-25T00:00:00Z'],
    ])));

    expect($error['code'])->toBe('QUOTA_EXCEEDED');
    expect($error['usage']['limit'])->toBe(200);
    expect($error['usage']['plan'])->toBe('free');
});

it('reads shape C: the HTTP rate limiter', function (): void {
    $error = ApiError::classify(
        errorResponse(429, flatBody('Rate limit exceeded. Maximum 100 requests per minute.')),
        ['retry-after' => '7'],
    );

    expect($error['kind'])->toBe(ApiError::KIND_FLAT);
    expect($error['code'])->toBe('RATE_LIMITED');
    expect($error['message'])->toBe('Rate limit exceeded. Maximum 100 requests per minute.');
    expect($error['message'])->not->toBe('1');
    expect($error['retryAfter'])->toBe(7);
});

it('reads shape D: the router', function (): void {
    $error = ApiError::classify(errorResponse(404, flatBody('Cannot POST /api/v1/scan/chek')));

    expect($error['kind'])->toBe(ApiError::KIND_FLAT);
    expect($error['code'])->toBe('NOT_FOUND');
    expect($error['message'])->toBe('Cannot POST /api/v1/scan/chek');
});

it('says something useful about a body it cannot read at all', function (): void {
    $error = ApiError::classify(errorResponse(502, []));

    expect($error['kind'])->toBe(ApiError::KIND_OPAQUE);
    expect($error['code'])->toBe('SERVER_ERROR');
    expect($error['message'])->toBe('HTTP 502');
});

it('ignores a Retry-After that is not a plain number of seconds', function (string $header): void {
    $error = ApiError::classify(errorResponse(429, flatBody('slow down')), ['retry-after' => $header]);

    expect($error['retryAfter'])->toBeNull();
})->with(['', 'Wed, 21 Oct 2026 07:28:00 GMT', '-5', 'soon']);

it('formats one line for the log', function (): void {
    $error = ApiError::classify(errorResponse(400, envelopeBody('VALIDATION_ERROR', 'content is required')));

    expect(ApiError::describe($error, 400))
        ->toBe('Spamtroll API error [400 VALIDATION_ERROR]: content is required');
});
