<?php

declare(strict_types=1);

namespace IPS\spamtroll\setup\upg_10005;

/** Apply repaired legacy migrations to forums already reporting 1.0.4. */
class _Upgrade
{
    /**
     * @param mixed $data
     *
     * @return bool
     */
    public function step1($data = null)
    {
        $db = \IPS\Db::i();
        $settings = \IPS\Settings::i();
        $threshold = (float) $settings->spamtroll_spam_threshold;
        $defaults = [
            'spamtroll_bypass_min_posts' => '0',
            'spamtroll_quota_skipped_log' => '',
            'spamtroll_sensitivity' => $threshold <= 0.5 ? 'strict' : ($threshold >= 0.85 ? 'lenient' : 'balanced'),
            'spamtroll_scan_scope' => !$settings->spamtroll_check_posts ? 'off' : ($settings->spamtroll_check_registrations ? 'all' : 'posts_only'),
            'spamtroll_anonymize_ip' => '0',
            'spamtroll_override_thresholds' => '1',
        ];
        foreach ($defaults as $key => $value) {
            try {
                $db->insert('core_sys_conf_settings', [
                    'conf_key' => $key,
                    'conf_value' => $value,
                    'conf_default' => $value,
                    'conf_app' => 'spamtroll',
                ]);
            } catch (\RuntimeException $exception) {
                // Only duplicate keys mean that an administrator's value exists.
                if ($exception->getCode() !== 1062) {
                    throw $exception;
                }
            }
        }

        /** @var array<string, array{columns: array<string, array<string, mixed>>}> $schema */
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['log_submission_id', 'log_email_hash'] as $column) {
            if (!$db->checkForColumn('spamtroll_logs', $column)) {
                $db->addColumn('spamtroll_logs', $schema['spamtroll_logs']['columns'][$column]);
            }
        }
        $db->delete('core_sys_conf_settings', ['conf_key=?', 'spamtroll_check_messages']);
        unset(\IPS\Data\Store::i()->settings);

        return true;
    }

    public function step1CustomTitle(): string
    {
        return 'Upgrading Spamtroll Anti-Spam to 1.0.5';
    }
}
