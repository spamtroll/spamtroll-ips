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

## Requirements

- IPS Community Suite 4.5+ or 5.x
- PHP 7.4+
- Spamtroll API key (get one at https://spamtroll.io)

## Installation

### Method 1: Developer Mode (Recommended for Development)

1. Copy the `ips-community` folder to your IPS applications directory:
   ```
   /applications/spamtroll/
   ```

2. Enable Developer Mode in your IPS installation (if not already enabled)

3. Go to Admin CP > System > Applications & Modules

4. Click "Create Application from Developer Files" and select `spamtroll`

### Method 2: Install as Application

1. Build the application using IPS Developer Tools

2. Go to Admin CP > System > Applications & Modules

3. Click "Install" and upload the built application file

## Configuration

1. Go to Admin CP > Community > Spamtroll > Settings

2. Enter your Spamtroll API key

3. Configure spam detection thresholds:
   - **Spam Threshold** (default: 0.7): Scores above this are treated as spam
   - **Suspicious Threshold** (default: 0.4): Scores above this are treated as suspicious

4. Select content types to check:
   - Forum Posts
   - Private Messages
   - Registrations

5. Configure actions for spam and suspicious content:
   - **Block**: Hide/delete the content
   - **Moderate**: Send to moderation queue
   - **Warn**: Log only, allow content
   - **Allow**: No action

6. Optionally select groups to bypass spam checking (administrators are always bypassed)

7. Click "Test Connection" to verify API connectivity

8. Enable Spamtroll and save settings

## Usage

Once configured, Spamtroll works automatically:

1. **Posts**: When a member creates a post, it's checked against the Spamtroll API. Based on the spam score and your configured thresholds, the appropriate action is taken.

2. **Messages**: Private messages are scanned similarly to posts.

3. **Registrations**: New member registrations are checked using username and email. High-risk registrations can be blocked or sent for review.

## Dashboard

Access the dashboard at Admin CP > Community > Spamtroll > Dashboard to view:

- Total scans in the last 7 days
- Number of blocked, suspicious, and safe items
- API status
- Recent activity chart
- Latest log entries

## Logs

View detailed logs at Admin CP > Community > Spamtroll > Logs:

- Filter by status (blocked/suspicious/safe)
- Filter by content type (posts/messages/registrations)
- Search by IP address
- View detection details including symbols and threat categories
- Export logs to JSON
- Clear old logs manually

## Automatic Cleanup

A background task runs daily to remove logs older than your configured retention period (default: 30 days).

## Troubleshooting

### API Connection Failed

1. Verify your API key is correct
2. Check that your server can reach https://api.spamtroll.io
3. Ensure firewall rules allow outbound HTTPS connections
4. Try increasing the timeout in settings

### False Positives

1. Lower the spam threshold (e.g., from 0.7 to 0.8)
2. Change action for suspicious content to "Warn" instead of "Moderate"
3. Add trusted groups to the bypass list

### Missing Logs

1. Check that logging is working by looking at system logs
2. Verify the database table exists (`spamtroll_logs`)
3. Check PHP error logs for any database errors

## Support

- Documentation: https://spamtroll.io/docs
- Support: support@spamtroll.io
- GitHub Issues: https://github.com/spamtroll/ips-community/issues

## License

This integration is released under the MIT License.

## Changelog

### 1.0.0
- Initial release
- Forum post protection
- Private message protection
- Registration protection
- Admin dashboard with statistics
- Comprehensive logging system
- Automatic log cleanup task
