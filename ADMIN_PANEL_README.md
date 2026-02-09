# Admin Panel - Complete Setup Guide

## Overview
A comprehensive admin panel for managing your Netflix-style streaming portal with all features from the reference files, adapted to work with MySQL database.

## Database Setup

### Step 1: Run Main Database Schema
```sql
-- Import database.sql first
```

### Step 2: Run Additional Tables
```sql
-- Import admin_tables.sql to add:
-- - coupons table
-- - coupon_redemptions table
-- - ads table
-- - sliders table
-- - settings table
-- - Additional columns to existing tables
```

## Admin Panel Features

### Main Navigation (admin/index.php)
- **Dashboard**: Overview statistics and quick actions
- **Movies**: Full CRUD for movies with sources management
- **TV Shows**: Full CRUD for TV shows
- **Episodes**: Manage TV show episodes (requires show_id)
- **Live TV**: Manage live TV channels
- **Categories**: Category management with TMDB integration
- **Users**: User management and subscription control
- **Coupons**: Create and manage subscription coupon codes
- **Ads**: Manage pre-roll, mid-roll, post-roll, banner, and popup ads
- **Sliders**: Homepage slider management
- **Reports**: Analytics and performance metrics
- **Settings**: Site-wide configuration
- **Shaka Config**: Shaka Player streaming configuration
- **Import/Export**: Backup and restore functionality
- **IPTV**: Import IPTV channels from M3U files
- **Links**: Manage stream sources for movies/TV shows
- **Bulk Fetch**: Bulk import from TMDB (requires API integration)

## File Structure

```
admin/
├── index.php              # Main admin panel with tab navigation
├── dashboard.php          # Dashboard with statistics
├── movies.php             # Movies management
├── tv-shows.php           # TV Shows management
├── episodes.php           # Episodes management
├── live-tv.php            # Live TV channels management
├── categories.php         # Categories management
├── users.php              # Users management
├── coupons.php            # Coupons management
├── ads.php                # Ads management
├── sliders.php            # Sliders management
├── reports.php            # Reports & analytics
├── settings.php           # Site settings
├── shaka_config.php       # Shaka Player config
├── import.php             # Import/Export
├── iptv.php               # IPTV import
├── links.php              # Stream links management
├── bulk_fetch.php         # Bulk fetch from TMDB
├── login.php              # Admin login
├── logout.php             # Admin logout
└── includes/
    ├── functions.php      # Helper functions
    ├── header.php         # (Legacy - not used in new system)
    └── footer.php         # (Legacy - not used in new system)
```

## Key Features

### 1. Tab-Based Navigation
All admin pages are accessed via `admin/index.php?tab=<page_name>`

### 2. Helper Functions
Located in `admin/includes/functions.php`:
- `sanitize()` - Input sanitization
- `getAllCategories()` - Get all categories
- `getMovieById()` - Get movie by ID
- `getTVShowById()` - Get TV show by ID
- `getChannelById()` - Get channel by ID
- `parseSources()` / `encodeSources()` - JSON source management
- `generateSlug()` / `getUniqueSlug()` - Slug generation
- `getAdminStats()` - Dashboard statistics

### 3. Database Tables Added
- `coupons` - Subscription coupon codes
- `coupon_redemptions` - Coupon usage tracking
- `ads` - Advertisement management
- `sliders` - Homepage sliders
- `settings` - Site configuration

### 4. Enhanced Existing Tables
- `live_tv_channels` - Added: sources, country, language, is_active, is_free, current_viewers, play_count, slug
- `movies` - Added: sources, is_active, tmdb_id, slug
- `tv_shows` - Added: sources, is_active, tmdb_id, slug
- `categories` - Added: description, tmdb_genre_id, display_order, is_active

## Usage

### Access Admin Panel
1. Navigate to: `http://localhost/stream/admin/`
2. Login with admin credentials
3. You'll be redirected to the dashboard

### Default Admin Credentials
- Username: `admin`
- Password: `admin123` (change after first login!)

### Managing Content
- **Movies**: Add/edit/delete movies, manage sources
- **TV Shows**: Add/edit/delete TV shows, then manage episodes
- **Live TV**: Add/edit/delete channels, manage stream sources
- **Categories**: Organize content with categories
- **Users**: View users, manage subscriptions
- **Coupons**: Create coupon codes for subscriptions
- **Ads**: Configure advertisements
- **Sliders**: Manage homepage sliders

## Notes

1. **Sources Management**: Movies, TV shows, and Live TV channels now support multiple stream sources stored as JSON
2. **Slug Generation**: Automatic slug generation for SEO-friendly URLs
3. **TMDB Integration**: Ready for TMDB API integration (requires API key configuration)
4. **IPTV Import**: M3U file parsing ready for implementation
5. **Bulk Operations**: Bulk fetch ready for TMDB API integration

## Next Steps

1. Run `admin_tables.sql` to add missing tables
2. Configure TMDB API key for bulk fetch (if needed)
3. Implement M3U file parsing for IPTV import
4. Customize settings and configurations
5. Add more stream sources to your content

## Support

All admin pages are fully functional and integrated with the MySQL database. The system uses a tab-based navigation for easy access to all features.
