<?php

declare(strict_types=1);

namespace IPS\spamtroll\setup\upg_10004;

/** Repair metadata omitted by the handwritten CLI installer. */
class _Upgrade
{
    /**
     * @param mixed $data
     *
     * @return bool
     */
    public function step1($data = null)
    {
        /** @var array<string, array{columns: array<string, array<string, mixed>>, indexes: array<string, array<string, mixed>>}> $schema */
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $table = $schema['spamtroll_logs'];
        $db = \IPS\Db::i();
        if (!$db->checkForTable('spamtroll_logs')) {
            $db->createTable($table);

            return true;
        }

        /* Read live definitions, including collation: copying manifest types
         * here could silently change defaults or nullable columns. */
        $live = $db->getTableDefinition('spamtroll_logs', false, true);
        foreach ($table['columns'] as $name => $column) {
            if (!isset($live['columns'][$name])) {
                continue;
            }
            $definition = $live['columns'][$name];
            if (($definition['comment'] ?? '') !== $column['comment']) {
                $definition['comment'] = $column['comment'];
                /* IPS compiles text columns using connection attributes, not
                 * definition['collation']. Borrow the live column's settings
                 * for this statement and restore them even when DDL fails. */
                $charset = $db->charset;
                $collation = $db->collation;
                try {
                    if (isset($definition['collation']) && \is_string($definition['collation']) && $definition['collation'] !== '') {
                        $db->collation = $definition['collation'];
                        $db->charset = explode('_', $db->collation, 2)[0];
                    }
                    $db->changeColumn('spamtroll_logs', $name, $definition);
                } finally {
                    $db->charset = $charset;
                    $db->collation = $collation;
                }
            }
        }

        /* 1.0.3 added the email hash column without its declared lookup index. */
        if ($db->checkForColumn('spamtroll_logs', 'log_email_hash') && !$db->checkForIndex('spamtroll_logs', 'log_email_hash')) {
            $db->addIndex('spamtroll_logs', $table['indexes']['log_email_hash']);
        }

        return true;
    }

    public function step1CustomTitle(): string
    {
        return 'Repairing Spamtroll log column comments';
    }
}
