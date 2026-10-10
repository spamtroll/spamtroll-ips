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
