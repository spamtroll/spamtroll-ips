<?php

declare(strict_types=1);

use IPS\spamtroll\Log\Recorder;

describe('anonymiseIp', function (): void {
    it('leaves the address alone by default', function (): void {
        expect(Recorder::anonymiseIp('203.0.113.42'))->toBe('203.0.113.42');
    });

    it('drops the host part of an IPv4 address when asked', function (): void {
        settings()->spamtroll_anonymize_ip = true;

        expect(Recorder::anonymiseIp('203.0.113.42'))->toBe('203.0.113.0');
    });

    it('keeps the first 64 bits of an IPv6 address when asked', function (): void {
        settings()->spamtroll_anonymize_ip = true;

        expect(Recorder::anonymiseIp('2001:db8:85a3:8d3:1319:8a2e:370:7348'))->toBe('2001:db8:85a3:8d3::');
    });

    it('passes through anything that is not an address', function (string|null $value): void {
        settings()->spamtroll_anonymize_ip = true;

        expect(Recorder::anonymiseIp($value))->toBe($value);
    })->with([null, '', 'unknown']);
});

describe('emailHash', function (): void {
    it('hashes the address, and stores nothing else about it', function (): void {
        $hash = Recorder::emailHash('Spammer@Example.TEST');

        expect($hash)->toBe(hash('sha256', 'spammer@example.test'));
        expect($hash)->not->toContain('spammer');
    });

    it('is case- and whitespace-insensitive so a delete actually matches', function (): void {
        expect(Recorder::emailHash('  spammer@example.test '))
            ->toBe(Recorder::emailHash('SPAMMER@EXAMPLE.TEST'));
    });

    it('has nothing to hash for a missing address', function (string|null $value): void {
        expect(Recorder::emailHash($value))->toBeNull();
    })->with([null, '', '   ']);
});
