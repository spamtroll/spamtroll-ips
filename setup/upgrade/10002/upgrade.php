<?php

declare(strict_types=1);
/**
 * @brief       Spamtroll Anti-Spam Upgrade Step — 1.0.2
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 *
 * 1.0.2 drops private-message scanning and adds the
 * spamtroll_bypass_min_posts trust-threshold setting.
 *
 * Cleans up the obsolete `spamtroll_check_messages` setting so the
 * legacy entry can't surface anywhere (e.g. raw \IPS\Settings reads,
 * data-export tooling). The new setting is created with default 0
 * (threshold disabled) — set to a positive value in ACP → Spamtroll →
 * Settings → Bypass to skip established members.
 */

namespace IPS\spamtroll\setup\upg_10002;

class _Upgrade
{
    public function step1($data)
    {
        try {
            \IPS\Db::i()->delete('core_sys_conf_settings', [ 'conf_key=?', 'spamtroll_check_messages' ]);
        } catch (\Exception $e) {
            // delete is best-effort; absence is fine
        }

        // Cache invalidation so the dropped setting and the new one
        // both show up correctly on the next page load.
        unset(\IPS\Data\Store::i()->settings);

        return true;
    }

    public function step1CustomTitle()
    {
        return 'Upgrading Spamtroll Anti-Spam to 1.0.2';
    }
}
