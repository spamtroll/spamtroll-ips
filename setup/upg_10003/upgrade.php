<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Anti-Spam Upgrade Step — 1.0.3
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 *
 * 1.0.3 installs the settings the AdminCP form has been writing to since
 * 1.0.2 without them existing, adds the address-hash column, and keeps the
 * existing scoring behaviour on forums that were already tuned.
 */

namespace IPS\spamtroll\setup\upg_10003;

class _Upgrade
{
    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public function step1($data = null)
    {
        /* The AdminCP form has rendered spamtroll_sensitivity and
         * spamtroll_scan_scope since 1.0.2 and saved them through
         * Form::saveAsSettings(). Neither key was ever installed, and
         * Settings::changeValues() drops an unknown conf_key without a word
         * (docs/SUITE-FACTS.md, U10) — so choosing "Strict" and pressing Save
         * did nothing at all, and the page came back showing "Balanced".
         *
         * Derive both from what the forum actually has, rather than
         * defaulting them, so an upgrade does not quietly re-tune anyone. */
        $sensitivity = $this->sensitivityFromThresholds();
        $scanScope = $this->scanScopeFromToggles();

        $this->install([
            'spamtroll_sensitivity' => $sensitivity,
            'spamtroll_scan_scope' => $scanScope,
            'spamtroll_anonymize_ip' => '0',
            /* 1.0.3 takes the verdict from the backend instead of deriving one
             * from thresholds of its own. That is the right behaviour and it
             * is what a fresh install gets — but it changes what an existing
             * forum blocks, so existing forums keep the old path until their
             * administrator turns this off. See CHANGELOG. */
            'spamtroll_override_thresholds' => '1',
            /* The quota-skip counter (HTTP 402 fail-open) reads and writes
             * this key via changeValues(), which silently drops unknown keys
             * — without it, upgraded forums never persist a skip. Fresh
             * installs get '' from install.php; match that default here. */
            'spamtroll_quota_skipped_log' => '',
        ]);

        /* 1.0.2 started writing the scan UUID and 1.0.3 the address hash, but
         * no upgrade step ever added the columns — only fresh installs have
         * them. Add both from the declared schema so upgraded forums match a
         * fresh install. Registration scans carry no member id, so deleting
         * an account never removed them; the hash gives MemberSync something
         * to match on. */
        $this->ensureLogColumns(['log_submission_id', 'log_email_hash']);

        unset(\IPS\Data\Store::i()->settings);

        return true;
    }

    /**
     * @return string
     */
    public function step1CustomTitle()
    {
        return 'Upgrading Spamtroll Anti-Spam to 1.0.3';
    }

    /**
     * The 1.0.2 form wrote a threshold pair for each preset. Read it back.
     */
    protected function sensitivityFromThresholds(): string
    {
        $spam = (float) \IPS\Settings::i()->spamtroll_spam_threshold;

        if ($spam <= 0.5) {
            return 'strict';
        }

        if ($spam >= 0.85) {
            return 'lenient';
        }

        return 'balanced';
    }

    protected function scanScopeFromToggles(): string
    {
        if (!\IPS\Settings::i()->spamtroll_check_posts) {
            return 'off';
        }

        return \IPS\Settings::i()->spamtroll_check_registrations ? 'all' : 'posts_only';
    }

    /**
     * Add log columns missing from pre-1.0.3 tables, using the declared
     * schema as the single source of truth. Guarded per column and
     * idempotent: re-running the upgrader changes nothing.
     *
     * @param array<int, string> $columns
     */
    protected function ensureLogColumns(array $columns): void
    {
        /** @var array<string, array{columns: array<string, array<string, mixed>>}> $schema */
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($columns as $column) {
            try {
                if (!\IPS\Db::i()->checkForColumn('spamtroll_logs', $column)) {
                    \IPS\Db::i()->addColumn('spamtroll_logs', $schema['spamtroll_logs']['columns'][$column]);
                }
            } catch (\Exception $e) {
                /* An install that already has the column is fine. */
            }
        }
    }

    /**
     * @param array<string, string> $settings
     */
    protected function install(array $settings): void
    {
        foreach ($settings as $key => $value) {
            try {
                \IPS\Db::i()->insert('core_sys_conf_settings', [
                    'conf_key' => $key,
                    'conf_value' => $value,
                    'conf_default' => $value,
                    'conf_app' => 'spamtroll',
                ]);
            } catch (\Exception $e) {
                /* Already present — leave the administrator's value alone. */
            }
        }
    }
}
