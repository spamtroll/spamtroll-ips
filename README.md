# Spamtroll Anti-Spam for IPS Community Suite

Official Spamtroll integration for IPS Community Suite 4.5+ and 5.x. Automatically checks forum posts, private messages, and registrations for spam using the Spamtroll API.

## Features

- **Forum Post Protection**: Automatically scans new posts for spam
- **Private Message Protection**: Checks private messages for spam content
- **Registration Protection**: Screens new member registrations
- **Configurable Actions**: Block, moderate, warn, or allow based on spam score
- **Group Bypass**: Exclude trusted groups from spam checking
- **Detailed Logging**: Track all spam checks with comprehensive logs
- **Dashboard**: View statistics and recent activity
- **API Status Monitoring**: Real-time API health checks
- **Automatic Cleanup**: Background task removes old logs on a configurable schedule

## Requirements

- IPS Community Suite 4.5+ or 5.x
- PHP 7.4+
- Spamtroll API key (get one at <https://spamtroll.io>)
- Running Spamtroll API server (for self-hosted setups)

## Installation

### Method 1: Developer Mode (Recommended for Development)

#### Step 1: Copy application files

```bash
cp -r /path/to/spamtroll-ips /path/to/ips/applications/spamtroll
```

Example for a local installation:

```bash
cp -r ~/git/spamtroll-ips ~/git/dogomania/forum/applications/spamtroll
```

#### Step 2: Enable Developer Mode (if not already enabled)

Edit the `conf_global.php` file in the IPS root directory:

```php
define('IN_DEV', 1);
```

#### Step 3: Install the application via CLI

Run the following PHP script in the IPS directory:

```bash
cd /path/to/ips/forum
php -r "
require 'init.php';

// Load application data
\$appJson = json_decode(file_get_contents('applications/spamtroll/data/application.json'), true);

// Check if already installed
try {
    \$exists = \\IPS\\Db::i()->select('app_id', 'core_applications', array('app_directory=?', 'spamtroll'))->first();
    echo \"Application already installed (ID: \$exists)\n\";
    exit;
} catch (UnderflowException \$e) {
    // Continue with installation
}

// Add application to database
\$maxPos = \\IPS\\Db::i()->select('MAX(app_position)', 'core_applications')->first();

\\IPS\\Db::i()->insert('core_applications', array(
    'app_author' => \$appJson['app_author'],
    'app_directory' => \$appJson['app_directory'],
    'app_protected' => 0,
    'app_enabled' => 1,
    'app_position' => \$maxPos + 1,
    'app_version' => '1.0.0',
    'app_long_version' => 10000,
    'app_update_check' => \$appJson['app_update_check'] ?? '',
    'app_website' => \$appJson['app_website'] ?? '',
    'app_hide_tab' => 0,
));
echo \"Application added to database\n\";

// Create logs table
try {
    \\IPS\\Db::i()->createTable(array(
        'name' => 'spamtroll_logs',
        'columns' => array(
            array('name' => 'log_id', 'type' => 'BIGINT', 'length' => 20, 'unsigned' => true, 'auto_increment' => true),
            array('name' => 'log_member_id', 'type' => 'INT', 'length' => 11, 'unsigned' => true, 'allow_null' => true),
            array('name' => 'log_content_type', 'type' => 'VARCHAR', 'length' => 50, 'default' => ''),
            array('name' => 'log_content_id', 'type' => 'BIGINT', 'length' => 20, 'unsigned' => true, 'allow_null' => true),
            array('name' => 'log_ip_address', 'type' => 'VARCHAR', 'length' => 46, 'allow_null' => true),
            array('name' => 'log_status', 'type' => 'VARCHAR', 'length' => 20, 'default' => 'safe'),
            array('name' => 'log_spam_score', 'type' => 'DECIMAL', 'length' => '5,4', 'default' => '0.0000'),
            array('name' => 'log_symbols', 'type' => 'TEXT', 'allow_null' => true),
            array('name' => 'log_threat_categories', 'type' => 'TEXT', 'allow_null' => true),
            array('name' => 'log_action_taken', 'type' => 'VARCHAR', 'length' => 20, 'default' => 'allow'),
            array('name' => 'log_content_preview', 'type' => 'TEXT', 'allow_null' => true),
            array('name' => 'log_date', 'type' => 'INT', 'length' => 11, 'unsigned' => true, 'default' => 0),
        ),
        'indexes' => array(
            array('type' => 'primary', 'columns' => array('log_id')),
            array('type' => 'key', 'name' => 'log_member_id', 'columns' => array('log_member_id')),
            array('type' => 'key', 'name' => 'log_date', 'columns' => array('log_date')),
            array('type' => 'key', 'name' => 'log_status', 'columns' => array('log_status')),
        ),
    ));
    echo \"Table spamtroll_logs created\n\";
} catch (Exception \$e) {
    echo \"Table already exists or error: \" . \$e->getMessage() . \"\n\";
}

// Add settings
\$settings = json_decode(file_get_contents('applications/spamtroll/data/settings.json'), true);
foreach (\$settings as \$setting) {
    try {
        \\IPS\\Db::i()->insert('core_sys_conf_settings', array(
            'conf_key' => \$setting['key'],
            'conf_value' => \$setting['default'],
            'conf_default' => \$setting['default'],
            'conf_app' => 'spamtroll',
        ));
    } catch (Exception \$e) {
        // May already exist
    }
}
echo \"Settings added\n\";

// Add modules
\$modules = json_decode(file_get_contents('applications/spamtroll/data/modules.json'), true);
foreach (\$modules as \$area => \$areaModules) {
    foreach (\$areaModules as \$key => \$module) {
        try {
            \\IPS\\Db::i()->insert('core_modules', array(
                'sys_module_key' => \$key,
                'sys_module_application' => 'spamtroll',
                'sys_module_area' => \$area,
                'sys_module_protected' => \$module['protected'] ?? 0,
                'sys_module_visible' => 1,
                'sys_module_position' => 1,
                'sys_module_default_controller' => \$module['default_controller'] ?? '',
            ));
        } catch (Exception \$e) {
            // May already exist
        }
    }
}
echo \"Modules added\n\";

// Clear cache
\\IPS\\Data\\Store::i()->clearAll();
echo \"Cache cleared\n\";

echo \"\n=== INSTALLATION COMPLETE ===\n\";
"
```

#### Step 4: Verify installation

```bash
php -r "
require 'init.php';
\$app = \\IPS\\Application::load('spamtroll');
echo 'Spamtroll v' . \$app->version . ' installed successfully!\n';
"
```

### Method 2: Installation via Admin CP

1. Log in to Admin CP
2. Go to **System** > **Applications & Modules**
3. Click **Install** or use the **Developer Center** if Developer Mode is enabled
4. Select the `spamtroll` folder from the applications directory

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
- Private Messages
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

2. **Messages**: Private messages are scanned similarly to posts.

3. **Registrations**: New member registrations are checked using username and email. High-risk registrations can be blocked or sent for review.

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
- Filter by content type (posts/messages/registrations)
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
1. The Spamtroll backend is running (`cd backend && uvicorn main:app --host 0.0.0.0 --port 8080`)
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
- Content type (post/message/registration)
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

## Uninstallation

### Via CLI

```bash
cd /path/to/ips/forum
php -r "
require 'init.php';

// Remove data
\\IPS\\Db::i()->dropTable('spamtroll_logs', true);
\\IPS\\Db::i()->delete('core_sys_conf_settings', array('conf_app=?', 'spamtroll'));
\\IPS\\Db::i()->delete('core_modules', array('sys_module_application=?', 'spamtroll'));
\\IPS\\Db::i()->delete('core_applications', array('app_directory=?', 'spamtroll'));
\\IPS\\Data\\Store::i()->clearAll();

echo 'Spamtroll uninstalled\n';
"
```

Then remove the application folder:

```bash
rm -rf /path/to/ips/applications/spamtroll
```

### Via Admin CP

1. Go to **System** > **Applications & Modules**
2. Find Spamtroll and click **Uninstall**

## Support

- Documentation: <https://spamtroll.io/docs>
- Support: support@spamtroll.io
- GitHub Issues: <https://github.com/spamtroll/ips-community/issues>

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
