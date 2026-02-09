# StreamFlix - Netflix Style Streaming Portal

A complete Netflix-style streaming portal built with PHP, MySQL, and Tailwind CSS.

## Features

- **User Authentication**: User registration and login system
- **Admin Panel**: Complete admin dashboard for content management
- **Movies**: Browse and watch movies with categories
- **TV Shows**: Browse TV shows with seasons and episodes
- **Live TV**: Watch live TV channels
- **User Profile**: View watch history and favorites
- **Netflix-Style UI**: Beautiful, responsive design with Tailwind CSS

## Installation

1. **Database Setup**:
   - Import `database.sql` into your MySQL database
   - Update database credentials in `config/database.php`

2. **Configuration**:
   - Update `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` in `config/database.php`
   - Update `SITE_URL` in `config/config.php` if needed

3. **Default Admin Credentials**:
   - Username: `admin`
   - Password: `admin123` (change this after first login!)

4. **Web Server**:
   - Place files in your web server directory (e.g., `htdocs/stream`)
   - Ensure PHP and MySQL are running
   - Access via `http://localhost/stream`

## File Structure

```
stream/
├── admin/
│   ├── dashboard.php
│   ├── movies.php
│   ├── tv-shows.php
│   ├── episodes.php
│   ├── live-tv.php
│   ├── categories.php
│   ├── login.php
│   └── includes/
│       ├── header.php
│       └── footer.php
├── config/
│   ├── database.php
│   └── config.php
├── includes/
│   ├── auth.php
│   ├── header.php
│   └── footer.php
├── index.php
├── login.php
├── register.php
├── movies.php
├── tv-shows.php
├── tv-show-detail.php
├── live-tv.php
├── watch.php
├── profile.php
└── database.sql
```

## Usage

### For Users:
1. Register a new account or login
2. Browse movies, TV shows, or Live TV
3. Click on any content to watch
4. View your profile for watch history

### For Admins:
1. Login at `/admin/login.php`
2. Manage movies, TV shows, episodes, and live TV channels
3. Add/edit/delete content
4. Manage categories

## Technologies Used

- PHP 7.4+
- MySQL
- Tailwind CSS (via CDN)
- Font Awesome Icons
- HTML5 Video Player

## Notes

- Video URLs in the database should point to actual video files or streaming URLs
- Image URLs should point to actual image files
- Default admin password should be changed after first login
- The application uses prepared statements to prevent SQL injection

## License

This project is open source and available for use.
