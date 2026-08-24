<?php

declare(strict_types=1);

use IPS\spamtroll\Scanner\Breaker;
use IPS\spamtroll\Tests\Support\ArrayStateStore;
use IPS\spamtroll\Tests\Support\FixedClock;

it('starts closed', function (): void {
    expect((new Breaker(new ArrayStateStore(), new FixedClock()))->isOpen())->toBeFalse();
});

it('opens for the requested window and closes again when it passes', function (): void {
    $clock = new FixedClock(1_700_000_000);
    $breaker = new Breaker(new ArrayStateStore(), $clock);

    $breaker->open(7);

    expect($breaker->isOpen())->toBeTrue();
    expect($breaker->secondsRemaining())->toBe(7);

    $clock->advance(7);
    expect($breaker->isOpen())->toBeFalse();
});

it('clamps an absurd Retry-After', function (): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->open(86_400);

    expect($breaker->secondsRemaining())->toBe(Breaker::MAX_BACKOFF);
});

it('falls back to the default window when the server did not say', function (int|null $seconds): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->open($seconds);

    expect($breaker->secondsRemaining())->toBe(Breaker::DEFAULT_BACKOFF);
})->with([null, 0, -30]);

it('does not shorten a backoff that is already longer', function (): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->open(120);
    $breaker->open(10);

    expect($breaker->secondsRemaining())->toBe(120);
});

it('opens after three consecutive transport failures, not before', function (): void {
    $store = new ArrayStateStore();
    $breaker = new Breaker($store, new FixedClock());

    $breaker->recordTransportFailure();
    $breaker->recordTransportFailure();
    expect($breaker->isOpen())->toBeFalse();

    $breaker->recordTransportFailure();
    expect($breaker->isOpen())->toBeTrue();
    expect($breaker->secondsRemaining())->toBe(Breaker::FAILURE_BACKOFF);
});

it('forgets the streak after a success', function (): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->recordTransportFailure();
    $breaker->recordTransportFailure();
    $breaker->recordSuccess();
    $breaker->recordTransportFailure();
    $breaker->recordTransportFailure();

    expect($breaker->isOpen())->toBeFalse();
});

it('eases off before the server has to say no', function (): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->observeRateLimitHeaders(['x-ratelimit-remaining' => '3']);

    expect($breaker->secondsRemaining())->toBe(Breaker::SOFT_BACKOFF);
});

it('leaves the breaker alone while there is room left', function (): void {
    $breaker = new Breaker(new ArrayStateStore(), new FixedClock());

    $breaker->observeRateLimitHeaders(['x-ratelimit-remaining' => '90']);

    expect($breaker->isOpen())->toBeFalse();
});

it('reads as closed when the datastore is unavailable', function (): void {
    $store = new ArrayStateStore();
    $store->throwOnRead = true;
    $store->throwOnWrite = true;
    $breaker = new Breaker($store, new FixedClock());

    $breaker->open(60);
    $breaker->recordTransportFailure();
    $breaker->recordSuccess();
    $breaker->observeRateLimitHeaders(['x-ratelimit-remaining' => '0']);

    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->secondsRemaining())->toBe(0);
});
