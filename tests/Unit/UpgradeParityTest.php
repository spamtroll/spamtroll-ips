<?php

declare(strict_types=1);

/** Run a single upgrade step without the install.php backfill, twice. */
function runUpgradeScenario(string $scenario): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../Support/SchemaHarness.php') . ' ' . escapeshellarg($scenario);
    exec($command, $output, $status);
    expect($status)->toBe(0);

    return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
}

it('repairs already installed 1.0.4 settings without replacing administrator values', function (): void {
    $result = runUpgradeScenario('upg10005');

    expect($result['error'])->toBeNull();
    expect($result['settings']['spamtroll_api_key'])->toBe('test-key-preserve-me');
    expect($result['settings']['spamtroll_enabled'])->toBe('1');
    expect($result['settings']['spamtroll_bypass_min_posts'])->toBe('25');
    expect($result['settings']['spamtroll_quota_skipped_log'])->toBe('');
    expect($result['firstTable']['columns'])->toHaveKeys(['log_submission_id', 'log_email_hash']);
    expect($result['firstTable']['indexes'])->toHaveKey('log_email_hash');
    expect($result['repeatedAdds'])->toBe([]);
    expect($result['repeatedChanges'])->toBe([]);
});

it('creates the 1.0.2 trust-threshold setting and drops the message toggle', function (): void {
    $result = runUpgradeScenario('upg10002');

    expect($result['error'])->toBeNull();
    /* The first run installs the missing setting with the disabled default. */
    expect($result['firstSettings']['spamtroll_bypass_min_posts'])->toBe('0');
    /* The obsolete toggle is gone, everything else is untouched. */
    expect($result['settings'])->not->toHaveKey('spamtroll_check_messages');
    expect($result['settings']['spamtroll_api_key'])->toBe('test-key-preserve-me');
    expect($result['settings']['spamtroll_enabled'])->toBe('1');
    /* The administrator customised the setting between runs; the re-run keeps it. */
    expect($result['settings']['spamtroll_bypass_min_posts'])->toBe('25');
    expect($result['repeatedAdds'])->toBe([]);
    expect($result['repeatedChanges'])->toBe([]);
});

it('adds the scan UUID and address hash columns missing before 1.0.3', function (): void {
    $result = runUpgradeScenario('upg10003');
    $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR)['spamtroll_logs'];

    expect($result['error'])->toBeNull();
    /* Neither column existed on a pre-1.0.3 table; both are added from the
     * declared schema on the first run. */
    expect($result['before']['columns'])->not->toHaveKey('log_submission_id');
    expect($result['before']['columns'])->not->toHaveKey('log_email_hash');
    expect($result['firstAdds'])->toBe([
        $schema['columns']['log_submission_id'],
        $schema['columns']['log_email_hash'],
    ]);
    expect($result['table']['columns']['log_submission_id'])->toBe($schema['columns']['log_submission_id']);
    expect($result['table']['columns']['log_email_hash'])->toBe($schema['columns']['log_email_hash']);
    /* A re-run adds nothing. */
    expect($result['repeatedAdds'])->toBe([]);
    expect($result['repeatedChanges'])->toBe([]);
});

it('derives the 1.0.3 settings from the toggles a 1.0.2 forum actually has', function (): void {
    $result = runUpgradeScenario('upg10003');

    expect($result['error'])->toBeNull();
    expect($result['settings']['spamtroll_sensitivity'])->toBe('balanced');
    expect($result['settings']['spamtroll_scan_scope'])->toBe('all');
    expect($result['settings']['spamtroll_anonymize_ip'])->toBe('0');
    expect($result['settings']['spamtroll_override_thresholds'])->toBe('1');
    /* The quota-skip counter key: fresh installs get '' from install.php, so
     * the upgrade must install the same default (changeValues drops unknown
     * keys silently, otherwise skips never persist on upgraded forums). */
    expect($result['settings']['spamtroll_quota_skipped_log'])->toBe('');
    expect($result['settings'])->toBe($result['firstSettings']);
});
