# M3U8 Stream Checker - Cron Job Setup

This script automatically checks m3u8 streaming links and removes dead sources from channels.

## Features

- ✅ Checks only m3u8/HLS streaming links
- ✅ Processes 20 channels at a time
- ✅ Removes dead m3u8 sources (does NOT delete channels)
- ✅ Proper stream validation (no fake checks)
- ✅ State tracking to resume from last position
- ✅ Detailed logging

## Setup Instructions for aaPanel

### 1. File Location
The script is located at: `cron/check-m3u8-streams.php`

### 2. Option A: Using Wrapper Script (RECOMMENDED)

**Step 1:** Make the wrapper script executable:
```bash
chmod +x /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

**Step 2:** In aaPanel Cron, use this command:
```bash
/www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

**Schedule:** `0 */6 * * *` (every 6 hours)

### 3. Option B: Direct PHP Command

In aaPanel, go to **Cron** section and add a new cron job:

**For aaPanel Cron Task Type:** Select "Shell Script" or "Command"

**Command:**
```bash
/usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php
```

**⚠️ IMPORTANT:** 
- You MUST include `/usr/bin/php` before the script path
- Do NOT run the script directly!
- Make sure you select the correct task type in aaPanel

**Wrong:** `/www/wwwroot/streampanel.live/cron/check-m3u8-streams.php` ❌  
**Correct:** `/usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php` ✅

**Schedule:** Every 6 hours
```
0 */6 * * *
```

This will run at: 00:00, 06:00, 12:00, 18:00

### 3. Find Your PHP Path (if /usr/bin/php doesn't work)

If `/usr/bin/php` doesn't work, find your PHP path:
```bash
which php
```

Or check common PHP locations:
```bash
ls -la /usr/bin/php*
ls -la /usr/local/php/bin/php*
```

Then use that path in the cron command, for example:
```bash
/usr/local/php/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php
```

**Common PHP paths in aaPanel:**
- `/usr/bin/php`
- `/usr/local/php/bin/php`
- `/usr/local/php74/bin/php` (for PHP 7.4)
- `/usr/local/php80/bin/php` (for PHP 8.0)
- `/usr/local/php81/bin/php` (for PHP 8.1)
- `/usr/local/php82/bin/php` (for PHP 8.2)

### 4. Permissions

Make sure the script and logs directory are writable:
```bash
chmod 755 cron/check-m3u8-streams.php
chmod 755 logs/
chmod 755 logs/m3u8-check.log
```

### 5. Test the Script

Before setting up the cron, test it manually:
```bash
php cron/check-m3u8-streams.php
```

Or with full path:
```bash
/usr/bin/php /www/wwwroot/your-domain.com/cron/check-m3u8-streams.php
```

## How It Works

1. **Batch Processing**: Checks 20 channels at a time
2. **State Tracking**: Remembers last checked channel ID to resume next run
3. **M3U8 Only**: Only checks sources with `.m3u8` in URL or `type: "hls"`
4. **Dead Source Removal**: Removes dead m3u8 sources from the sources array
5. **Channel Preservation**: Never deletes channels, only removes dead sources
6. **Logging**: All actions logged to `logs/m3u8-check.log`

## Logs

Check the log file for details:
```bash
tail -f logs/m3u8-check.log
```

## Important Notes

- The script processes 20 channels per run
- If all channels are checked, it resets and starts from the beginning
- Dead m3u8 sources are removed from the `sources` JSON array
- Channels with no sources left are NOT deleted (only logged as warning)
- The script has a 5-minute execution time limit

## Troubleshooting

### Script not running?

**"Permission denied" error?**

This happens when aaPanel tries to execute the PHP file directly. Solutions:

**Solution 1: Use the wrapper script (EASIEST)**
```bash
chmod +x /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```
Then in aaPanel cron, use:
```bash
/www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

**Solution 2: Fix the cron command**
- In aaPanel, make sure the cron task type is set to "Shell Script" or "Command"
- Use the FULL command: `/usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php`
- Do NOT just put the PHP file path alone

**Solution 3: Check PHP path**
Run this in SSH to find your PHP:
```bash
which php
```
Then use that path in the cron command.

**Troubleshooting steps:**
1. Find PHP path: `which php` or check aaPanel PHP settings
2. Test manually: `/usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php`
3. Check file permissions: `ls -la cron/check-m3u8-streams.php` (should be 644 or 755)
4. Check logs: `cat logs/m3u8-check.log`
5. Check PHP version: `/usr/bin/php -v`

### Permission errors?
```bash
chown -R www:www logs/
chmod -R 755 logs/
```

### Database connection errors?
Check your `config/database.php` file has correct credentials.