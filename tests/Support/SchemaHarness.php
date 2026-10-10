<?php

declare(strict_types=1);

namespace IPS;

/** Isolated database double: never loads or connects to a forum. */
class Db
{
    public static array $table = [];
    public static array $changes = [];
    public static array $creates = [];
    public static array $adds = [];
    public static array $addedIndexes = [];
    public static bool $fail = false;
    public static array $settings = [];
    public string $charset = 'utf8mb4';
    public string $collation = 'utf8mb4_unicode_ci';
    private static ?self $instance = null;

    public static function i(): self
    {
        return self::$instance ??= new self();
    }

    public function checkForTable(string $name): bool
    {
        return self::$table !== [];
    }

    public function checkForColumn(string $table, string $column): bool
    {
        return isset(self::$table['columns'][$column]);
    }

    public function createTable(array $definition): void
    {
        self::$creates[] = $definition;
        self::$table = $definition;
    }

    public function addColumn(string $table, array $definition): void
    {
        self::$adds[] = $definition;
        self::$table['columns'][$definition['name']] = $definition;
    }

    public function checkForIndex(string $table, string $name): bool
    {
        return isset(self::$table['indexes'][$name]);
    }

    public function addIndex(string $table, array $definition): void
    {
        self::$addedIndexes[] = $definition;
        self::$table['indexes'][$definition['name']] = $definition;
    }

    public function getTableDefinition(string $table, bool $columnsOnly = false, bool $getCollation = false): array
    {
        return self::$table;
    }

    public function changeColumn(string $table, string $name, array $definition): void
    {
        if (self::$fail) {
            throw new \RuntimeException('DDL failed');
        }
        /* Match the Suite compiler's connection-level collation behaviour. */
        if (isset($definition['collation'])) {
            $definition['collation'] = $this->collation;
            if (!str_starts_with($this->collation, $this->charset . '_')) {
                throw new \RuntimeException('Charset does not match collation');
            }
        }
        self::$changes[$name] = $definition;
        self::$table['columns'][$name] = $definition;
    }

    public function insert(string $table, array $values): void
    {
        if ($table !== 'core_sys_conf_settings') {
            throw new \RuntimeException('Unexpected row write');
        }
        $key = $values['conf_key'];
        if (array_key_exists($key, self::$settings)) {
            throw new \IPS\Db\Exception('Duplicate setting');
        }
        self::$settings[$key] = $values['conf_value'];
    }

    public function delete(string $table, array $where): void
    {
        if ($table !== 'core_sys_conf_settings' || $where[0] !== 'conf_key=?') {
            throw new \RuntimeException('Unexpected row deletion');
        }
        unset(self::$settings[$where[1]]);
    }
}

class DbException extends \RuntimeException
{
}

class_alias(DbException::class, 'IPS\\Db\\Exception');

class Settings
{
    public static function i(): self
    {
        return new self();
    }

    public function __get(string $key): mixed
    {
        return Db::$settings[$key] ?? null;
    }
}

class DataStore
{
    public bool $settings = true;

    public static function i(): self
    {
        static $instance;

        return $instance ??= new self();
    }
}

class_alias(DataStore::class, 'IPS\\Data\\Store');

