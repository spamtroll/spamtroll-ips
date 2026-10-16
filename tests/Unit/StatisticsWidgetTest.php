<?php

declare(strict_types=1);

use IPS\spamtroll\Application;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    define('\IPS\SUITE_UNIQUE_KEY', 'test');
}

/** A database without the spamtroll_logs table (partial upgrade). */
class ThrowingDb extends \IPS\Db
{
    public function select(string $columns, string $table, $where = null, ?string $order = null, ?int $limit = null): \IPS\Db\Select
    {
        throw new \RuntimeException('no such table: spamtroll_logs');
    }
}

class CapturingStatsWidget extends \IPS\spamtroll\widgets\spamtrollStats
{
    public ?array $captured = null;

    /** @param array<string, mixed> $stats */
    public function output($stats): string
    {
        $this->captured = $stats;

        return 'rendered';
    }
}

describe('getStatistics fail-open', function (): void {
    it('returns zeroed statistics when the logs table is missing', function (): void {
        \IPS\Db::setTestInstance(new ThrowingDb());

        $stats = Application::getStatistics(7);

        expect($stats['total'])->toBe(0);
        expect($stats['blocked'])->toBe(0);
        expect($stats['suspicious'])->toBe(0);
        expect($stats['safe'])->toBe(0);
        expect($stats['daily'])->toHaveCount(7);
        foreach ($stats['daily'] as $day) {
            expect($day['total'])->toBe(0);
            expect($day['blocked'])->toBe(0);
        }
    });

    it('logs the failure instead of surfacing it', function (): void {
        \IPS\Db::setTestInstance(new ThrowingDb());

        Application::getStatistics(7);

        expect(loggedMessages())->not->toBeEmpty();
    });

    it('buckets days in UTC regardless of the server timezone', function (): void {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati'); // UTC+14
        try {
            $stats = Application::getStatistics(3);
        } finally {
            date_default_timezone_set($previous);
        }

        $midnight = (int) gmmktime(0, 0, 0, (int) gmdate('n'), (int) gmdate('j'), (int) gmdate('Y'));
        $expected = [];
        for ($i = 2; $i >= 0; $i--) {
            $expected[] = gmdate('Y-m-d', $midnight - ($i * 86400));
        }

        expect(array_column($stats['daily'], 'date'))->toBe($expected);
    });
});

describe('statistics widget fail-open', function (): void {
    it('renders zeroed statistics when the logs table is missing', function (): void {
        \IPS\Db::setTestInstance(new ThrowingDb());

        $widget = new CapturingStatsWidget('test-widget');

        expect($widget->render())->toBe('rendered');
        expect($widget->captured)->not->toBeNull();
        expect($widget->captured['total'] ?? null)->toBe(0);
        expect($widget->captured['daily'] ?? [])->toHaveCount(7);
    });

    it('renders live statistics when the logs table is present', function (): void {
        $widget = new CapturingStatsWidget('test-widget');

        expect($widget->render())->toBe('rendered');
        expect($widget->captured)->not->toBeNull();
        expect($widget->captured['daily'] ?? [])->toHaveCount(7);
    });
});
