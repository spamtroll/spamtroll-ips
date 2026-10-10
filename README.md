# Spamtroll Anti-Spam for IPS Community Suite

Spamtroll integration for IPS Community Suite. A manual upgrade to 1.0.4 has been verified on IPS 4.7.24, including live post and registration scans. Fresh ACP installation, native package export and uninstall remain pending; see [verification results](docs/SMOKE.md). IPS 5 is unverified. Private messages are excluded from scanning.

## Features

- **Forum Post Protection**: Automatically scans new posts for spam
- **Registration Protection**: Screens new member registrations
- **Configurable Actions**: Block, moderate, warn, or allow based on spam score
- **Group Bypass**: Exclude trusted groups from spam checking
- **Detailed Logging**: Track all spam checks with comprehensive logs
- **Dashboard**: View statistics and recent activity
- **API Status Monitoring**: Real-time API health checks
- **Automatic Cleanup**: Background task removes old logs on a configurable schedule

## Requirements

- IPS Community Suite 4.7.x (manual upgrade verified on 4.7.24; fresh native lifecycle verification pending)
- PHP 8.0+ (CI tests PHP 8.2–8.4)
- Spamtroll API key (get one at <https://spamtroll.io>)
- Running Spamtroll API server (for self-hosted setups)

## Release package

The GitHub [`v1.0.4` prerelease](https://github.com/spamtroll/spamtroll-ips/releases/tag/v1.0.4) includes `spamtroll-ips-1.0.4-dev.zip`,
a developer installation bundle with the production PHP SDK and templates.
Extract its `spamtroll/` directory into `applications/`, then use Method A.
This ZIP is not an ACP-uploadable application TAR. Do not upload it through
**System → Applications** or **System → Plugins**. Existing installations must
follow [the upgrade guide](docs/UPGRADING.md); the CLI installer alone does not
dispatch migrations. A manual production upgrade is verified, while native ACP
installation/upgrade, uninstall and export remain pending.

To build the bundle from a checkout, run `bash dev/build-release.sh`.
The builder resolves production dependencies in an isolated temporary directory;
it does not include tests, stubs, QA tools or any licensed Suite files.

## Installation

> **⚠️ This is an IPS _application_, not a _plugin_.**
>
> In IPS Community Suite:
> - **Plugins** are single-file `.xml` uploads via **System → Plugins → Install**. They add small tweaks.
> - **Applications** (this one) are full modules with hooks, admin pages, database tables, tasks, and widgets. They go under `applications/<key>/` on disk.
>
> If you try to upload this repository as a plugin, IPS will reject it with **"plugin is invalid"**. Follow the steps below instead.

The development installation and native packaging paths below still require isolated fresh-install verification. Rehearse them on a test forum.

### Method A — CLI install (Developer Mode, `IN_DEV=1`)

Use this path on an isolated development forum until the native lifecycle has been verified. A bundled PHP script performs every step the ACP Developer Center would perform (register the app, create the logs table, register hooks/tasks/widgets, import language strings and templates, compile templates, clear caches).

**Step 1.** Copy the application folder into the IPS installation:

```bash
rsync -a --exclude='.git' ~/git/spamtroll-ips/ /path/to/ips/applications/spamtroll/
chown -R <ips-user>:<ips-group> /path/to/ips/applications/spamtroll
```

**Step 2.** Confirm Developer Mode is enabled in `conf_global.php`:

```php
define('IN_DEV', 1);
```

**Step 3.** Run the installer from the IPS root directory:

```bash
cd /path/to/ips/forum
php applications/spamtroll/setup/cli-install.php
```

The script is idempotent — re-running it on an installed forum only reports `[=]` for existing items.

**Step 4.** Verify:

```bash
php -r "require 'init.php'; \$a = \\IPS\\Application::load('spamtroll'); echo 'Spamtroll v' . \$a->version . ' enabled=' . (int)\$a->enabled . PHP_EOL;"
```

**Step 5.** Log into the ACP → **Community → Spamtroll → Settings**, paste your API key, enable the app.

### Method B — Build a `.tar` package and upload via ACP

For production forums without Developer Mode, or for distribution, build a proper application package:

1. On a development IPS install with `IN_DEV=1`, copy the application folder into `applications/spamtroll/`.
2. Log into the ACP → **System → Applications → Developer Center** → select Spamtroll → **Download**. IPS generates a `.tar` with everything pre-compiled.
3. On the target forum, ACP → **System → Applications → Install** → upload the `.tar`.

IPS automatically registers the app, imports language strings, and compiles templates during the install — no manual steps needed.

> **Why CLI install needs an extra import step.** When you drop a new application folder onto a forum with `IN_DEV=1`, IPS does NOT automatically import templates from `dev/html/`, language strings from `dev/lang.php`, or CSS/JS from `dev/`. Those imports normally happen when the ACP Developer Center builds the `.tar`, or when the ACP installs a `.tar`. `setup/cli-install.php` fills that gap for the CLI install path. This is the root cause of the infamous `template_store_missing` error when people manually copy an application folder and only register it in `core_applications`.

## Configuration

### Step 1: Open Spamtroll settings

In Admin CP go to: **Community** > **Spamtroll** > **Settings**

### Step 2: Configure the API connection

| Setting | Development value | Production value |
|---------|-------------------|------------------|
| **Enable Spamtroll** | Yes | Yes |
| **API Key** | (key from local server) | (production key) |
| **API URL** | `http://spamtroll-api.local/api/v1` | `https://api.spamtroll.io/api/v1` |
| **Timeout** | 5 seconds | 5 seconds |

### Step 3: Configure detection thresholds

| Setting | Recommended value | Description |
|---------|-------------------|-------------|
| **Spam Threshold** | 0.7 | Scores above this are treated as spam |
| **Suspicious Threshold** | 0.4 | Scores above this are treated as suspicious |

### Step 4: Select content types to check

- Forum Posts
- Registrations

### Step 5: Configure actions

| Content type | Recommended action |
|--------------|--------------------|
| **Spam** | Block |
| **Suspicious** | Moderate |

Available actions:
- **Block**: Hide/delete the content
- **Moderate**: Send to moderation queue
- **Warn**: Log only, allow content
- **Allow**: No action

### Step 6: Configure group bypass

Optionally select member groups to bypass spam checking. Administrators are always bypassed.

### Step 7: Test connection

Click the **Test Connection** button to verify API connectivity, then enable Spamtroll and save settings.

## Usage

Once configured, Spamtroll works automatically:

1. **Posts**: When a member creates a post, it's checked against the Spamtroll API. Based on the spam score and your configured thresholds, the appropriate action is taken.

2. **Registrations**: New member registrations are checked using username and email. High-risk registrations can be blocked or sent for review.

## Dashboard

Access the dashboard at **Admin CP** > **Community** > **Spamtroll** > **Dashboard** to view:

- Total scans in the last 7 days
- Number of blocked, suspicious, and safe items
- API status (online/offline/not configured)
- Recent activity
- Latest log entries

## Logs

View detailed logs at **Admin CP** > **Community** > **Spamtroll** > **Logs**:

- Filter by status (blocked/suspicious/safe)
- Filter by content type (posts/registrations)
- Search by IP address
- View detection details including symbols and threat categories
- Export logs to JSON
- Clear old logs manually

## Automatic Cleanup

A background task runs daily to remove logs older than your configured retention period (default: 30 days). Configure the retention period in **Settings** > **Maintenance**.

## Development Environment

When the forum and Spamtroll server are running on the same machine:

```
API URL: http://localhost:8080/api/v1
```

Make sure that:
1. The Spamtroll backend is reachable at the configured API URL
2. You have created a user account in Spamtroll
3. You have generated an API key in the Spamtroll panel

## Verification

### Test 1: Dashboard

Go to **Community** > **Spamtroll** > **Dashboard**. You should see:
- API Status: Online
- Statistics (initially empty)

### Test 2: Spam detection

1. Create a test post containing typical spam, e.g.:
   ```
   Buy cheap viagra online! Click here: http://spam-link.com FREE!!!
   ```
2. Check the logs at **Community** > **Spamtroll** > **Logs**
3. Verify that the post was flagged appropriately

### Test 3: Logs

The logs should show entries with:
- Content type (post/registration)
- Spam score result
- Action taken

## Troubleshooting

### API not responding

1. Check if the Spamtroll backend is running:
   ```bash
   curl http://localhost:8080/api/v1/scan/status
   ```
2. Check the backend logs
3. Verify the URL in settings (without trailing `/`)

### "API key not configured" error

1. Make sure the API key is entered in the settings
2. Verify that the key is valid

### API connection failed

1. Verify your API key is correct
2. Check that your server can reach the API endpoint
3. Ensure firewall rules allow outbound HTTPS connections
4. Try increasing the timeout in settings

### False positives

1. Lower the spam threshold (e.g., from 0.7 to 0.8)
2. Change action for suspicious content to "Warn" instead of "Moderate"
3. Add trusted groups to the bypass list

### Hooks not working

1. Clear the IPS cache: **System** > **Support** > **Clear Caches**
2. Check PHP logs for errors
3. Verify that the application is enabled

### Missing logs

1. Check that logging is working by looking at system logs
2. Verify the database table exists (`spamtroll_logs`)
3. Check PHP error logs for any database errors

### Developer Mode looks enabled but `\IPS\IN_DEV` is false

`IPS\IN_DEV` is populated from the global `IN_DEV` constant, but only at the exact moment `init.php` runs (around line 613 in IPS 4.7). IPS loads `constants.php` before that point, but `conf_global.php` only later, on the first `\IPS\Db::i()` call. If `define('IN_DEV', 1);` lives in `conf_global.php`, it's seen as `defined('IN_DEV') === true` at runtime but `\IPS\IN_DEV === false`, and the framework behaves as if Dev Mode were off.

Move `define('IN_DEV', 1);` out of `conf_global.php` and into `constants.php`. Restart any long-lived PHP processes (opcache) afterwards. To confirm, run `php -r "require 'init.php'; \\IPS\\Db::i(); echo var_export(\\IPS\\IN_DEV, true);"` — it should print `true`, not `false`.

### "plugin is invalid" when uploading the repo

You uploaded the repository (or a zip of it) via **System → Plugins → Install**. This is an _application_, not a _plugin_ — see the note at the top of the Installation section and use Method A or B instead.

### `template_store_missing` / `ErrorException: template_store_missing`

Triggered when IPS tries to render a template for a group that has no compiled entry in the datastore. The two common causes:

1. **The application was installed by only copying files + inserting a row into `core_applications`.** Templates in `dev/html/` were never imported into `core_theme_templates`. Run `php applications/spamtroll/setup/cli-install.php` from the IPS root — it imports the templates and compiles them.
2. **One template file has invalid syntax**, which poisons the entire compiled group (one PHP file holds every template in a group, so one parse error breaks all of them). Look at the raw compiled template in `core_store` with key `template_1_<hash>_<group>`, run `php -l` on it, and fix the offending `.phtml`. Common mistake: writing `{{$foo}}` (raw expression, no echo) when you want echo — the correct form is `{$foo}`.

After fixing a template, delete the compile lock and recompile:

```bash
php -r "require 'init.php'; \\IPS\\Db::i()->delete('core_store', array('store_key LIKE ?', 'template_compiling_%')); \\IPS\\Data\\Store::i()->clearAll(); foreach (glob(\\IPS\\ROOT_PATH.'/datastore/*.php') as \$f) @unlink(\$f); \\IPS\\Theme::load(1)->compileTemplates('spamtroll', 'admin', 'spamtroll');"
```

### Admin menu shows hash strings (e.g. `89950d2aa01ae2ad795b91681fe7529b`) instead of titles

Language strings for the application were not imported into `core_sys_lang_words`. Re-run `php applications/spamtroll/setup/cli-install.php` — the language import step is idempotent.

## Uninstallation

### Via CLI

```bash
cd /path/to/ips/forum
php applications/spamtroll/setup/cli-uninstall.php
rm -rf applications/spamtroll
```

`cli-uninstall.php` drops `spamtroll_logs` and removes every row in `core_applications`, `core_modules`, `core_hooks`, `core_tasks`, `core_widgets`, `core_sys_conf_settings`, `core_sys_lang_words`, and `core_theme_templates` where `app = 'spamtroll'`, then clears caches.

### Via Admin CP

1. Go to **System** > **Applications & Modules**
2. Find Spamtroll and click **Uninstall**

IPS runs `extensions/core/Uninstall/Spamtroll.php` automatically to clean up the logs table and related tasks.

## For developers

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — request flow, how the hooks
  are analysed and tested, why the `_ClassName` prefix exists.
- [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) — local setup, the quality
  gate, release checklist.
- [`docs/SUITE-FACTS.md`](docs/SUITE-FACTS.md) — every behaviour of IPS
  Community Suite this application depends on, with the file and line it was
  read from, and what still needs a running Suite to answer.

## Support

- Documentation: <https://spamtroll.io/docs>
- Support: support@spamtroll.io
- GitHub Issues: <https://github.com/spamtroll/ips-community/issues>

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

### IPS database checker reports `spamtroll_logs`

CLI installs before 1.0.4 omitted the column comments declared in
`data/schema.json`. IPS compares those comments and can propose changing every
column. Its generated `UPDATE ... IS NULL` statements are also emitted for a
comment mismatch on a NOT NULL column; their presence does not prove NULL rows
exist or that the database is corrupt.

For an existing installation, the AdminCP support tool can apply the proposed
schema correction. Back up the database first and schedule it for a quiet period
if the log table is large: `ALTER TABLE` may lock or rebuild the table. Rerun the
checker afterwards. If it fails or still reports differences, inspect the error
and `SHOW CREATE TABLE spamtroll_logs` before retrying.

Version 1.0.4 fixes new CLI installs and includes a versioned upgrade that
repairs comments while preserving actual column attributes. The 1.0.4 developer
bundle includes this fix. A manual 1.0.2 → 1.0.4 upgrade on IPS 4.7.24 passed the
live checker with zero repair queries and preserved prior settings/log rows.
Automated tests also cover upgrades from 1.0.0–1.0.3. Fresh ACP installation and
ACP-driven upgrade remain pending; see [SMOKE.md](docs/SMOKE.md).
