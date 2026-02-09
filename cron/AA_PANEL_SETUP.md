# Quick Setup Guide for aaPanel

## The Problem
If you're getting "Permission denied" error, aaPanel is trying to run the PHP file directly as a shell script.

## Solution: Use the Wrapper Script

### Step 1: Upload Files
Make sure both files are uploaded:
- `cron/check-m3u8-streams.php`
- `cron/run-m3u8-check.sh`

### Step 2: Make Wrapper Executable
SSH into your server and run:
```bash
chmod +x /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

### Step 3: Setup Cron in aaPanel

1. Go to **Cron** in aaPanel
2. Click **Add Cron Task**
3. Fill in:
   - **Task Name:** `M3U8 Stream Checker`
   - **Task Type:** Select **Shell Script** or **Command**
   - **Execution Cycle:** `Every 6 hours` or `0 */6 * * *`
   - **Script Content:** 
     ```
     /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
     ```
4. Click **Submit**

### Step 4: Test It
Click **Run** button next to the cron task to test it immediately.

## Alternative: Direct PHP Command

If wrapper doesn't work, try this in aaPanel Cron:

**Task Type:** Shell Script  
**Script Content:**
```bash
/usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php
```

If `/usr/bin/php` doesn't work, find your PHP path:
```bash
which php
```

Then use that path instead.

## Check Logs
After running, check the log:
```bash
tail -f /www/wwwroot/streampanel.live/logs/m3u8-check.log
```

## Still Having Issues?

1. **Check file permissions:**
   ```bash
   ls -la /www/wwwroot/streampanel.live/cron/
   ```

2. **Test PHP manually:**
   ```bash
   /usr/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php
   ```

3. **Test wrapper manually:**
   ```bash
   /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
   ```

4. **Check PHP version:**
   ```bash
   /usr/bin/php -v
   ```