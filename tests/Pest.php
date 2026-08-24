<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\Breaker;
use IPS\spamtroll\Scanner\Gateway;
use IPS\spamtroll\Scanner\Scanner;
use IPS\spamtroll\Tests\Mocks\FakeMember;
use IPS\spamtroll\Tests\Support\ArrayStateStore;
use IPS\spamtroll\Tests\Support\FakeCommentParent;
use IPS\spamtroll\Tests\Support\FakeHttpClient;
use IPS\spamtroll\Tests\Support\FakeMemberParent;
use IPS\spamtroll\Tests\Support\FixedClock;
use IPS\spamtroll\Tests\Support\RecordingQuotaLog;
use IPS\spamtroll\Tests\Support\RecordingRecorder;
use Spamtroll\Sdk\Client;

/**
 * Everything that survives between tests gets put back. The settings stub, the
 * IPS log buffer, the gateway's scanner, and the instrumented fake parents are
 * all process-global; a test that inherited one of their leftovers would pass
 * or fail for reasons that have nothing to do with what it asserts.
 */
uses()
    ->beforeEach(function (): void {
        \IPS\Settings::setInstance(null);

        $defaults = [
            'spamtroll_enabled' => true,
            'spamtroll_api_key' => 'test-key',
            'spamtroll_api_url' => 'https://api.example.test/api/v1',
            'spamtroll_spam_threshold' => 0.7,
            'spamtroll_suspicious_threshold' => 0.4,
            'spamtroll_check_posts' => true,
            'spamtroll_check_registrations' => true,
            'spamtroll_action_blocked' => 'block',
            'spamtroll_action_suspicious' => 'moderate',
            'spamtroll_bypass_groups' => '',
            'spamtroll_bypass_min_posts' => 0,
            'spamtroll_sensitivity' => 'balanced',
            'spamtroll_override_thresholds' => false,
            'spamtroll_timeout' => 5,
            'spamtroll_anonymize_ip' => false,
            'spam_service_enabled' => true,
            'reg_auth_type' => 'none',
        ];

        $settings = \IPS\Settings::i();
        foreach ($defaults as $key => $value) {
            $settings->{$key} = $value;
        }

        \IPS\Log::$entries = [];
        Gateway::setScanner(null);
        FakeCommentParent::reset();
        FakeMemberParent::reset();
    })
    ->in('Unit', 'Scanner', 'Hooks');

function settings(): IPS\Settings
{
    return IPS\Settings::i();
}

/**
 * @param array<int, int> $groups
 */
function member(int $memberId = 1, int $memberPosts = 0, array $groups = [3]): FakeMember
{
    return new FakeMember($memberId, $memberPosts, $groups);
}

/**
 * A scanner wired to a fake network and in-memory bookkeeping, with the real
 * SDK in between. Installed on the gateway so the hooks reach it too.
 */
function scannerOver(FakeHttpClient $http, ?ArrayStateStore $store = null, ?FixedClock $clock = null): Scanner
{
    $scanner = new Scanner(
        new Client('test-key', \IPS\spamtroll\Scanner\ClientFactory::interactiveConfig(), $http),
        new Breaker($store ?? new ArrayStateStore(), $clock ?? new FixedClock()),
        new RecordingRecorder(),
        new RecordingQuotaLog(),
        $http,
    );

    Gateway::setScanner($scanner);

    return $scanner;
}

function recorderOf(Scanner $scanner): RecordingRecorder
{
    $recorder = $scanner->recorder();
    if (!$recorder instanceof RecordingRecorder) {
        throw new RuntimeException('Scanner is not using the recording recorder');
    }

    return $recorder;
}

function quotaLogOf(Scanner $scanner): RecordingQuotaLog
{
    $reflection = new ReflectionProperty(Scanner::class, 'quotaLog');
    $reflection->setAccessible(true);
    $quotaLog = $reflection->getValue($scanner);

    if (!$quotaLog instanceof RecordingQuotaLog) {
        throw new RuntimeException('Scanner is not using the recording quota log');
    }

    return $quotaLog;
}

/**
 * Every line the application put into the IPS log during this test.
 *
 * @return array<int, string>
 */
function loggedMessages(): array
{
    return array_map(
        static fn (array $entry): string => $entry['what'] instanceof Throwable
            ? $entry['what']->getMessage()
            : (string) $entry['what'],
        array_values(array_filter(
            \IPS\Log::$entries,
            static fn (array $entry): bool => $entry['category'] === 'spamtroll',
        )),
    );
}

/** The API's standard error envelope (shape A of four). */
function envelopeBody(string $code, string $message, ?array $extra = null): array
{
    $error = ['code' => $code, 'message' => $message, 'request_id' => 'req_test'];
    if ($extra !== null) {
        $error += $extra;
    }

    return ['success' => false, 'error' => $error];
}

/** The rate limiter's and the router's shape: `error` is the boolean true. */
function flatBody(string $message): array
{
    return ['error' => true, 'message' => $message];
}

/** A successful scan. */
function scanBody(string $status, float $rawScore): array
{
    return [
        'success' => true,
        'data' => [
            'status' => $status,
            'spam_score' => $rawScore,
            'symbols' => ['TEST_SYMBOL'],
            'threat_categories' => ['test'],
            'submission_id' => '11111111-2222-3333-4444-555555555555',
        ],
    ];
}
