<?php

declare(strict_types=1);

use IPS\spamtroll\Application;

/**
 * The data/*.json manifests, checked from PHP as well as from
 * dev/check-manifests.sh — the shell script is the CI gate, these are the
 * assertions a developer sees when they run the suite.
 */

/** @return array<string, mixed> */
function manifest(string $name): array
{
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/' . $name . '.json'), true);

    return $decoded;
}

it('names a class for every declared extension', function (): void {
    /* `{"core":{"MemberSync":{},"Uninstall":{}}}` shipped for three releases.
     * Application::extensions() does `foreach ($json[$app][$extension] as
     * $name => $classname)` (docs/SUITE-FACTS.md, U2), so an empty object
     * loads nothing: deleting a member removed none of their scan logs, and
     * uninstalling left the table behind. */
    $extensions = manifest('extensions');

    expect($extensions['core']['MemberSync'])->not->toBe([]);
    expect($extensions['core']['Uninstall'])->not->toBe([]);

    foreach ($extensions as $group => $types) {
        foreach ($types as $type => $classes) {
            expect($classes)->not->toBe([]);

            foreach ($classes as $name => $fqcn) {
                $relative = str_replace('IPS\\spamtroll\\', '', $fqcn);
                $path = \dirname(__DIR__, 2) . '/' . str_replace('\\', '/', $relative) . '.php';

                expect($path)->toBeFile();

                $short = substr(strrchr($fqcn, '\\') ?: '', 1);
                expect((string) file_get_contents($path))->toContain('class _' . $short);
            }
        }
    }
});

it('ships a file for every declared hook, and declares every file it ships', function (): void {
    $declared = array_keys(manifest('hooks'));
    sort($declared);

    $onDisk = array_map(
        static fn (string $path): string => basename($path, '.php'),
        glob(\dirname(__DIR__, 2) . '/hooks/*.php') ?: [],
    );
    sort($onDisk);

    expect($declared)->toBe($onDisk);
});

it('ships a file for every declared task', function (): void {
    foreach (array_keys(manifest('tasks')) as $key) {
        expect(\dirname(__DIR__, 2) . '/tasks/' . $key . '.php')->toBeFile();
    }
});

it('keeps the application version in step with data/versions.json', function (): void {
    $versions = manifest('versions');
    $long = max(array_map('intval', array_keys($versions)));

    expect(Application::VERSION_LONG)->toBe($long);
    expect(Application::VERSION)->toBe($versions[(string) $long]);
});

it('installs every setting the AdminCP form reads or writes', function (): void {
    /* Form::saveAsSettings() goes straight to Settings::changeValues(), which
     * drops a key that is not in core_sys_conf_settings without a word
     * (docs/SUITE-FACTS.md, U10). spamtroll_sensitivity and
     * spamtroll_scan_scope were rendered, chosen, saved and discarded for
     * two releases. */
    $declared = array_column(manifest('settings'), 'key');
    $controller = (string) file_get_contents(\dirname(__DIR__, 2) . '/modules/admin/spamtroll/settings.php');

    preg_match_all(
        "/Settings::i\\(\\)->(spamtroll_[a-z_]+)|\\\$values\\['(spamtroll_[a-z_]+)'\\]/",
        $controller,
        $matches,
    );
    $used = array_values(array_unique(array_filter(array_merge($matches[1], $matches[2]))));

    expect($used)->not->toBeEmpty();
    foreach ($used as $key) {
        expect($declared)->toContain($key);
    }
});

it('declares the same settings in the manifest and in the installer', function (): void {
    $declared = array_column(manifest('settings'), 'key');
    sort($declared);

    $installer = (string) file_get_contents(\dirname(__DIR__, 2) . '/setup/install.php');
    preg_match('/\$defaults = \[(.*?)\n        \];/s', $installer, $block);
    preg_match_all("/'(spamtroll_[a-z_]+)'/", $block[1] ?? '', $matches);
    $installed = $matches[1];
    sort($installed);

    expect($installed)->toBe($declared);
});

it('ships an upgrade step for every version past the first', function (): void {
    /* json_decode turns numeric object keys into ints. */
    foreach (array_map('intval', array_keys(manifest('versions'))) as $long) {
        if ($long === 10000) {
            continue;
        }

        expect(\dirname(__DIR__, 2) . '/setup/upgrade/' . $long . '/upgrade.php')->toBeFile();
    }
});
