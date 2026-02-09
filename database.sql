-- Netflix Style Streaming Portal Database Schema

CREATE DATABASE IF NOT EXISTS streaming_portal;
USE streaming_portal;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    subscription_type ENUM('free', 'premium') DEFAULT 'free',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Movies table
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    poster VARCHAR(255),
    video_url VARCHAR(500),
    duration INT,
    release_year YEAR,
    rating DECIMAL(3,1) DEFAULT 0.0,
    views INT DEFAULT 0,
    category_id INT,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- TV Shows table
CREATE TABLE IF NOT EXISTS tv_shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    poster VARCHAR(255),
    release_year YEAR,
    rating DECIMAL(3,1) DEFAULT 0.0,
    views INT DEFAULT 0,
    category_id INT,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- TV Show Episodes table
CREATE TABLE IF NOT EXISTS tv_episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tv_show_id INT NOT NULL,
    season_number INT NOT NULL,
    episode_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    video_url VARCHAR(500),
    duration INT,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tv_show_id) REFERENCES tv_shows(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode (tv_show_id, season_number, episode_number)
);

-- Live TV Channels table
CREATE TABLE IF NOT EXISTS live_tv_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    stream_url VARCHAR(500) NOT NULL,
    category VARCHAR(100),
    featured BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Watch History table
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_type ENUM('movie', 'tv_episode', 'live_tv') NOT NULL,
    content_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Favorites table
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_type ENUM('movie', 'tv_show', 'live_tv') NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, content_type, content_id)
);

-- Insert default admin (password: admin123)
INSERT INTO admins (username, email, password, full_name) VALUES 
('admin', 'admin@stream.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User');

-- Insert sample categories
INSERT INTO categories (name, slug) VALUES 
('Action', 'action'),
('Comedy', 'comedy'),
('Drama', 'drama'),
('Horror', 'horror'),
('Sci-Fi', 'sci-fi'),
('Thriller', 'thriller'),
('Romance', 'romance'),
('Documentary', 'documentary');

-- Insert sample movies
INSERT INTO movies (title, description, thumbnail, poster, video_url, duration, release_year, rating, category_id, featured) VALUES
('The Dark Knight', 'Batman faces the Joker in this epic superhero film.', 'thumb1.jpg', 'poster1.jpg', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 152, 2008, 9.0, 1, TRUE),
('Inception', 'A mind-bending thriller about dream manipulation.', 'thumb2.jpg', 'poster2.jpg', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 148, 2010, 8.8, 6, TRUE),
('The Matrix', 'A computer hacker learns about the true nature of reality.', 'thumb3.jpg', 'poster3.jpg', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 136, 1999, 8.7, 5, TRUE);

-- Insert sample TV shows
INSERT INTO tv_shows (title, description, thumbnail, poster, release_year, rating, category_id, featured) VALUES
('Breaking Bad', 'A high school chemistry teacher turned methamphetamine manufacturer.', 'tv1.jpg', 'tvposter1.jpg', 2008, 9.5, 3, TRUE),
('Game of Thrones', 'Nine noble families fight for control over the lands of Westeros.', 'tv2.jpg', 'tvposter2.jpg', 2011, 9.3, 3, TRUE);

-- Insert sample TV episodes
INSERT INTO tv_episodes (tv_show_id, season_number, episode_number, title, description, video_url, duration) VALUES
(1, 1, 1, 'Pilot', 'Walter White begins his transformation into a drug kingpin.', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 58),
(1, 1, 2, 'Cat\'s in the Bag...', 'Walt and Jesse try to dispose of two bodies.', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 48);

-- Insert sample Live TV channels
INSERT INTO live_tv_channels (name, description, logo, stream_url, category, featured) VALUES
-- Movies Category
('Action Movies HD', '24/7 Action Movies Channel - Non-stop action and adventure', 'action-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Movies', TRUE),
('Comedy Movies', '24/7 Comedy Movies - Laugh out loud entertainment', 'comedy-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Movies', TRUE),
('Drama Channel', 'Premium drama movies and series', 'drama-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Movies', FALSE),
('Horror TV', 'Scary movies and horror content 24/7', 'horror-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Movies', FALSE),
('Sci-Fi Channel', 'Science fiction movies and shows', 'scifi-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Movies', FALSE),
('Thriller Movies', 'Suspense and thriller movies', 'thriller-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Movies', FALSE),
('Romance Channel', 'Romantic movies and love stories', 'romance-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Movies', FALSE),

-- News Category
('World News 24', 'Breaking news from around the world', 'news-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'News', TRUE),
('Business News', 'Latest business and financial news', 'business-news.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'News', FALSE),
('Sports News', 'Sports updates and highlights', 'sports-news.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'News', FALSE),
('Tech News', 'Technology news and updates', 'tech-news.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'News', FALSE),

-- Sports Category
('Sports Central', 'Live sports events and highlights', 'sports-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Sports', TRUE),
('Football Live', 'Live football matches and analysis', 'football-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Sports', TRUE),
('Basketball TV', 'NBA and basketball coverage', 'basketball-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Sports', FALSE),
('Cricket Channel', 'Live cricket matches and highlights', 'cricket-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Sports', FALSE),
('Tennis Network', 'Tennis tournaments and matches', 'tennis-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Sports', FALSE),

-- Entertainment Category
('Entertainment TV', 'Celebrity news and entertainment shows', 'entertainment-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Entertainment', TRUE),
('Comedy Central', '24/7 Comedy Shows and stand-up', 'comedy-central.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Entertainment', TRUE),
('Reality TV', 'Reality shows and competitions', 'reality-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Entertainment', FALSE),
('Talk Shows', 'Late night and talk shows', 'talkshow-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Entertainment', FALSE),

-- Documentary Category
('Documentary Channel', 'Nature, history and science documentaries', 'doc-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Documentary', TRUE),
('Nature Channel', 'Wildlife and nature documentaries', 'nature-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Documentary', FALSE),
('History Channel', 'Historical documentaries and shows', 'history-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Documentary', FALSE),
('Science TV', 'Science and technology documentaries', 'science-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Documentary', FALSE),

-- Kids Category
('Kids TV', 'Cartoons and kids entertainment', 'kids-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Kids', TRUE),
('Cartoon Network', 'Animated shows and cartoons', 'cartoon-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Kids', TRUE),
('Educational Kids', 'Educational content for children', 'edu-kids.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Kids', FALSE),

-- Music Category
('Music Channel', 'Music videos and live performances', 'music-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Music', TRUE),
('Hits TV', 'Top hits and popular music', 'hits-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Music', FALSE),
('Classic Rock', 'Classic rock music videos', 'rock-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Music', FALSE),
('Hip Hop TV', 'Hip hop and rap music videos', 'hiphop-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Music', FALSE),

-- Other Categories
('Cooking Channel', 'Cooking shows and recipes', 'cooking-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Lifestyle', FALSE),
('Travel TV', 'Travel shows and destinations', 'travel-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Lifestyle', FALSE),
('Fashion TV', 'Fashion shows and style content', 'fashion-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Lifestyle', FALSE);
