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

## Installation (any domain / folder)

Works at domain root (`https://example.com/`) or a subdirectory (`http://localhost/stream/`) with **no `.htaccess` edits**.

1. **Upload files** to your web root (e.g. `/www/wwwroot/yoursite` or `htdocs/stream`).
2. **Create a MySQL database** and import the latest dump:
   ```bash
   mysql -u DB_USER -p'DB_PASS' DB_NAME < database.sql
   ```
   (`database.sql` is UTF-8 ready for Linux/aaPanel.)
3. **Connect the database** — edit only `config/database.php`:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
4. Ensure Apache has `mod_rewrite` enabled and `AllowOverride All` for the site.
5. Open the site — `BASE_URL` and `.htaccess` APP_BASE sync automatically.

### Default Admin Credentials
- Username: `admin`
- Password: `admin123` (change after first login)

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
