<?php

declare(strict_types=1);

/** Run installation code without the regular IPS stubs or a database connection. */
function runSchemaScenario(string $scenario): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../Support/SchemaHarness.php') . ' ' . escapeshellarg($scenario);
    exec($command, $output, $status);
    expect($status)->toBe(0);

    return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
}

it('creates the exact declared schema on a fresh CLI installation', function (): void {
    $result = runSchemaScenario('fresh');
    $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($result['error'])->toBeNull();
    expect($result['creates'])->toBe([$schema['spamtroll_logs']]);
});

it('adds declared metadata with missing columns on a repeated installation', function (): void {
    $result = runSchemaScenario('existing');
    $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR)['spamtroll_logs'];
    expect($result['error'])->toBeNull();
    expect($result['creates'])->toBe([]);
    expect($result['adds'])->toBe([$schema['columns']['log_submission_id'], $schema['columns']['log_email_hash']]);
});

it('repairs comments without changing live column attributes or indexes and is repeatable', function (): void {
    $result = runSchemaScenario('upgrade');
    $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR)['spamtroll_logs'];
    $expected = $result['before'];
    foreach ($expected['columns'] as $name => &$column) {
        $column['comment'] = $schema['columns'][$name]['comment'];
    }
    unset($column);
    expect($result['error'])->toBeNull();
    expect($result['table'])->toBe($expected);
    expect($result['repeatedChanges'])->toBe([]);
    expect($result['connection'])->toBe(['utf8mb4', 'utf8mb4_unicode_ci']);
    expect($result['creates'])->toBe([]);
    expect($result['adds'])->toBe([]);
});

it('does not hide a failed schema repair from the IPS upgrader', function (): void {
    $result = runSchemaScenario('failure');
    expect($result['error'])->toBe('DDL failed');
    expect($result['connection'])->toBe(['utf8mb4', 'utf8mb4_unicode_ci']);
    expect($result['table'])->toBe($result['before']);
});

it('adds the declared hash lookup index missing from 1.0.3 upgrades', function (): void {
    $result = runSchemaScenario('legacy');
    $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR)['spamtroll_logs'];
    expect($result['error'])->toBeNull();
    expect($result['addedIndexes'])->toBe([$schema['indexes']['log_email_hash']]);
    expect($result['table']['indexes'])->toBe($schema['indexes']);
});

it('preserves administrator settings across the ordered upgrade chain and repeated imports', function (string $scenario, string $sensitivity, string $scope, string $anonymize, string $override): void {
    $result = runSchemaScenario($scenario);
    expect($result['error'])->toBeNull();
    foreach ($result['settingsBefore'] as $key => $value) {
        if ($key === 'spamtroll_check_messages') {
            expect($result['settings'])->not->toHaveKey($key);

            continue;
        }
        expect($result['settings'][$key])->toBe($value);
    }
    expect($result['settings']['spamtroll_sensitivity'])->toBe($sensitivity);
    expect($result['settings']['spamtroll_scan_scope'])->toBe($scope);
    expect($result['settings']['spamtroll_anonymize_ip'])->toBe($anonymize);
    expect($result['settings']['spamtroll_override_thresholds'])->toBe($override);
    expect($result['settings'])->toBe($result['firstSettings']);
    expect($result['table'])->toBe($result['firstTable']);
    expect($result['table']['columns'])->toHaveKey('log_email_hash');
    expect($result['table']['indexes'])->toHaveKey('log_email_hash');
    expect($result['repeatedChanges'])->toBe([]);
    expect($result['adds'])->toBe([]);
    expect($result['addedIndexes'])->toBe([]);
})->with([
    '1.0.0 strict, all scans' => ['chain-10000-strict', 'strict', 'all', '0', '1'],
    '1.0.1 lenient, posts only' => ['chain-10001-lenient', 'lenient', 'posts_only', '0', '1'],
    '1.0.2 scans disabled' => ['chain-10002-off', 'balanced', 'off', '0', '1'],
    '1.0.2 existing custom settings' => ['chain-10002-custom', 'lenient', 'off', '1', '0'],
    '1.0.3 existing custom settings' => ['chain-10003-custom', 'lenient', 'off', '1', '0'],
]);
