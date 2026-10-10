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
    public static bool $fail = false;
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
    }
}

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
$before = Db::$table;
$error = null;
try {
    if ($scenario === 'fresh' || $scenario === 'existing') {
        require $root . '/setup/install.php';
        (new \IPS\spamtroll\setup\install\_Install())->step1([]);
    } else {
        require $root . '/setup/upgrade/10004/upgrade.php';
        Db::$fail = $scenario === 'failure';
        if (Db::$fail) {
            /* Fail on a text column after adopting its connection settings. */
            Db::$table['columns']['log_id']['comment'] = $schema['columns']['log_id']['comment'];
            Db::$table['columns']['log_member_id']['comment'] = $schema['columns']['log_member_id']['comment'];
            $before = Db::$table;
        }
        $upgrade = new \IPS\spamtroll\setup\upg_10004\_Upgrade();
        $upgrade->step1([]);
        $firstChanges = Db::$changes;
        Db::$changes = [];
        $upgrade->step1([]);
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
    'repeatedChanges' => $repeatedChanges ?? null,
    'error' => $error,
    'connection' => [Db::i()->charset, Db::i()->collation],
], JSON_THROW_ON_ERROR);
