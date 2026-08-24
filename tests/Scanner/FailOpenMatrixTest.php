<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\Decision;
use IPS\spamtroll\Scanner\Gateway;
use IPS\spamtroll\Tests\Support\ArrayStateStore;
use IPS\spamtroll\Tests\Support\FakeHttpClient;
use IPS\spamtroll\Tests\Support\FixedClock;
use Spamtroll\Sdk\Exception\ConnectionException;
use Spamtroll\Sdk\Exception\TimeoutException;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * The fail-open gate.
 *
 * Every row goes through the real SDK — request building, retry loop, status
 * handling, response parsing — over a fake network, and asserts two things:
 * the action, and that nothing propagated. A scan that cannot produce a
 * verdict must let the content through, whatever the reason.
 *
 * The three verdict rows are here too, because a matrix that only proves
 * "never blocks" would also pass with the scanner deleted.
 */

/** @return array<string, array{0: callable(): FakeHttpClient, 1: string, 2: string}> */
dataset('fail-open matrix', [
    '01 200 safe' => [
        fn () => FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SAFE, 1.0)),
        Decision::ACTION_ALLOW,
        '',
    ],
    '02 200 suspicious' => [
        fn () => FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_SUSPICIOUS, 8.0)),
        Decision::ACTION_MODERATE,
        '',
    ],
    '03 200 blocked' => [
        fn () => FakeHttpClient::json(200, scanBody(CheckSpamResponse::STATUS_BLOCKED, 16.0)),
        Decision::ACTION_BLOCK,
        '',
    ],
    '04 400 validation' => [
        fn () => FakeHttpClient::json(400, envelopeBody('VALIDATION_ERROR', 'content is required')),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '05 401 bad key' => [
        fn () => FakeHttpClient::json(401, envelopeBody('INVALID_API_KEY', 'API key is not valid')),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '06 402 quota' => [
        fn () => FakeHttpClient::json(402, envelopeBody('QUOTA_EXCEEDED', 'Daily quota exhausted', [
            'usage' => ['current' => 200, 'limit' => 200, 'plan' => 'free'],
        ])),
        Decision::ACTION_ALLOW,
        'quota_exceeded',
    ],
    '07 403 forbidden' => [
        fn () => FakeHttpClient::json(403, envelopeBody('FORBIDDEN', 'This key cannot scan that source')),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '08 404 not found' => [
        fn () => FakeHttpClient::json(404, envelopeBody('NOT_FOUND', 'No such resource')),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '09 422 unprocessable' => [
        fn () => FakeHttpClient::json(422, envelopeBody('UNPROCESSABLE', 'Content too long')),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '10 429 flat body' => [
        fn () => FakeHttpClient::json(
            429,
            flatBody('Rate limit exceeded. Maximum 100 requests per minute.'),
            ['retry-after' => '7'],
        ),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '11 429 envelope' => [
        fn () => FakeHttpClient::json(429, envelopeBody('RATE_LIMITED', 'Too many requests'), ['retry-after' => '12']),
        Decision::ACTION_ALLOW,
        'api_error',
    ],
    '12 500 repeated' => [
        fn () => FakeHttpClient::json(500, envelopeBody('SERVER_ERROR', 'upstream unavailable')),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '13 empty body' => [
        fn () => new FakeHttpClient(200, ''),
        Decision::ACTION_ALLOW,
        '',
    ],
    '14 html instead of json' => [
        fn () => new FakeHttpClient(502, '<html><body>502 Bad Gateway</body></html>'),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '15 connection refused' => [
        fn () => FakeHttpClient::throwing(ConnectionException::fromMessage('Connection refused')),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '16 timeout' => [
        fn () => FakeHttpClient::throwing(TimeoutException::afterSeconds(5)),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '17 adapter throws Error' => [
        fn () => FakeHttpClient::throwing(new Error('Class "Spamtroll\\Sdk\\Client" not found')),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
    '18 adapter throws TypeError' => [
        fn () => FakeHttpClient::throwing(new TypeError('Argument #3 must be of type array, null given')),
        Decision::ACTION_ALLOW,
        'transport_error',
    ],
]);

it('never propagates and never blocks without a verdict', function (callable $makeHttp, string $action, string $skipReason): void {
    $http = $makeHttp();
    scannerOver($http);

    $decision = Gateway::scanComment('buy cheap watches', member(), '203.0.113.7');

    expect($decision->action)->toBe($action);
    expect($decision->skipReason)->toBe($skipReason);
})->with('fail-open matrix');

it('logs a readable message rather than the literal 1 for a flat-bodied 429', function (): void {
    scannerOver(FakeHttpClient::json(
        429,
        flatBody('Rate limit exceeded. Maximum 100 requests per minute.'),
        ['retry-after' => '7'],
    ));

    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');

    expect($decision->errorMessage)->toBe('Rate limit exceeded. Maximum 100 requests per minute.');
    expect($decision->errorCode)->toBe('RATE_LIMITED');
    expect(loggedMessages())->toContain(
        'Spamtroll api: Spamtroll API error [429 RATE_LIMITED]: Rate limit exceeded. Maximum 100 requests per minute.',
    );
});

it('keeps the API key out of the log when the key is rejected', function (): void {
    settings()->spamtroll_api_key = 'sk_live_do_not_leak_me';
    scannerOver(FakeHttpClient::json(401, envelopeBody('INVALID_API_KEY', 'API key is not valid')));

    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');

    expect($decision->action)->toBe(Decision::ACTION_ALLOW);
    foreach (loggedMessages() as $message) {
        expect($message)->not->toContain('sk_live_do_not_leak_me');
    }
    expect($decision->errorMessage)->not->toContain('sk_live_do_not_leak_me');
});

it('distinguishes a 403 from a 401 in the log', function (): void {
    scannerOver(FakeHttpClient::json(403, envelopeBody('FORBIDDEN', 'This key cannot scan that source')));

    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');

    expect($decision->errorCode)->toBe('FORBIDDEN');
    expect(loggedMessages())->toContain('Spamtroll api: Spamtroll API error [403 FORBIDDEN]: This key cannot scan that source');
});

it('counts a quota-exhausted scan and keeps the usage block', function (): void {
    $scanner = scannerOver(FakeHttpClient::json(402, envelopeBody('QUOTA_EXCEEDED', 'Daily quota exhausted', [
        'usage' => ['current' => 200, 'limit' => 200, 'plan' => 'free'],
    ])));

    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');

    expect(quotaLogOf($scanner)->records)->toHaveCount(1);
    expect($decision->quotaUsage)->toBe(['current' => 200, 'limit' => 200, 'plan' => 'free']);
});

it('makes exactly one attempt on the interactive path', function (): void {
    $http = FakeHttpClient::json(500, envelopeBody('SERVER_ERROR', 'upstream unavailable'));
    scannerOver($http);

    Gateway::scanComment('hello', member(), '203.0.113.7');

    expect($http->callCount)->toBe(1);
});

it('finishes a timed-out scan inside the timeout budget', function (): void {
    settings()->spamtroll_timeout = 2;
    scannerOver(FakeHttpClient::throwing(TimeoutException::afterSeconds(2)));

    $started = microtime(true);
    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');
    $elapsed = microtime(true) - $started;

    expect($decision->action)->toBe(Decision::ACTION_ALLOW);
    expect($elapsed)->toBeLessThan(2.2);
});

it('opens the breaker for as long as Retry-After asks', function (): void {
    $store = new ArrayStateStore();
    $clock = new FixedClock(1_700_000_000);
    scannerOver(
        FakeHttpClient::json(429, flatBody('Rate limit exceeded.'), ['retry-after' => '7']),
        $store,
        $clock,
    );

    Gateway::scanComment('hello', member(), '203.0.113.7');

    expect($store->values['spamtroll_backoff_until'])->toBe(1_700_000_007);
});

it('stops touching the network once the breaker is open', function (): void {
    $store = new ArrayStateStore();
    $clock = new FixedClock(1_700_000_000);
    $http = FakeHttpClient::throwing(ConnectionException::fromMessage('Connection refused'));
    scannerOver($http, $store, $clock);

    /* Three failures trip it. */
    for ($i = 0; $i < 3; $i++) {
        expect(Gateway::scanComment('hello', member(), '203.0.113.7')->action)->toBe(Decision::ACTION_ALLOW);
    }
    expect($http->callCount)->toBe(3);

    $decision = Gateway::scanComment('hello', member(), '203.0.113.7');
    expect($http->callCount)->toBe(3);
    expect($decision->skipReason)->toBe('breaker_open');

    /* And starts again once the window has passed. */
    $clock->advance(61);
    Gateway::scanComment('hello', member(), '203.0.113.7');
    expect($http->callCount)->toBe(4);
});

/**
 * The mechanical half of the gate. A new public scan path on the gateway that
 * nobody exercised would otherwise slip in with no fail-open coverage at all.
 */
it('covers every public scan path on the gateway', function (): void {
    $reflection = new ReflectionClass(Gateway::class);

    $public = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            continue;
        }
        if (\in_array($method->getName(), Gateway::NON_SCAN_METHODS, true)) {
            continue;
        }
        $public[] = $method->getName();
    }
    sort($public);

    /* Exercised by this file and by tests/Hooks. Adding a public method
     * without adding it here — and a test for it — turns CI red. */
    $covered = ['applyToComment', 'applyToRegistration', 'scanComment', 'scanRegistration'];
    sort($covered);

    expect($public)->toBe($covered);
});
