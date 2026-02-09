-- Add Demo Live TV Channels to Existing Database
-- Run this file if you already have a database set up

USE streaming_portal;

-- Clear existing demo channels (optional - comment out if you want to keep existing)
-- DELETE FROM live_tv_channels WHERE id > 0;

-- Insert demo Live TV channels for all categories
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

-- Lifestyle Category
('Cooking Channel', 'Cooking shows and recipes', 'cooking-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'Lifestyle', FALSE),
('Travel TV', 'Travel shows and destinations', 'travel-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'Lifestyle', FALSE),
('Fashion TV', 'Fashion shows and style content', 'fashion-logo.png', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'Lifestyle', FALSE);
