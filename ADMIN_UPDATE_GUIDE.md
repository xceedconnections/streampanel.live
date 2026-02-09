# Admin Panel Complete Update Guide

## Overview
This update adds comprehensive source management, free/premium content control, featured/slider options, and reporting functionality to all admin pages.

## Database Updates Required

Run `admin_tables.sql` to add:
- `sources` column (TEXT/JSON) to `movies`, `tv_shows`, `tv_episodes`, `live_tv_channels`
- `is_free`, `is_premium` columns for content access control
- `show_in_slider` column for homepage slider management
- `reports` table for broken link reporting
- Subscription fields to `users` table

## Features Added

### 1. Multiple Source Management
- Support for: YouTube, Dailymotion, Vimeo, Facebook, Instagram, TikTok, Twitter/X
- Streaming protocols: M3U8 (HLS), MPEG-DASH, RTMP, RTSP, M3U
- Direct video: MP4, Direct links
- Embed options: Iframe, HTML Embed Code
- Priority system: Set priority 0 for default source
- Active/Visible toggles per source

### 2. Free/Premium Content
- `is_free`: Available to all logged-in users
- `is_premium`: Requires active subscription
- Both can be checked (free users see free, premium users see both)

### 3. Featured & Slider Options
- `featured`: Mark content as featured
- `show_in_slider`: Show in homepage slider

### 4. Reporting System
- Users can report broken links
- Admin can view, resolve, or dismiss reports
- Tracks: content type, content ID, source ID, issue type, description

## Files to Update

1. `admin/movies.php` - Complete rewrite with source management
2. `admin/tv-shows.php` - Complete rewrite with source management  
3. `admin/episodes.php` - Episode-wise source management
4. `admin/live-tv.php` - Complete rewrite with source management
5. `admin/reports.php` - New reporting interface
6. `admin/settings.php` - Enhanced settings management

## Source JSON Structure

```json
[
  {
    "id": "src_1234567890_abc123",
    "label": "Server 1 - HD",
    "url": "https://example.com/stream.m3u8",
    "type": "m3u8",
    "quality": "HD",
    "language": "English",
    "priority": 0,
    "isActive": true,
    "isVisible": true
  }
]
```

## Priority System
- Priority 0 = Default source (plays first)
- Lower priority numbers = Higher priority
- Sources sorted by priority ascending

## Next Steps

1. Run `admin_tables.sql`
2. Update admin pages (provided in separate files)
3. Test source management
4. Configure free/premium content
5. Set up reporting system