$root = \dirname(__DIR__, 2);
$schema = json_decode(file_get_contents($root . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR)['spamtroll_logs'];
$scenario = $argv[1];
if ($scenario !== 'fresh' && $scenario !== 'missing') {
    Db::$table = $schema;
    foreach (Db::$table['columns'] as &$column) {
        $column['comment'] = '';
    }
    unset($column);
    /* Prove a comment repair preserves actual types, defaults and collation. */
    Db::$table['columns']['log_content_type']['length'] = 80;
    Db::$table['columns']['log_content_type']['collation'] = 'utf8_general_ci';
    Db::$table['columns']['log_status']['allow_null'] = true;
    Db::$table['columns']['log_status']['default'] = null;
    if ($scenario === 'existing') {
        unset(Db::$table['columns']['log_submission_id'], Db::$table['columns']['log_email_hash']);
    }
}
if ($scenario === 'legacy') {
    unset(Db::$table['indexes']['log_email_hash']);
}
$chain = preg_match('/^chain-(1000[0-3])-(strict|lenient|off|custom)$/', $scenario, $matches) === 1;
if ($chain) {
    $from = (int) $matches[1];
    $preset = $matches[2];
    Db::$settings = [
        'spamtroll_api_key' => 'test-key-preserve-me',
        'spamtroll_spam_threshold' => ['strict' => '0.5', 'lenient' => '0.85', 'off' => '0.7', 'custom' => '0.72'][$preset],
        'spamtroll_suspicious_threshold' => '0.38',
        'spamtroll_check_posts' => $preset === 'off' ? '0' : '1',
        'spamtroll_check_registrations' => $preset === 'lenient' ? '0' : '1',
        'spamtroll_action_blocked' => 'warn',
        'spamtroll_enabled' => '1',
        'spamtroll_bypass_groups' => '4,7',
        'spamtroll_api_url' => 'https://example.invalid/api/v1',
    ];
    if ($from < 10002) {
        Db::$settings['spamtroll_check_messages'] = '1';
    }
    if ($preset === 'custom') {
        Db::$settings += [
            'spamtroll_sensitivity' => 'lenient',
            'spamtroll_scan_scope' => 'off',
            'spamtroll_anonymize_ip' => '1',
            'spamtroll_override_thresholds' => '0',
        ];
    }
    unset(Db::$table['indexes']['log_email_hash']);
    if ($from < 10003) {
        unset(Db::$table['columns']['log_email_hash']);
    }
}
$before = Db::$table;
$beforeSettings = Db::$settings;
$error = null;
try {
    if ($chain) {
        $run = static function () use ($root, $from): void {
            foreach (range($from + 1, 10004) as $version) {
                require_once $root . '/setup/upg_' . $version . '/upgrade.php';
                $class = '\\IPS\\spamtroll\\setup\\upg_' . $version . '\\_Upgrade';
                (new $class())->step1();
            }
            /* Import defaults only after deriving settings from the old values. */
            require_once $root . '/setup/install.php';
            (new \IPS\spamtroll\setup\install\_Install())->step1([]);
        };
        $run();
        $firstSettings = Db::$settings;
        $firstTable = Db::$table;
        Db::$changes = [];
        Db::$adds = [];
        Db::$addedIndexes = [];
        $run();
        $repeatedChanges = Db::$changes;
    } elseif ($scenario === 'fresh' || $scenario === 'existing') {
        require $root . '/setup/install.php';
        (new \IPS\spamtroll\setup\install\_Install())->step1([]);
    } else {
        require $root . '/setup/upg_10004/upgrade.php';
        Db::$fail = $scenario === 'failure';
        if (Db::$fail) {
            /* Fail on a text column after adopting its connection settings. */
            Db::$table['columns']['log_id']['comment'] = $schema['columns']['log_id']['comment'];
            Db::$table['columns']['log_member_id']['comment'] = $schema['columns']['log_member_id']['comment'];
            $before = Db::$table;
        }
        $upgrade = new \IPS\spamtroll\setup\upg_10004\_Upgrade();
        $upgrade->step1();
        $firstChanges = Db::$changes;
        Db::$changes = [];
        $upgrade->step1();
        $repeatedChanges = Db::$changes;
        Db::$changes = $firstChanges;
    }
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
echo json_encode([
    'before' => $before,
    'table' => Db::$table,
    'creates' => Db::$creates,
    'adds' => Db::$adds,
    'changes' => Db::$changes,
    'addedIndexes' => Db::$addedIndexes,
    'repeatedChanges' => $repeatedChanges ?? null,
    'error' => $error,
    'connection' => [Db::i()->charset, Db::i()->collation],
    'settingsBefore' => $beforeSettings,
    'settings' => Db::$settings,
    'firstSettings' => $firstSettings ?? null,
    'firstTable' => $firstTable ?? null,
], JSON_THROW_ON_ERROR);
