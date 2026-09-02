# aaPanel Cron Setup — Stream Source Checker

These jobs remove **dead M3U8/HLS and DASH sources** from TV channels. They **never delete channels**.

## Quick fix for "Permission denied"

If you see:
```
/www/wwwroot/streampanel.live/cron/run-dash-check.sh: Permission denied
```

Use **one** of these in aaPanel (Shell Script):

### Option 1 — Run with `bash` (no chmod needed)

**M3U8:**
```bash
bash /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

**DASH:**
```bash
bash /www/wwwroot/streampanel.live/cron/run-dash-check.sh
```

**Both:**
```bash
bash /www/wwwroot/streampanel.live/cron/run-all-stream-checks.sh
```

### Option 2 — Direct PHP (most reliable on aaPanel)

Find PHP path on server:
```bash
which php
```

Common paths: `/usr/bin/php` or `/usr/local/php/bin/php`

**M3U8:**
```bash
/usr/local/php/bin/php /www/wwwroot/streampanel.live/cron/check-m3u8-streams.php
```

**DASH:**
```bash
/usr/local/php/bin/php /www/wwwroot/streampanel.live/cron/check-dash-streams.php
```

### Option 3 — Make scripts executable (one-time SSH)

```bash
chmod +x /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
chmod +x /www/wwwroot/streampanel.live/cron/run-dash-check.sh
chmod +x /www/wwwroot/streampanel.live/cron/run-all-stream-checks.sh
```

Then you can use:
```bash
/www/wwwroot/streampanel.live/cron/run-dash-check.sh
```

---

## aaPanel setup

1. Go to **Cron → Add Cron Task**
2. **Task Type:** Shell Script
3. **Execution Cycle:** `0 3,15 * * *` (twice daily) or `0 */6 * * *` (every 6 hours)
4. **Script Content:** use one of the commands above
5. Click **Run** to test immediately

### Recommended: one task for both checks

| Field | Value |
|-------|-------|
| Task Name | `Stream Source Checker` |
| Script Content | `bash /www/wwwroot/streampanel.live/cron/run-all-stream-checks.sh` |

---

## Logs

```bash
tail -50 /www/wwwroot/streampanel.live/logs/m3u8-check.log
tail -50 /www/wwwroot/streampanel.live/logs/dash-check.log
```

## Manual test (SSH)

```bash
bash /www/wwwroot/streampanel.live/cron/run-dash-check.sh
bash /www/wwwroot/streampanel.live/cron/run-m3u8-check.sh
```

## Wrong vs right

**Wrong** (permission denied):
```
/www/wwwroot/streampanel.live/cron/check-dash-streams.php
```

**Right:**
```
bash /www/wwwroot/streampanel.live/cron/run-dash-check.sh
```
or
```
/usr/local/php/bin/php /www/wwwroot/streampanel.live/cron/check-dash-streams.php
```
