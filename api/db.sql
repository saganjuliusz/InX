-- Core User Management System
-- Enhanced user system with advanced personalization and social features
CREATE TABLE users (
    user_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL, -- bcrypt/argon2 hash
    phone_number VARCHAR(20) UNIQUE,
    
    -- Profile Information
    display_name VARCHAR(100),
    bio TEXT,
    avatar_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    birth_date DATE,
    country_code CHAR(2), -- ISO country codes
    timezone VARCHAR(50),
    language_preference VARCHAR(10) DEFAULT 'en',
    
    -- Account Status & Verification
    account_status ENUM('active', 'inactive', 'suspended', 'deleted') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    
    -- Subscription & Features
    subscription_tier ENUM('free', 'premium', 'family', 'student', 'artist', 'admin') DEFAULT 'free',
    subscription_start_date DATETIME,
    subscription_end_date DATETIME,
    auto_renewal BOOLEAN DEFAULT TRUE,
    
    -- Social Features
    is_public_profile BOOLEAN DEFAULT TRUE,
    allow_followers BOOLEAN DEFAULT TRUE,
    show_listening_activity BOOLEAN DEFAULT TRUE,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME,
    last_active_at DATETIME,
    
    -- Analytics
    total_listening_time BIGINT DEFAULT 0, -- in seconds
    total_songs_played BIGINT DEFAULT 0,
    
    -- Metadata
    user_metadata JSON, -- flexible field for future features
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_country (country_code),
    INDEX idx_subscription (subscription_tier),
    INDEX idx_last_active (last_active_at)
);

-- Advanced Artist Management
-- Comprehensive artist profiles with career tracking and collaboration support
CREATE TABLE artists (
    artist_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    stage_name VARCHAR(200),
    real_name VARCHAR(200),
    
    -- Profile Information
    bio TEXT,
    description TEXT,
    avatar_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    website_url VARCHAR(500),
    
    -- Career Information
    formation_date DATE,
    disbandment_date DATE,
    origin_country CHAR(2),
    origin_city VARCHAR(100),
    
    -- Artist Type & Status
    artist_type ENUM('solo', 'band', 'duo', 'collective', 'orchestra', 'producer', 'dj') NOT NULL,
    verification_status ENUM('unverified', 'pending', 'verified', 'premium_verified') DEFAULT 'unverified',
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Social Media & Links
    spotify_url VARCHAR(500),
    apple_music_url VARCHAR(500),
    youtube_url VARCHAR(500),
    instagram_url VARCHAR(500),
    twitter_url VARCHAR(500),
    facebook_url VARCHAR(500),
    tiktok_url VARCHAR(500),
    
    -- Statistics
    total_followers BIGINT DEFAULT 0,
    total_plays BIGINT DEFAULT 0,
    monthly_listeners BIGINT DEFAULT 0,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Metadata
    artist_metadata JSON,
    
    INDEX idx_name (name),
    INDEX idx_stage_name (stage_name),
    INDEX idx_country (origin_country),
    INDEX idx_verification (verification_status),
    INDEX idx_type (artist_type),
    FULLTEXT idx_search (name, stage_name, real_name, bio)
);

-- Artist Collaborations & Relationships
CREATE TABLE artist_relationships (
    relationship_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    primary_artist_id BIGINT NOT NULL,
    related_artist_id BIGINT NOT NULL,
    relationship_type ENUM('member', 'former_member', 'collaborator', 'producer', 'featured', 'remix', 'cover', 'tribute') NOT NULL,
    start_date DATE,
    end_date DATE,
    description TEXT,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (primary_artist_id) REFERENCES artists(artist_id) ON DELETE CASCADE,
    FOREIGN KEY (related_artist_id) REFERENCES artists(artist_id) ON DELETE CASCADE,
    INDEX idx_primary_artist (primary_artist_id),
    INDEX idx_related_artist (related_artist_id),
    INDEX idx_relationship_type (relationship_type)
);

-- Advanced Genre System with Hierarchical Structure
CREATE TABLE genres (
    genre_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    parent_genre_id INT,
    description TEXT,
    color_code VARCHAR(7), -- hex color for UI
    icon_url VARCHAR(500),
    
    -- Popularity metrics
    total_tracks BIGINT DEFAULT 0,
    total_plays BIGINT DEFAULT 0,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_genre_id) REFERENCES genres(genre_id),
    INDEX idx_parent_genre (parent_genre_id),
    INDEX idx_name (name)
);

-- Mood & Atmosphere Tags
CREATE TABLE moods (
    mood_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    color_code VARCHAR(7),
    emoji VARCHAR(10),
    
    INDEX idx_name (name)
);

-- Record Labels & Publishers
CREATE TABLE labels (
    label_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    parent_label_id BIGINT,
    
    -- Contact Information
    contact_website_url VARCHAR(500),
    email VARCHAR(100),
    phone VARCHAR(20),
    
    -- Address
    country CHAR(2),
    city VARCHAR(100),
    address TEXT,
    
    -- Label Information
    founded_date DATE,
    label_type ENUM('major', 'independent', 'subsidiary', 'distributor') DEFAULT 'independent',
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Social Media
    social_website_url VARCHAR(500),
    instagram_url VARCHAR(500),
    twitter_url VARCHAR(500),
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_label_id) REFERENCES labels(label_id),
    INDEX idx_name (name),
    INDEX idx_type (label_type),
    INDEX idx_country (country)
);

-- Enhanced Album Management
CREATE TABLE albums (
    album_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    artist_id BIGINT NOT NULL,
    label_id BIGINT,
    
    -- Album Information
    release_date DATE,
    original_release_date DATE, -- for reissues
    album_type ENUM('studio', 'live', 'compilation', 'soundtrack', 'remix', 'ep', 'single', 'demo') DEFAULT 'studio',
    
    -- Content Information
    total_tracks INT DEFAULT 0,
    total_duration INT DEFAULT 0, -- in seconds
    
    -- Artwork & Media
    cover_art_url VARCHAR(500),
    back_cover_url VARCHAR(500),
    booklet_images JSON, -- array of image URLs
    
    -- Description & Credits
    description TEXT,
    credits TEXT,
    recording_location VARCHAR(200),
    recording_date_start DATE,
    recording_date_end DATE,
    
    -- Commercial Information
    catalog_number VARCHAR(50),
    barcode VARCHAR(20),
    copyright_info TEXT,
    
    -- Quality & Format
    mastered_for_itunes BOOLEAN DEFAULT FALSE,
    spatial_audio_available BOOLEAN DEFAULT FALSE,
    hires_available BOOLEAN DEFAULT FALSE,
    
    -- Statistics
    total_plays BIGINT DEFAULT 0,
    
    -- Status
    is_explicit BOOLEAN DEFAULT FALSE,
    availability_status ENUM('available', 'limited', 'unavailable', 'coming_soon') DEFAULT 'available',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Metadata
    album_metadata JSON,
    
    FOREIGN KEY (artist_id) REFERENCES artists(artist_id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(label_id),
    INDEX idx_title (title),
    INDEX idx_artist (artist_id),
    INDEX idx_release_date (release_date),
    INDEX idx_album_type (album_type),
    FULLTEXT idx_search (title, description)
);

-- Advanced Track Management
CREATE TABLE tracks (
    track_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    artist_id BIGINT NOT NULL,
    album_id BIGINT,
    
    -- Track Information
    track_number INT,
    disc_number INT DEFAULT 1,
    duration INT NOT NULL, -- in seconds
    
    -- Audio Technical Information
    file_path VARCHAR(1000) NOT NULL,
    file_format ENUM('mp3', 'flac', 'wav', 'aac', 'ogg', 'opus', 'mqa') NOT NULL,
    bitrate INT,
    sample_rate INT,
    bit_depth INT,
    
    -- Alternative Versions
    original_track_id BIGINT, -- for remixes, covers, etc.
    version_type ENUM('original', 'remix', 'acoustic', 'live', 'radio_edit', 'extended', 'instrumental', 'cover') DEFAULT 'original',
    
    -- Content Information
    lyrics TEXT,
    lyrics_language VARCHAR(10),
    has_explicit_lyrics BOOLEAN DEFAULT FALSE,
    
    -- Musical Information
    bpm INT,
    musical_key VARCHAR(10), -- C, C#, Dm, etc.
    time_signature VARCHAR(10), -- 4/4, 3/4, etc.
    energy_level DECIMAL(3,2), -- 0.00 to 1.00
    danceability DECIMAL(3,2), -- 0.00 to 1.00
    valence DECIMAL(3,2), -- musical positivity 0.00 to 1.00
    
    -- Credits
    composer VARCHAR(500),
    lyricist VARCHAR(500),
    producer VARCHAR(500),
    featured_artists VARCHAR(500),
    
    -- Technical Audio Features (for AI recommendations)
    acousticness DECIMAL(3,2),
    instrumentalness DECIMAL(3,2),
    liveness DECIMAL(3,2),
    loudness DECIMAL(5,2),
    speechiness DECIMAL(3,2),
    
    -- Commercial Information
    isrc VARCHAR(20), -- International Standard Recording Code
    copyright_info TEXT,
    
    -- Availability & Restrictions
    availability_regions JSON, -- array of country codes
    is_streamable BOOLEAN DEFAULT TRUE,
    is_downloadable BOOLEAN DEFAULT FALSE,
    preview_start_time INT DEFAULT 30, -- seconds
    preview_end_time INT DEFAULT 60, -- seconds
    
    -- Statistics
    play_count BIGINT DEFAULT 0,
    skip_count BIGINT DEFAULT 0,
    like_count BIGINT DEFAULT 0,
    share_count BIGINT DEFAULT 0,
    
    -- AI & ML Features
    audio_fingerprint TEXT, -- for duplicate detection
    waveform_data JSON, -- visualization data
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Metadata
    track_metadata JSON,
    
    FOREIGN KEY (artist_id) REFERENCES artists(artist_id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(album_id) ON DELETE SET NULL,
    FOREIGN KEY (original_track_id) REFERENCES tracks(track_id),
    
    INDEX idx_title (title),
    INDEX idx_artist (artist_id),
    INDEX idx_album (album_id),
    INDEX idx_duration (duration),
    INDEX idx_bpm (bpm),
    INDEX idx_play_count (play_count),
    INDEX idx_energy (energy_level),
    INDEX idx_danceability (danceability),
    FULLTEXT idx_search (title, lyrics, composer, lyricist)
);

-- Track Genre Relationships (Many-to-Many)
CREATE TABLE track_genres (
    track_id BIGINT,
    genre_id INT,
    relevance_score DECIMAL(3,2) DEFAULT 1.00, -- how relevant this genre is to the track
    
    PRIMARY KEY (track_id, genre_id),
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(genre_id) ON DELETE CASCADE,
    INDEX idx_relevance (relevance_score)
);

-- Track Mood Relationships
CREATE TABLE track_moods (
    track_id BIGINT,
    mood_id INT,
    intensity DECIMAL(3,2) DEFAULT 1.00, -- how intense this mood is in the track
    
    PRIMARY KEY (track_id, mood_id),
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    FOREIGN KEY (mood_id) REFERENCES moods(mood_id) ON DELETE CASCADE,
    INDEX idx_intensity (intensity)
);

-- Advanced Playlist System
CREATE TABLE playlists (
    playlist_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    
    -- Playlist Type & Features
    playlist_type ENUM('user', 'collaborative', 'public', 'editorial', 'algorithmic', 'radio') DEFAULT 'user',
    is_public BOOLEAN DEFAULT FALSE,
    is_collaborative BOOLEAN DEFAULT FALSE,
    
    -- Visual Elements
    cover_image_url VARCHAR(500),
    color_theme VARCHAR(7), -- hex color
    
    -- Content Information
    total_tracks INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    
    -- Social Features
    follower_count BIGINT DEFAULT 0,
    play_count BIGINT DEFAULT 0,
    
    -- Algorithmic Features
    auto_update BOOLEAN DEFAULT FALSE, -- for smart playlists
    update_criteria JSON, -- criteria for auto-updating
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_played_at DATETIME,
    
    -- Metadata
    playlist_metadata JSON,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_name (name),
    INDEX idx_type (playlist_type),
    INDEX idx_public (is_public),
    INDEX idx_follower_count (follower_count),
    FULLTEXT idx_search (name, description)
);

-- Playlist Track Relationships with Advanced Features
CREATE TABLE playlist_tracks (
    playlist_id BIGINT,
    track_id BIGINT,
    position INT NOT NULL,
    added_by_user_id BIGINT,
    
    -- Track-specific Information
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    played_at DATETIME,
    play_count INT DEFAULT 0,
    
    -- Collaborative Features
    likes INT DEFAULT 0,
    comments TEXT,
    
    PRIMARY KEY (playlist_id, track_id),
    FOREIGN KEY (playlist_id) REFERENCES playlists(playlist_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    FOREIGN KEY (added_by_user_id) REFERENCES users(user_id),
    INDEX idx_position (position),
    INDEX idx_added_by (added_by_user_id),
    INDEX idx_added_at (added_at)
);

-- Advanced Listening History with Context
CREATE TABLE listening_history (
    history_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    
    -- Listening Context
    played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    listening_duration INT NOT NULL, -- actual seconds listened
    completion_percentage DECIMAL(5,2), -- percentage of track completed
    
    -- Context Information
    platform ENUM('web', 'mobile_ios', 'mobile_android', 'desktop', 'smart_speaker', 'car', 'tv') DEFAULT 'web',
    device_type VARCHAR(100),
    listening_context ENUM('playlist', 'album', 'radio', 'search', 'recommendation', 'shuffle', 'repeat') DEFAULT 'playlist',
    source_playlist_id BIGINT,
    source_album_id BIGINT,
    
    -- Location & Environment
    country_code CHAR(2),
    city VARCHAR(100),
    timezone VARCHAR(50),
    
    -- User Behavior
    was_skipped BOOLEAN DEFAULT FALSE,
    skip_time INT, -- at what point was it skipped
    was_liked BOOLEAN DEFAULT FALSE,
    was_shared BOOLEAN DEFAULT FALSE,
    volume_level INT, -- 0-100
    
    -- Quality Settings
    audio_quality ENUM('low', 'normal', 'high', 'lossless', 'spatial') DEFAULT 'normal',
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    FOREIGN KEY (source_playlist_id) REFERENCES playlists(playlist_id),
    FOREIGN KEY (source_album_id) REFERENCES albums(album_id),
    
    INDEX idx_user_played (user_id, played_at),
    INDEX idx_track_played (track_id, played_at),
    INDEX idx_platform (platform),
    INDEX idx_context (listening_context),
    INDEX idx_completion (completion_percentage)
);

-- Advanced User Interactions & Social Features
CREATE TABLE user_track_interactions (
    interaction_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    
    -- Interaction Types
    is_liked BOOLEAN DEFAULT FALSE,
    is_disliked BOOLEAN DEFAULT FALSE,
    rating DECIMAL(2,1) CHECK (rating BETWEEN 1.0 AND 5.0),
    
    -- Detailed Feedback
    review TEXT,
    tags JSON, -- user-generated tags
    
    -- Behavioral Data
    total_plays INT DEFAULT 0,
    total_listening_time INT DEFAULT 0,
    skip_frequency DECIMAL(3,2) DEFAULT 0.00, -- percentage of times skipped
    
    -- Timestamps
    first_played_at DATETIME,
    last_played_at DATETIME,
    liked_at DATETIME,
    rated_at DATETIME,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_track (user_id, track_id),
    INDEX idx_liked (is_liked),
    INDEX idx_rating (rating),
    INDEX idx_total_plays (total_plays)
);

-- User Following System
CREATE TABLE user_follows (
    follower_id BIGINT,
    following_id BIGINT,
    followed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_enabled BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_followed_at (followed_at)
);

-- Artist Following System
CREATE TABLE artist_follows (
    user_id BIGINT,
    artist_id BIGINT,
    followed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_enabled BOOLEAN DEFAULT TRUE,
    
    PRIMARY KEY (user_id, artist_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES artists(artist_id) ON DELETE CASCADE,
    INDEX idx_followed_at (followed_at)
);

-- Advanced Recommendation Engine Data
CREATE TABLE user_preferences (
    user_id BIGINT PRIMARY KEY,
    
    -- Genre Preferences (JSON array with weights)
    preferred_genres JSON,
    disliked_genres JSON,
    
    -- Mood Preferences
    preferred_moods JSON,
    
    -- Audio Characteristics Preferences
    preferred_energy_range JSON, -- [min, max]
    preferred_valence_range JSON,
    preferred_danceability_range JSON,
    preferred_bpm_range JSON,
    
    -- Discovery Settings
    discovery_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    include_explicit BOOLEAN DEFAULT TRUE,
    language_preferences JSON,
    
    -- Temporal Preferences
    preferred_listening_times JSON, -- hours of day
    preferred_track_length_range JSON, -- [min_seconds, max_seconds]
    
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- AI-Powered Recommendation Logs
CREATE TABLE recommendation_logs (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    
    -- Recommendation Context
    recommendation_type ENUM('discover_weekly', 'daily_mix', 'radio', 'similar_tracks', 'trending', 'mood_based') NOT NULL,
    algorithm_version VARCHAR(50),
    confidence_score DECIMAL(3,2), -- 0.00 to 1.00
    
    -- Recommendation Factors
    factors JSON, -- what influenced this recommendation
    
    -- User Response
    was_played BOOLEAN DEFAULT FALSE,
    was_liked BOOLEAN DEFAULT FALSE,
    was_skipped BOOLEAN DEFAULT FALSE,
    listening_duration INT DEFAULT 0,
    
    recommended_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_user_recommended (user_id, recommended_at),
    INDEX idx_type (recommendation_type),
    INDEX idx_confidence (confidence_score)
);

-- Social Comments System
CREATE TABLE comments (
    comment_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    
    -- Comment Target
    target_type ENUM('track', 'album', 'playlist', 'artist') NOT NULL,
    target_id BIGINT NOT NULL,
    
    -- Comment Content
    content TEXT NOT NULL,
    timestamp_reference INT, -- for track comments, reference to specific time
    
    -- Comment Metadata
    is_public BOOLEAN DEFAULT TRUE,
    language VARCHAR(10),
    
    -- Engagement
    like_count INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    
    -- Moderation
    is_flagged BOOLEAN DEFAULT FALSE,
    moderation_status ENUM('approved', 'pending', 'rejected') DEFAULT 'approved',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_target (target_type, target_id),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_public (is_public),
    FULLTEXT idx_content (content)
);

-- Comment Replies (Nested Comments)
CREATE TABLE comment_replies (
    reply_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    comment_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    
    like_count INT DEFAULT 0,
    is_flagged BOOLEAN DEFAULT FALSE,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_comment (comment_id),
    INDEX idx_user (user_id)
);

-- Advanced Analytics Tables
CREATE TABLE daily_user_stats (
    stat_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    date DATE NOT NULL,
    
    -- Listening Statistics
    total_listening_time INT DEFAULT 0,
    tracks_played INT DEFAULT 0,
    unique_tracks INT DEFAULT 0,
    unique_artists INT DEFAULT 0,
    
    -- Behavior Statistics
    skips INT DEFAULT 0,
    likes INT DEFAULT 0,
    playlist_additions INT DEFAULT 0,
    
    -- Discovery Statistics
    new_tracks_discovered INT DEFAULT 0,
    new_artists_discovered INT DEFAULT 0,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_date (date)
);

CREATE TABLE daily_track_stats (
    stat_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    track_id BIGINT NOT NULL,
    date DATE NOT NULL,
    
    -- Play Statistics
    play_count INT DEFAULT 0,
    unique_listeners INT DEFAULT 0,
    total_listening_time INT DEFAULT 0,
    
    -- Engagement Statistics
    like_count INT DEFAULT 0,
    share_count INT DEFAULT 0,
    playlist_additions INT DEFAULT 0,
    
    -- Quality Statistics
    completion_rate DECIMAL(5,2) DEFAULT 0.00,
    skip_rate DECIMAL(5,2) DEFAULT 0.00,
    
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    UNIQUE KEY unique_track_date (track_id, date),
    INDEX idx_date (date),
    INDEX idx_play_count (play_count)
);

-- ===============================================
-- ADVANCED INDEXES FOR OPTIMAL PERFORMANCE
-- ===============================================

-- Composite indexes for complex queries
CREATE INDEX idx_tracks_artist_album ON tracks(artist_id, album_id);
CREATE INDEX idx_tracks_genre_energy ON tracks(artist_id, energy_level);
CREATE INDEX idx_listening_history_user_date ON listening_history(user_id, played_at);
CREATE INDEX idx_recommendations_user_type ON recommendation_logs(user_id, recommendation_type);
CREATE INDEX idx_playlists_public_followers ON playlists(is_public, follower_count);

-- Full-text search indexes
CREATE FULLTEXT INDEX idx_artists_fulltext ON artists(name, stage_name, bio);
CREATE FULLTEXT INDEX idx_albums_fulltext ON albums(title, description);
CREATE FULLTEXT INDEX idx_tracks_fulltext ON tracks(title, lyrics);
CREATE FULLTEXT INDEX idx_playlists_fulltext ON playlists(name, description);

-- ===============================================
-- ADVANCED VIEWS FOR COMMON QUERIES
-- ===============================================

-- Top Tracks with Comprehensive Information
CREATE VIEW trending_tracks AS
SELECT 
    t.track_id,
    t.title,
    a.name AS artist_name,
    al.title AS album_title,
    t.play_count,
    t.like_count,
    t.duration,
    t.energy_level,
    t.valence,
    COALESCE(AVG(uti.rating), 0) AS average_rating,
    COUNT(DISTINCT uti.user_id) AS total_ratings
FROM tracks t
JOIN artists a ON t.artist_id = a.artist_id
LEFT JOIN albums al ON t.album_id = al.album_id
LEFT JOIN user_track_interactions uti ON t.track_id = uti.track_id
GROUP BY t.track_id
ORDER BY t.play_count DESC, t.like_count DESC
LIMIT 100;

-- User Discovery Dashboard
CREATE VIEW user_discovery_stats AS
SELECT 
    u.user_id,
    u.username,
    COUNT(DISTINCT lh.track_id) AS unique_tracks_played,
    COUNT(DISTINCT t.artist_id) AS unique_artists_played,
    SUM(lh.listening_duration) AS total_listening_time,
    COUNT(DISTINCT DATE(lh.played_at)) AS active_days,
    AVG(lh.completion_percentage) AS avg_completion_rate
FROM users u
LEFT JOIN listening_history lh ON u.user_id = lh.user_id
LEFT JOIN tracks t ON lh.track_id = t.track_id
WHERE lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
GROUP BY u.user_id;

-- Artist Performance Dashboard
CREATE VIEW artist_performance_stats AS
SELECT 
    a.artist_id,
    a.name,
    COUNT(DISTINCT t.track_id) AS total_tracks,
    SUM(t.play_count) AS total_plays,
    SUM(t.like_count) AS total_likes,
    COUNT(DISTINCT af.user_id) AS follower_count,
    AVG(uti.rating) AS average_rating
FROM artists a
LEFT JOIN tracks t ON a.artist_id = t.artist_id
LEFT JOIN artist_follows af ON a.artist_id = af.artist_id
LEFT JOIN user_track_interactions uti ON t.track_id = uti.track_id
GROUP BY a.artist_id;

-- ===============================================
-- STORED PROCEDURES FOR COMPLEX OPERATIONS
-- ===============================================

DELIMITER //

-- Update play count and user statistics
CREATE PROCEDURE update_play_statistics(
    IN p_user_id BIGINT,
    IN p_track_id BIGINT,
    IN p_listening_duration INT,
    IN p_completion_percentage DECIMAL(5,2)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update track play count
    UPDATE tracks 
    SET play_count = play_count + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE track_id = p_track_id;
    
    -- Update user interaction statistics
    INSERT INTO user_track_interactions (user_id, track_id, total_plays, total_listening_time, first_played_at, last_played_at)
    VALUES (p_user_id, p_track_id, 1, p_listening_duration, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ON DUPLICATE KEY UPDATE
        total_plays = total_plays + 1,
        total_listening_time = total_listening_time + p_listening_duration,
        last_played_at = CURRENT_TIMESTAMP;
    
    -- Update user total statistics
    UPDATE users 
    SET total_listening_time = total_listening_time + p_listening_duration,
        total_songs_played = total_songs_played + 1,
        last_active_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
    
    COMMIT;
END //

-- Advanced recommendation algorithm procedure
CREATE PROCEDURE generate_user_recommendations(
    IN p_user_id BIGINT,
    IN p_recommendation_type VARCHAR(50),
    IN p_limit INT DEFAULT 50
)
BEGIN
    DECLARE v_user_exists INT DEFAULT 0;
    
    -- Check if user exists
    SELECT COUNT(*) INTO v_user_exists FROM users WHERE user_id = p_user_id;
    
    IF v_user_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;
    
    -- Generate recommendations based on user listening history and preferences
    INSERT INTO recommendation_logs (user_id, track_id, recommendation_type, confidence_score, factors)
    SELECT 
        p_user_id,
        t.track_id,
        p_recommendation_type,
        (
            -- Calculate confidence score based on multiple factors
            (CASE WHEN tg.genre_id IN (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(preferred_genres, CONCAT('$[', numbers.n, ']')))
                FROM user_preferences up
                CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) numbers
                WHERE up.user_id = p_user_id
                AND JSON_UNQUOTE(JSON_EXTRACT(preferred_genres, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
            ) THEN 0.3 ELSE 0.0 END) +
            (CASE WHEN t.energy_level BETWEEN 0.6 AND 0.9 THEN 0.2 ELSE 0.0 END) +
            (CASE WHEN t.valence > 0.5 THEN 0.15 ELSE 0.0 END) +
            (CASE WHEN af.user_id IS NOT NULL THEN 0.25 ELSE 0.0 END) +
            (CASE WHEN t.play_count > 1000 THEN 0.1 ELSE 0.0 END)
        ) AS confidence_score,
        JSON_OBJECT(
            'genre_match', CASE WHEN tg.genre_id IS NOT NULL THEN 'true' ELSE 'false' END,
            'artist_followed', CASE WHEN af.user_id IS NOT NULL THEN 'true' ELSE 'false' END,
            'popularity_score', t.play_count,
            'energy_level', t.energy_level,
            'valence', t.valence
        ) as factors
    FROM tracks t
    JOIN artists a ON t.artist_id = a.artist_id
    LEFT JOIN track_genres tg ON t.track_id = tg.track_id
    LEFT JOIN artist_follows af ON t.artist_id = af.artist_id AND af.user_id = p_user_id
    LEFT JOIN user_track_interactions uti ON t.track_id = uti.track_id AND uti.user_id = p_user_id
    WHERE uti.track_id IS NULL -- Don't recommend already interacted tracks
    AND t.availability_status = 'available'
    AND a.is_active = TRUE
    HAVING confidence_score > 0.2
    ORDER BY confidence_score DESC, t.play_count DESC
    LIMIT p_limit;
    
END //

-- Procedure to create smart playlists
CREATE PROCEDURE create_smart_playlist(
    IN p_user_id BIGINT,
    IN p_playlist_name VARCHAR(200),
    IN p_criteria JSON
)
BEGIN
    DECLARE v_playlist_id BIGINT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Create the playlist
    INSERT INTO playlists (user_id, name, playlist_type, auto_update, update_criteria)
    VALUES (p_user_id, p_playlist_name, 'algorithmic', TRUE, p_criteria);
    
    SET v_playlist_id = LAST_INSERT_ID();
    
    -- Populate playlist based on criteria
    INSERT INTO playlist_tracks (playlist_id, track_id, position, added_by_user_id)
    SELECT 
        v_playlist_id,
        t.track_id,
        ROW_NUMBER() OVER (ORDER BY t.play_count DESC, t.created_at DESC),
        p_user_id
    FROM tracks t
    JOIN artists a ON t.artist_id = a.artist_id
    LEFT JOIN track_genres tg ON t.track_id = tg.track_id
    LEFT JOIN track_moods tm ON t.track_id = tm.track_id
    WHERE 
        (JSON_EXTRACT(p_criteria, '$.min_energy') IS NULL OR t.energy_level >= JSON_UNQUOTE(JSON_EXTRACT(p_criteria, '$.min_energy'))) AND
        (JSON_EXTRACT(p_criteria, '$.max_energy') IS NULL OR t.energy_level <= JSON_UNQUOTE(JSON_EXTRACT(p_criteria, '$.max_energy'))) AND
        (JSON_EXTRACT(p_criteria, '$.genre_ids') IS NULL OR tg.genre_id IN (
            SELECT JSON_UNQUOTE(JSON_EXTRACT(p_criteria, CONCAT('$.genre_ids[', numbers.n, ']')))
            FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) numbers
            WHERE JSON_UNQUOTE(JSON_EXTRACT(p_criteria, CONCAT('$.genre_ids[', numbers.n, ']'))) IS NOT NULL
        )) AND
        (JSON_EXTRACT(p_criteria, '$.min_bpm') IS NULL OR t.bpm >= JSON_UNQUOTE(JSON_EXTRACT(p_criteria, '$.min_bpm'))) AND
        (JSON_EXTRACT(p_criteria, '$.max_bpm') IS NULL OR t.bpm <= JSON_UNQUOTE(JSON_EXTRACT(p_criteria, '$.max_bpm')))
    LIMIT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_criteria, '$.max_tracks')), 50);
    
    -- Update playlist statistics
    UPDATE playlists p
    SET total_tracks = (SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = v_playlist_id),
        total_duration = (SELECT SUM(t.duration) FROM playlist_tracks pt JOIN tracks t ON pt.track_id = t.track_id WHERE pt.playlist_id = v_playlist_id)
    WHERE p.playlist_id = v_playlist_id;
    
    COMMIT;
    
    SELECT v_playlist_id as playlist_id;
END //

-- Update daily statistics
CREATE PROCEDURE update_daily_statistics()
BEGIN
    DECLARE v_yesterday DATE DEFAULT DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY);
    
    -- Update daily user statistics
    INSERT INTO daily_user_stats (user_id, date, total_listening_time, tracks_played, unique_tracks, unique_artists, skips, likes, playlist_additions)
    SELECT 
        lh.user_id,
        v_yesterday,
        SUM(lh.listening_duration) as total_listening_time,
        COUNT(*) as tracks_played,
        COUNT(DISTINCT lh.track_id) as unique_tracks,
        COUNT(DISTINCT t.artist_id) as unique_artists,
        SUM(CASE WHEN lh.was_skipped THEN 1 ELSE 0 END) as skips,
        COUNT(DISTINCT uti.track_id) as likes,
        COUNT(DISTINCT pt.playlist_id) as playlist_additions
    FROM listening_history lh
    JOIN tracks t ON lh.track_id = t.track_id
    LEFT JOIN user_track_interactions uti ON lh.user_id = uti.user_id AND lh.track_id = uti.track_id AND uti.is_liked = TRUE AND DATE(uti.liked_at) = v_yesterday
    LEFT JOIN playlist_tracks pt ON lh.track_id = pt.track_id AND DATE(pt.added_at) = v_yesterday
    WHERE DATE(lh.played_at) = v_yesterday
    GROUP BY lh.user_id
    ON DUPLICATE KEY UPDATE
        total_listening_time = VALUES(total_listening_time),
        tracks_played = VALUES(tracks_played),
        unique_tracks = VALUES(unique_tracks),
        unique_artists = VALUES(unique_artists),
        skips = VALUES(skips),
        likes = VALUES(likes),
        playlist_additions = VALUES(playlist_additions);
    
    -- Update daily track statistics
    INSERT INTO daily_track_stats (track_id, date, play_count, unique_listeners, total_listening_time, like_count, share_count, playlist_additions, completion_rate, skip_rate)
    SELECT 
        lh.track_id,
        v_yesterday,
        COUNT(*) as play_count,
        COUNT(DISTINCT lh.user_id) as unique_listeners,
        SUM(lh.listening_duration) as total_listening_time,
        COUNT(DISTINCT CASE WHEN uti.is_liked THEN uti.user_id END) as like_count,
        SUM(CASE WHEN lh.was_shared THEN 1 ELSE 0 END) as share_count,
        COUNT(DISTINCT pt.playlist_id) as playlist_additions,
        AVG(lh.completion_percentage) as completion_rate,
        (SUM(CASE WHEN lh.was_skipped THEN 1 ELSE 0 END) / COUNT(*)) * 100 as skip_rate
    FROM listening_history lh
    LEFT JOIN user_track_interactions uti ON lh.user_id = uti.user_id AND lh.track_id = uti.track_id AND uti.is_liked = TRUE
    LEFT JOIN playlist_tracks pt ON lh.track_id = pt.track_id AND DATE(pt.added_at) = v_yesterday
    WHERE DATE(lh.played_at) = v_yesterday
    GROUP BY lh.track_id
    ON DUPLICATE KEY UPDATE
        play_count = VALUES(play_count),
        unique_listeners = VALUES(unique_listeners),
        total_listening_time = VALUES(total_listening_time),
        like_count = VALUES(like_count),
        share_count = VALUES(share_count),
        playlist_additions = VALUES(playlist_additions),
        completion_rate = VALUES(completion_rate),
        skip_rate = VALUES(skip_rate);
END //

DELIMITER ;

-- ===============================================
-- ADVANCED TRIGGERS FOR AUTOMATED PROCESSES
-- ===============================================

DELIMITER //

-- Update playlist statistics when tracks are added/removed
CREATE TRIGGER update_playlist_stats_after_insert
AFTER INSERT ON playlist_tracks
FOR EACH ROW
BEGIN
    UPDATE playlists p
    SET total_tracks = (SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = NEW.playlist_id),
        total_duration = (SELECT SUM(t.duration) FROM playlist_tracks pt JOIN tracks t ON pt.track_id = t.track_id WHERE pt.playlist_id = NEW.playlist_id),
        updated_at = CURRENT_TIMESTAMP
    WHERE p.playlist_id = NEW.playlist_id;
END //

CREATE TRIGGER update_playlist_stats_after_delete
AFTER DELETE ON playlist_tracks
FOR EACH ROW
BEGIN
    UPDATE playlists p
    SET total_tracks = (SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = OLD.playlist_id),
        total_duration = (SELECT COALESCE(SUM(t.duration), 0) FROM playlist_tracks pt JOIN tracks t ON pt.track_id = t.track_id WHERE pt.playlist_id = OLD.playlist_id),
        updated_at = CURRENT_TIMESTAMP
    WHERE p.playlist_id = OLD.playlist_id;
END //

-- Update artist follower count
CREATE TRIGGER update_artist_followers_after_insert
AFTER INSERT ON artist_follows
FOR EACH ROW
BEGIN
    UPDATE artists 
    SET total_followers = total_followers + 1
    WHERE artist_id = NEW.artist_id;
END //

CREATE TRIGGER update_artist_followers_after_delete
AFTER DELETE ON artist_follows
FOR EACH ROW
BEGIN
    UPDATE artists 
    SET total_followers = total_followers - 1
    WHERE artist_id = OLD.artist_id;
END //

-- Update track like count
CREATE TRIGGER update_track_likes_after_insert
AFTER INSERT ON user_track_interactions
FOR EACH ROW
BEGIN
    IF NEW.is_liked = TRUE THEN
        UPDATE tracks 
        SET like_count = like_count + 1
        WHERE track_id = NEW.track_id;
    END IF;
END //

CREATE TRIGGER update_track_likes_after_update
AFTER UPDATE ON user_track_interactions
FOR EACH ROW
BEGIN
    IF OLD.is_liked = FALSE AND NEW.is_liked = TRUE THEN
        UPDATE tracks 
        SET like_count = like_count + 1
        WHERE track_id = NEW.track_id;
    ELSEIF OLD.is_liked = TRUE AND NEW.is_liked = FALSE THEN
        UPDATE tracks 
        SET like_count = like_count - 1
        WHERE track_id = NEW.track_id;
    END IF;
END //

-- Auto-update smart playlists when new tracks are added
CREATE TRIGGER update_smart_playlists_after_track_insert
AFTER INSERT ON tracks
FOR EACH ROW
BEGIN
    -- This is a simplified version - in production, you'd want a more sophisticated system
    INSERT INTO playlist_tracks (playlist_id, track_id, position, added_by_user_id)
    SELECT 
        p.playlist_id,
        NEW.track_id,
        COALESCE(MAX(pt.position), 0) + 1,
        p.user_id
    FROM playlists p
    LEFT JOIN playlist_tracks pt ON p.playlist_id = pt.playlist_id
    LEFT JOIN track_genres tg ON NEW.track_id = tg.track_id
    WHERE p.auto_update = TRUE
    AND p.playlist_type = 'algorithmic'
    AND (
        JSON_EXTRACT(p.update_criteria, '$.genre_ids') IS NULL 
        OR tg.genre_id IN (
            SELECT JSON_UNQUOTE(JSON_EXTRACT(p.update_criteria, CONCAT('$.genre_ids[', numbers.n, ']')))
            FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) numbers
            WHERE JSON_UNQUOTE(JSON_EXTRACT(p.update_criteria, CONCAT('$.genre_ids[', numbers.n, ']'))) IS NOT NULL
        )
    )
    AND (JSON_EXTRACT(p.update_criteria, '$.min_energy') IS NULL OR NEW.energy_level >= JSON_UNQUOTE(JSON_EXTRACT(p.update_criteria, '$.min_energy')))
    AND (JSON_EXTRACT(p.update_criteria, '$.max_energy') IS NULL OR NEW.energy_level <= JSON_UNQUOTE(JSON_EXTRACT(p.update_criteria, '$.max_energy')))
    GROUP BY p.playlist_id;
END //

DELIMITER ;

-- ===============================================
-- ADVANCED SEARCH AND DISCOVERY FUNCTIONS
-- ===============================================

DELIMITER //

-- Advanced search function with AI-powered relevance scoring
CREATE FUNCTION calculate_search_relevance(
    p_query VARCHAR(200),
    p_title VARCHAR(200),
    p_artist_name VARCHAR(200),
    p_album_title VARCHAR(200),
    p_lyrics TEXT,
    p_play_count BIGINT,
    p_like_count BIGINT
) RETURNS DECIMAL(5,4)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_relevance DECIMAL(5,4) DEFAULT 0.0000;
    DECLARE v_query_lower VARCHAR(200) DEFAULT LOWER(p_query);
    DECLARE v_title_lower VARCHAR(200) DEFAULT LOWER(p_title);
    DECLARE v_artist_lower VARCHAR(200) DEFAULT LOWER(p_artist_name);
    DECLARE v_album_lower VARCHAR(200) DEFAULT LOWER(COALESCE(p_album_title, ''));
    DECLARE v_lyrics_lower TEXT DEFAULT LOWER(COALESCE(p_lyrics, ''));
    
    -- Exact title match gets highest score
    IF v_title_lower = v_query_lower THEN
        SET v_relevance = v_relevance + 1.0000;
    -- Title starts with query
    ELSEIF v_title_lower LIKE CONCAT(v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.8000;
    -- Title contains query
    ELSEIF v_title_lower LIKE CONCAT('%', v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.6000;
    END IF;
    
    -- Artist name matching
    IF v_artist_lower = v_query_lower THEN
        SET v_relevance = v_relevance + 0.9000;
    ELSEIF v_artist_lower LIKE CONCAT(v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.7000;
    ELSEIF v_artist_lower LIKE CONCAT('%', v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.5000;
    END IF;
    
    -- Album title matching
    IF v_album_lower LIKE CONCAT('%', v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.3000;
    END IF;
    
    -- Lyrics matching (lower weight)
    IF v_lyrics_lower LIKE CONCAT('%', v_query_lower, '%') THEN
        SET v_relevance = v_relevance + 0.2000;
    END IF;
    
    -- Popularity boost (normalized)
    SET v_relevance = v_relevance + (LEAST(p_play_count / 100000.0, 0.2));
    SET v_relevance = v_relevance + (LEAST(p_like_count / 10000.0, 0.1));
    
    RETURN LEAST(v_relevance, 2.0000);
END //

DELIMITER ;

-- ===============================================
-- DATA SEEDING AND INITIAL SETUP
-- ===============================================

-- Insert basic genres
INSERT INTO genres (name, description, color_code) VALUES
('Pop', 'Popular music with catchy melodies and wide appeal', '#FF6B6B'),
('Rock', 'Guitar-driven music with strong rhythm', '#4ECDC4'),
('Hip Hop', 'Rhythmic music with rap vocals', '#45B7D1'),
('Electronic', 'Music produced using electronic instruments', '#96CEB4'),
('Jazz', 'Improvisational music with complex harmonies', '#FFEAA7'),
('Classical', 'Traditional orchestral and chamber music', '#DDA0DD'),
('Country', 'American folk music with storytelling', '#F4A460'),
('R&B', 'Rhythm and blues with soulful vocals', '#FFB6C1'),
('Reggae', 'Jamaican music with distinctive rhythm', '#98FB98'),
('Folk', 'Traditional acoustic music', '#DEB887'),
('Punk', 'Fast, raw rock music', '#FF69B4'),
('Metal', 'Heavy, aggressive rock music', '#696969'),
('Funk', 'Groove-based music with strong rhythm', '#DAA520'),
('Blues', 'Emotional music with twelve-bar structure', '#4169E1'),
('Indie', 'Independent alternative music', '#9370DB');

-- Insert basic moods
INSERT INTO moods (name, description, color_code, emoji) VALUES
('Happy', 'Upbeat and joyful music', '#FFD700', '😊'),
('Sad', 'Melancholic and emotional music', '#4682B4', '😢'),
('Energetic', 'High-energy and motivating music', '#FF4500', '⚡'),
('Relaxed', 'Calm and peaceful music', '#98FB98', '😌'),
('Romantic', 'Love songs and intimate music', '#FF69B4', '💕'),
('Angry', 'Aggressive and intense music', '#DC143C', '😡'),
('Nostalgic', 'Music that evokes memories', '#DDA0DD', '🌅'),
('Party', 'Fun and celebratory music', '#FF1493', '🎉'),
('Focus', 'Music for concentration and work', '#6495ED', '🎯'),
('Workout', 'High-energy music for exercise', '#FF6347', '💪'),
('Chill', 'Laid-back and mellow music', '#20B2AA', '🌊'),
('Mysterious', 'Dark and atmospheric music', '#483D8B', '🌙');


-- ===============================================
-- PERFORMANCE OPTIMIZATION SETTINGS
-- ===============================================

-- Optimize MySQL settings for music streaming workload
-- These would typically be set in my.cnf, but shown here for reference

/*
Recommended MySQL Configuration for Music Streaming:

[mysqld]
# Memory settings
innodb_buffer_pool_size = 70% of available RAM
innodb_buffer_pool_instances = 8
innodb_log_file_size = 512M
innodb_log_buffer_size = 16M

# Performance settings
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_type = 0
query_cache_size = 0

# Connection settings
max_connections = 1000
thread_cache_size = 16
table_open_cache = 4000

# Replication settings (for read replicas)
server-id = 1
log-bin = mysql-bin
binlog_format = ROW
read_only = 0
*/

-- ===============================================
-- FINAL CLEANUP AND OPTIMIZATIONS
-- ===============================================

-- Additional composite indexes for complex queries
CREATE INDEX idx_tracks_artist_genre_energy ON tracks(artist_id, energy_level, valence);
CREATE INDEX idx_listening_history_complex ON listening_history(user_id, played_at, completion_percentage);
CREATE INDEX idx_recommendations_user_confidence ON recommendation_logs(user_id, confidence_score DESC);
CREATE INDEX idx_user_interactions_complex ON user_track_interactions(user_id, is_liked, rating, total_plays);

-- Partitioning for large tables (example for listening_history)
-- This would be implemented based on date ranges for better performance
/*
ALTER TABLE listening_history 
PARTITION BY RANGE (YEAR(played_at)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/

-- ===============================================
-- COMMENTS AND DOCUMENTATION
-- ===============================================

/*
SCHEMA OVERVIEW:
================

This database schema is designed to power a next-generation music streaming platform
that combines the best features of Spotify, Tidal, and SoundCloud while adding
innovative features for the future of music consumption.

KEY INNOVATIONS:
================

1. ADVANCED AI/ML INTEGRATION:
   - Detailed audio feature analysis (energy, valence, danceability, etc.)
   - Sophisticated recommendation engine with confidence scoring
   - User preference learning and adaptation
   - Audio fingerprinting for duplicate detection

2. COMPREHENSIVE SOCIAL FEATURES:
   - User following and artist following systems
   - Collaborative playlists with detailed contribution tracking
   - Comment system with timestamp references for tracks
   - Social activity feeds and sharing capabilities

3. FUTURE-PROOF ARCHITECTURE:
   - JSON metadata fields for flexible schema evolution
   - Spatial audio and high-resolution audio support
   - Multi-format audio file support including lossless formats
   - Advanced analytics and user behavior tracking

4. SUPERIOR SEARCH AND DISCOVERY:
   - Full-text search across all content
   - AI-powered relevance scoring
   - Smart playlist generation based on complex criteria
   - Mood-based music discovery

5. ENTERPRISE-GRADE PERFORMANCE:
   - Optimized indexing strategy for complex queries
   - Partitioning support for massive scale
   - Comprehensive view system for common operations
   - Stored procedures for complex business logic

6. ADVANCED ANALYTICS:
   - Daily statistics tracking for users and tracks
   - Comprehensive listening behavior analysis
   - Real-time recommendation performance tracking
   - Artist performance dashboard capabilities

SCALABILITY CONSIDERATIONS:
===========================

- All tables use BIGINT for primary keys to support massive scale
- JSON fields allow for flexible schema evolution without migrations
- Comprehensive indexing strategy optimized for music streaming queries
- Partitioning strategy for time-series data (listening history, daily stats)
- Read replica support through proper indexing and query optimization

SECURITY FEATURES:
==================

- Password hashing with bcrypt/argon2
- Two-factor authentication support
- Account verification system
- Content moderation framework
- User privacy controls

This schema represents the foundation for a music streaming platform that can
compete with and exceed the capabilities of existing major platforms while
being prepared for future innovations in music technology and user experience.
*/

-- ===============================================
-- PODCAST AND AUDIOBOOK SYSTEM
-- ===============================================

CREATE TABLE podcasts (
    podcast_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    creator_id BIGINT NOT NULL,
    description TEXT,
    cover_art_url VARCHAR(500),
    rss_feed_url VARCHAR(500),
    category_id INT,
    language VARCHAR(10),
    explicit_content BOOLEAN DEFAULT FALSE,
    total_episodes INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    average_rating DECIMAL(3,2),
    subscriber_count BIGINT DEFAULT 0,
    last_updated DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (creator_id) REFERENCES artists(artist_id),
    INDEX idx_title (title),
    FULLTEXT idx_podcast_search (title, description)
);

CREATE TABLE podcast_episodes (
    episode_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    podcast_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    duration INT NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    release_date DATETIME,
    episode_number INT,
    season_number INT DEFAULT 1,
    play_count BIGINT DEFAULT 0,
    transcript TEXT,
    chapter_markers JSON,
    
    FOREIGN KEY (podcast_id) REFERENCES podcasts(podcast_id),
    INDEX idx_release_date (release_date)
);

-- ===============================================
-- MUSIC EVENTS SYSTEM
-- ===============================================

CREATE TABLE events (
    event_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    artist_id BIGINT,
    venue_name VARCHAR(200),
    venue_location_lat DECIMAL(10,8),
    venue_location_lon DECIMAL(11,8),
    event_date DATETIME,
    doors_open_time TIME,
    ticket_url VARCHAR(500),
    price_range VARCHAR(100),
    capacity INT,
    available_tickets INT,
    description TEXT,
    poster_url VARCHAR(500),
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    
    FOREIGN KEY (artist_id) REFERENCES artists(artist_id),
    INDEX idx_event_date (event_date),
    INDEX idx_location (venue_location_lat, venue_location_lon)
);

-- ===============================================
-- VIRTUAL EVENTS SYSTEM
-- ===============================================

CREATE TABLE virtual_events (
    virtual_event_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    artist_id BIGINT NOT NULL,
    event_type ENUM('live', 'premiere', 'replay') NOT NULL,
    start_time DATETIME,
    duration INT,
    stream_url VARCHAR(1000),
    backup_stream_url VARCHAR(1000),
    ticket_price DECIMAL(10,2),
    max_viewers INT,
    current_viewers INT DEFAULT 0,
    chat_enabled BOOLEAN DEFAULT TRUE,
    recording_available BOOLEAN DEFAULT FALSE,
    recording_expiry_date DATETIME,
    
    FOREIGN KEY (artist_id) REFERENCES artists(artist_id),
    INDEX idx_start_time (start_time)
);

-- ===============================================
-- SYNCHRONIZED LYRICS AND KARAOKE SYSTEM
-- ===============================================

CREATE TABLE synchronized_lyrics (
    lyrics_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    track_id BIGINT NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    lyrics_json JSON,
    is_verified BOOLEAN DEFAULT FALSE,
    contributor_id BIGINT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (track_id) REFERENCES tracks(track_id),
    FOREIGN KEY (contributor_id) REFERENCES users(user_id)
);

CREATE TABLE karaoke_sessions (
    session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    recording_path VARCHAR(1000),
    score INT,
    duration INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (track_id) REFERENCES tracks(track_id)
);

-- ===============================================
-- AI MUSIC GENERATION SYSTEM
-- ===============================================

CREATE TABLE ai_generated_tracks (
    track_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    prompt_text TEXT,
    model_version VARCHAR(50),
    generation_parameters JSON,
    source_track_id BIGINT,
    generation_status ENUM('pending', 'processing', 'completed', 'failed'),
    processing_time INT,
    
    FOREIGN KEY (source_track_id) REFERENCES tracks(track_id)
);

-- ===============================================
-- MUSIC COLLABORATION SYSTEM
-- ===============================================

CREATE TABLE collaboration_projects (
    project_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    creator_id BIGINT NOT NULL,
    project_type ENUM('remix', 'cover', 'original', 'mashup') NOT NULL,
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    deadline DATETIME,
    description TEXT,
    source_track_id BIGINT,
    
    FOREIGN KEY (creator_id) REFERENCES users(user_id),
    FOREIGN KEY (source_track_id) REFERENCES tracks(track_id)
);

CREATE TABLE project_contributions (
    contribution_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    project_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    contribution_type ENUM('vocals', 'instrument', 'mixing', 'mastering', 'production'),
    file_path VARCHAR(1000),
    notes TEXT,
    status ENUM('submitted', 'accepted', 'rejected', 'revision_requested'),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES collaboration_projects(project_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ===============================================
-- ADVANCED MUSIC FEATURES
-- ===============================================

-- System zarządzania partyturami i nutami
CREATE TABLE sheet_music (
    sheet_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    track_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    composer_notes TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced', 'professional') NOT NULL,
    instrument_type VARCHAR(100),
    file_path VARCHAR(1000), -- PDF/MusicXML format
    preview_image_url VARCHAR(500),
    is_verified BOOLEAN DEFAULT FALSE,
    price DECIMAL(10,2),
    download_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_track (track_id),
    INDEX idx_difficulty (difficulty_level)
);

-- System lekcji muzycznych
CREATE TABLE music_lessons (
    lesson_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    instructor_id BIGINT NOT NULL,
    description TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced', 'professional') NOT NULL,
    duration INT NOT NULL, -- w minutach
    video_url VARCHAR(1000),
    price DECIMAL(10,2),
    category VARCHAR(100),
    tags JSON,
    average_rating DECIMAL(3,2),
    enrollment_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (instructor_id) REFERENCES users(user_id),
    INDEX idx_instructor (instructor_id),
    INDEX idx_difficulty (difficulty_level),
    FULLTEXT idx_search (title, description)
);

-- System instrumentów muzycznych
CREATE TABLE instruments (
    instrument_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('strings', 'woodwinds', 'brass', 'percussion', 'keyboard', 'electronic', 'other') NOT NULL,
    description TEXT,
    icon_url VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Powiązanie utworów z instrumentami
CREATE TABLE track_instruments (
    track_id BIGINT,
    instrument_id BIGINT,
    prominence_level DECIMAL(3,2) DEFAULT 1.00, -- jak wyraźny jest instrument w utworze
    
    PRIMARY KEY (track_id, instrument_id),
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    FOREIGN KEY (instrument_id) REFERENCES instruments(instrument_id) ON DELETE CASCADE,
    INDEX idx_prominence (prominence_level)
);

-- System zakładek muzycznych (timestamps w utworach)
CREATE TABLE track_bookmarks (
    bookmark_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    timestamp INT NOT NULL, -- pozycja w sekundach
    label VARCHAR(200),
    notes TEXT,
    color_code VARCHAR(7), -- hex kolor dla UI
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_user_track (user_id, track_id)
);

-- System mixów DJ-skich
CREATE TABLE dj_mixes (
    mix_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    duration INT NOT NULL,
    bpm_range VARCHAR(20), -- np. "120-128"
    mix_type ENUM('live', 'studio', 'radio', 'podcast') NOT NULL,
    genre_tags JSON,
    file_path VARCHAR(1000),
    waveform_data JSON,
    play_count BIGINT DEFAULT 0,
    like_count BIGINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    FULLTEXT idx_search (title, description)
);

-- Tracklista mixu
CREATE TABLE mix_tracklist (
    mix_id BIGINT,
    track_id BIGINT,
    position INT NOT NULL,
    start_time INT NOT NULL, -- początek utworu w mixie (sekundy)
    end_time INT NOT NULL, -- koniec utworu w mixie (sekundy)
    transition_type VARCHAR(50), -- np. "cut", "blend", "effect"
    notes TEXT,
    
    PRIMARY KEY (mix_id, track_id),
    FOREIGN KEY (mix_id) REFERENCES dj_mixes(mix_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_position (position)
);

-- System efektów dźwiękowych
CREATE TABLE sound_effects (
    effect_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    duration INT NOT NULL,
    file_path VARCHAR(1000),
    waveform_data JSON,
    tags JSON,
    license_type VARCHAR(50),
    download_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FULLTEXT idx_search (name, category)
);

-- System próbek dźwiękowych (samples)
CREATE TABLE audio_samples (
    sample_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    creator_id BIGINT,
    category VARCHAR(100),
    duration INT NOT NULL,
    bpm INT,
    key_signature VARCHAR(10),
    file_path VARCHAR(1000),
    waveform_data JSON,
    tags JSON,
    price DECIMAL(10,2),
    license_type VARCHAR(50),
    download_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (creator_id) REFERENCES users(user_id),
    FULLTEXT idx_search (name, category)
);

-- System pakietów próbek
CREATE TABLE sample_packs (
    pack_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    creator_id BIGINT,
    description TEXT,
    category VARCHAR(100),
    total_samples INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    cover_image_url VARCHAR(500),
    price DECIMAL(10,2),
    download_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (creator_id) REFERENCES users(user_id),
    FULLTEXT idx_search (name, description)
);

-- Powiązanie próbek z pakietami
CREATE TABLE pack_samples (
    pack_id BIGINT,
    sample_id BIGINT,
    
    PRIMARY KEY (pack_id, sample_id),
    FOREIGN KEY (pack_id) REFERENCES sample_packs(pack_id) ON DELETE CASCADE,
    FOREIGN KEY (sample_id) REFERENCES audio_samples(sample_id) ON DELETE CASCADE
);

-- System wyzwań muzycznych
CREATE TABLE music_challenges (
    challenge_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    challenge_type ENUM('remix', 'cover', 'composition', 'performance') NOT NULL,
    start_date DATETIME,
    end_date DATETIME,
    prize_description TEXT,
    rules TEXT,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    participant_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FULLTEXT idx_search (title, description)
);

-- Zgłoszenia do wyzwań
CREATE TABLE challenge_submissions (
    submission_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    challenge_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(1000),
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    vote_count INT DEFAULT 0,
    average_rating DECIMAL(3,2),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    FOREIGN KEY (challenge_id) REFERENCES music_challenges(challenge_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_challenge (challenge_id),
    INDEX idx_user (user_id)
);

-- System osiągnięć muzycznych
CREATE TABLE achievements (
    achievement_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    icon_url VARCHAR(500),
    points INT DEFAULT 0,
    requirements JSON, -- kryteria zdobycia osiągnięcia
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Zdobyte osiągnięcia użytkowników
CREATE TABLE user_achievements (
    user_id BIGINT,
    achievement_id BIGINT,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    progress JSON, -- postęp w osiągnięciu (dla osiągnięć progresywnych)
    
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(achievement_id) ON DELETE CASCADE,
    INDEX idx_earned_at (earned_at)
);

-- ===============================================
-- STORED PROCEDURES FOR NEW FEATURES
-- ===============================================

DELIMITER //

-- Procedura do generowania rekomendacji instrumentów
CREATE PROCEDURE recommend_instruments(
    IN p_user_id BIGINT,
    IN p_limit INT
)
BEGIN
    SELECT 
        i.*,
        COUNT(DISTINCT ti.track_id) as usage_count,
        AVG(ti.prominence_level) as avg_prominence
    FROM instruments i
    JOIN track_instruments ti ON i.instrument_id = ti.instrument_id
    JOIN tracks t ON ti.track_id = t.track_id
    JOIN user_track_interactions uti ON t.track_id = uti.track_id
    WHERE uti.user_id = p_user_id
    GROUP BY i.instrument_id
    ORDER BY usage_count DESC, avg_prominence DESC
    LIMIT p_limit;
END //

-- Procedura do analizy mixów DJ-skich
CREATE PROCEDURE analyze_dj_mix(
    IN p_mix_id BIGINT
)
BEGIN
    SELECT 
        m.*,
        COUNT(DISTINCT mt.track_id) as total_tracks,
        GROUP_CONCAT(DISTINCT g.name) as genres,
        AVG(t.bpm) as average_bpm,
        AVG(t.energy_level) as average_energy
    FROM dj_mixes m
    JOIN mix_tracklist mt ON m.mix_id = mt.mix_id
    JOIN tracks t ON mt.track_id = t.track_id
    LEFT JOIN track_genres tg ON t.track_id = tg.track_id
    LEFT JOIN genres g ON tg.genre_id = g.genre_id
    WHERE m.mix_id = p_mix_id
    GROUP BY m.mix_id;
END //

DELIMITER ;

-- ===============================================
-- TRIGGERS FOR NEW FEATURES
-- ===============================================

DELIMITER //

-- Aktualizacja statystyk pakietu próbek
CREATE TRIGGER update_sample_pack_stats_after_insert
AFTER INSERT ON pack_samples
FOR EACH ROW
BEGIN
    UPDATE sample_packs sp
    SET total_samples = (
        SELECT COUNT(*) FROM pack_samples WHERE pack_id = NEW.pack_id
    ),
    total_duration = (
        SELECT COALESCE(SUM(duration), 0) 
        FROM audio_samples as2 
        JOIN pack_samples ps ON as2.sample_id = ps.sample_id 
        WHERE ps.pack_id = NEW.pack_id
    )
    WHERE sp.pack_id = NEW.pack_id;
END //

-- Aktualizacja licznika uczestników wyzwania
CREATE TRIGGER update_challenge_participant_count_after_insert
AFTER INSERT ON challenge_submissions
FOR EACH ROW
BEGIN
    UPDATE music_challenges
    SET participant_count = (
        SELECT COUNT(DISTINCT user_id) 
        FROM challenge_submissions 
        WHERE challenge_id = NEW.challenge_id
    )
    WHERE challenge_id = NEW.challenge_id;
END //

DELIMITER ;

-- ===============================================
-- INDEXES FOR NEW FEATURES
-- ===============================================

-- Indeksy dla systemu lekcji muzycznych
CREATE INDEX idx_lesson_price_rating ON music_lessons(price, average_rating);
CREATE INDEX idx_lesson_category_difficulty ON music_lessons(category, difficulty_level);

-- Indeksy dla systemu DJ-skiego
CREATE INDEX idx_mix_type_bpm ON dj_mixes(mix_type, bpm_range);
CREATE INDEX idx_mix_popularity ON dj_mixes(play_count, like_count);

-- Indeksy dla systemu próbek
CREATE INDEX idx_sample_attributes ON audio_samples(category, bpm, key_signature);
CREATE INDEX idx_sample_downloads ON audio_samples(download_count DESC);

-- Indeksy dla wyzwań muzycznych
CREATE INDEX idx_challenge_dates ON music_challenges(start_date, end_date);
CREATE INDEX idx_challenge_status_type ON music_challenges(status, challenge_type);

-- ===============================================
-- ADVANCED AI AND SOCIAL FEATURES
-- ===============================================

-- AI DJ System
CREATE TABLE ai_dj_sessions (
    session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME,
    conversation_history JSON, -- Store chat history with AI
    mood_trajectory JSON, -- Track mood changes during session
    context_data JSON, -- Activity, location, time of day etc
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_session (user_id, start_time)
);

-- AI DJ Music Queue
CREATE TABLE ai_dj_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    position INT NOT NULL,
    confidence_score DECIMAL(3,2), -- AI confidence in selection
    selection_reason TEXT, -- Why AI chose this track
    user_feedback ENUM('like', 'dislike', 'skip', 'none') DEFAULT 'none',
    
    FOREIGN KEY (session_id) REFERENCES ai_dj_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_session_pos (session_id, position)
);

-- Voice Commands History
CREATE TABLE voice_commands (
    command_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    session_id BIGINT,
    command_text TEXT NOT NULL,
    command_type ENUM('play', 'queue', 'mood', 'info', 'other') NOT NULL,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES ai_dj_sessions(session_id) ON DELETE SET NULL,
    INDEX idx_user_time (user_id, processed_at)
);

-- Live Group Sessions (Social Listening)
CREATE TABLE group_sessions (
    group_session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200),
    host_user_id BIGINT NOT NULL,
    session_type ENUM('private', 'friends_only', 'public') DEFAULT 'private',
    max_participants INT DEFAULT 50,
    current_track_id BIGINT,
    current_position INT, -- Position in track (milliseconds)
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (host_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (current_track_id) REFERENCES tracks(track_id) ON DELETE SET NULL,
    INDEX idx_active_public (is_active, session_type)
);

-- Group Session Participants
CREATE TABLE group_session_participants (
    group_session_id BIGINT,
    user_id BIGINT,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_active_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    role ENUM('host', 'co_host', 'participant') DEFAULT 'participant',
    can_control_playback BOOLEAN DEFAULT FALSE,
    
    PRIMARY KEY (group_session_id, user_id),
    FOREIGN KEY (group_session_id) REFERENCES group_sessions(group_session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_sessions (user_id)
);

-- Group Chat Messages
CREATE TABLE group_session_chat (
    message_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    group_session_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('text', 'reaction', 'system') DEFAULT 'text',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_session_id) REFERENCES group_sessions(group_session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_session_time (group_session_id, sent_at)
);

-- Spatial Audio Tracks
CREATE TABLE spatial_audio_tracks (
    spatial_track_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    track_id BIGINT NOT NULL,
    format ENUM('dolby_atmos', 'sony_360ra', 'ambisonic') NOT NULL,
    channels INT NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    bitrate INT,
    size_mb INT,
    
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    UNIQUE KEY unique_track_format (track_id, format),
    INDEX idx_format (format)
);

-- Real-time Audio Analysis
CREATE TABLE realtime_audio_features (
    feature_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    track_id BIGINT NOT NULL,
    timestamp_ms INT NOT NULL, -- Millisecond position in track
    energy_level DECIMAL(4,3),
    beat_strength DECIMAL(4,3),
    harmonic_change DECIMAL(4,3),
    timbre_description JSON,
    
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_track_time (track_id, timestamp_ms)
);

-- Virtual Studio Projects
CREATE TABLE virtual_studio_projects (
    project_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    creator_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    project_type ENUM('original', 'remix', 'cover', 'mashup') NOT NULL,
    bpm INT,
    key_signature VARCHAR(10),
    is_public BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id),
    INDEX idx_public (is_public)
);

-- Virtual Studio Tracks
CREATE TABLE virtual_studio_tracks (
    studio_track_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    project_id BIGINT NOT NULL,
    track_type ENUM('audio', 'midi', 'virtual_instrument') NOT NULL,
    name VARCHAR(100) NOT NULL,
    file_path VARCHAR(1000),
    instrument_preset VARCHAR(100),
    volume DECIMAL(5,2) DEFAULT 0.00,
    pan DECIMAL(3,2) DEFAULT 0.00,
    muted BOOLEAN DEFAULT FALSE,
    soloed BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (project_id) REFERENCES virtual_studio_projects(project_id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
);

-- AI Music Generation
CREATE TABLE ai_music_generations (
    generation_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    prompt_text TEXT NOT NULL,
    style_reference_track_id BIGINT,
    generation_parameters JSON,
    output_file_path VARCHAR(1000),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (style_reference_track_id) REFERENCES tracks(track_id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status)
);

-- ===============================================
-- STORED PROCEDURES FOR NEW FEATURES
-- ===============================================

DELIMITER //

-- Start a new AI DJ session
CREATE PROCEDURE start_ai_dj_session(
    IN p_user_id BIGINT,
    IN p_initial_context JSON
)
BEGIN
    INSERT INTO ai_dj_sessions (user_id, context_data)
    VALUES (p_user_id, p_initial_context);
    
    SELECT LAST_INSERT_ID() as session_id;
END //

-- Add track to AI DJ queue with explanation
CREATE PROCEDURE queue_ai_dj_track(
    IN p_session_id BIGINT,
    IN p_track_id BIGINT,
    IN p_confidence DECIMAL(3,2),
    IN p_reason TEXT
)
BEGIN
    DECLARE v_position INT;
    
    -- Get next position in queue
    SELECT COALESCE(MAX(position), 0) + 1
    INTO v_position
    FROM ai_dj_queue
    WHERE session_id = p_session_id;
    
    INSERT INTO ai_dj_queue (
        session_id, track_id, position,
        confidence_score, selection_reason
    )
    VALUES (
        p_session_id, p_track_id, v_position,
        p_confidence, p_reason
    );
END //

-- Create a new group listening session
CREATE PROCEDURE create_group_session(
    IN p_host_user_id BIGINT,
    IN p_name VARCHAR(200),
    IN p_session_type ENUM('private', 'friends_only', 'public'),
    IN p_max_participants INT
)
BEGIN
    INSERT INTO group_sessions (
        host_user_id, name, session_type, max_participants
    )
    VALUES (
        p_host_user_id, p_name, p_session_type, p_max_participants
    );
    
    -- Add host as first participant
    INSERT INTO group_session_participants (
        group_session_id, user_id, role, can_control_playback
    )
    VALUES (
        LAST_INSERT_ID(), p_host_user_id, 'host', TRUE
    );
    
    SELECT LAST_INSERT_ID() as session_id;
END //

DELIMITER ;

-- ===============================================
-- TRIGGERS FOR NEW FEATURES
-- ===============================================

DELIMITER //

-- Update group session participant last active timestamp
CREATE TRIGGER update_participant_last_active
BEFORE UPDATE ON group_session_participants
FOR EACH ROW
BEGIN
    SET NEW.last_active_at = CURRENT_TIMESTAMP;
END //

-- Clean up inactive group sessions
CREATE EVENT cleanup_inactive_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    UPDATE group_sessions
    SET is_active = FALSE
    WHERE is_active = TRUE
    AND NOT EXISTS (
        SELECT 1 FROM group_session_participants
        WHERE group_session_id = group_sessions.group_session_id
        AND last_active_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    );
END //

DELIMITER ;

-- ===============================================
-- INDEXES FOR NEW FEATURES
-- ===============================================

-- Indeksy dla systemu lekcji muzycznych
CREATE INDEX idx_lesson_price_rating ON music_lessons(price, average_rating);
CREATE INDEX idx_lesson_category_difficulty ON music_lessons(category, difficulty_level);

-- Indeksy dla systemu DJ-skiego
CREATE INDEX idx_mix_type_bpm ON dj_mixes(mix_type, bpm_range);
CREATE INDEX idx_mix_popularity ON dj_mixes(play_count, like_count);

-- Indeksy dla systemu próbek
CREATE INDEX idx_sample_attributes ON audio_samples(category, bpm, key_signature);
CREATE INDEX idx_sample_downloads ON audio_samples(download_count DESC);

-- Indeksy dla wyzwań muzycznych
CREATE INDEX idx_challenge_dates ON music_challenges(start_date, end_date);
CREATE INDEX idx_challenge_status_type ON music_challenges(status, challenge_type);

-- ===============================================
-- SYSTEM ZARZĄDZANIA URZĄDZENIAMI I SYNCHRONIZACJĄ
-- ===============================================

-- Tabela urządzeń użytkownika
CREATE TABLE user_devices (
    device_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'web', 'smart_tv', 'speaker', 'car') NOT NULL,
    device_model VARCHAR(100),
    os_type VARCHAR(50),
    os_version VARCHAR(50),
    app_version VARCHAR(50),
    device_token VARCHAR(255) UNIQUE, -- do identyfikacji urządzenia
    last_active_at DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_devices (user_id, is_active),
    INDEX idx_device_token (device_token)
);

-- Stan odtwarzania na urządzeniu
CREATE TABLE device_playback_states (
    state_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    device_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    track_id BIGINT,
    playlist_id BIGINT,
    queue_position INT,
    playback_position_ms INT DEFAULT 0,
    is_playing BOOLEAN DEFAULT FALSE,
    volume_level INT DEFAULT 100,
    repeat_mode ENUM('off', 'track', 'context') DEFAULT 'off',
    shuffle_mode BOOLEAN DEFAULT FALSE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES user_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE SET NULL,
    FOREIGN KEY (playlist_id) REFERENCES playlists(playlist_id) ON DELETE SET NULL,
    INDEX idx_user_device (user_id, device_id)
);

-- Kolejka odtwarzania na urządzeniu
CREATE TABLE device_playback_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    device_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    track_id BIGINT NOT NULL,
    position INT NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_id) REFERENCES user_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE CASCADE,
    INDEX idx_device_position (device_id, position)
);

-- Historia transferów odtwarzania
CREATE TABLE playback_transfers (
    transfer_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    from_device_id BIGINT NOT NULL,
    to_device_id BIGINT NOT NULL,
    track_id BIGINT,
    transfer_position_ms INT,
    queue_state JSON, -- zachowanie stanu kolejki
    transfer_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (from_device_id) REFERENCES user_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (to_device_id) REFERENCES user_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(track_id) ON DELETE SET NULL,
    INDEX idx_user_transfer (user_id, transfer_time)
);

-- ===============================================
-- PROCEDURY DLA ZARZĄDZANIA URZĄDZENIAMI
-- ===============================================

DELIMITER //

-- Rejestracja nowego urządzenia
CREATE PROCEDURE register_device(
    IN p_user_id BIGINT,
    IN p_device_name VARCHAR(100),
    IN p_device_type VARCHAR(50),
    IN p_device_model VARCHAR(100),
    IN p_os_type VARCHAR(50),
    IN p_os_version VARCHAR(50),
    IN p_app_version VARCHAR(50)
)
BEGIN
    INSERT INTO user_devices (
        user_id, device_name, device_type, device_model,
        os_type, os_version, app_version, device_token
    )
    VALUES (
        p_user_id, p_device_name, p_device_type, p_device_model,
        p_os_type, p_os_version, p_app_version, 
        UUID() -- generowanie unikalnego tokenu
    );
    
    SELECT LAST_INSERT_ID() as device_id;
END //

-- Transfer odtwarzania między urządzeniami
CREATE PROCEDURE transfer_playback(
    IN p_user_id BIGINT,
    IN p_from_device_id BIGINT,
    IN p_to_device_id BIGINT
)
BEGIN
    DECLARE v_track_id BIGINT;
    DECLARE v_position_ms INT;
    DECLARE v_queue_state JSON;
    
    -- Pobierz aktualny stan odtwarzania
    SELECT track_id, playback_position_ms
    INTO v_track_id, v_position_ms
    FROM device_playback_states
    WHERE device_id = p_from_device_id AND user_id = p_user_id;
    
    -- Pobierz stan kolejki
    SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'track_id', track_id,
            'position', position
        )
    ) INTO v_queue_state
    FROM device_playback_queue
    WHERE device_id = p_from_device_id
    ORDER BY position;
    
    -- Zapisz transfer w historii
    INSERT INTO playback_transfers (
        user_id, from_device_id, to_device_id,
        track_id, transfer_position_ms, queue_state
    )
    VALUES (
        p_user_id, p_from_device_id, p_to_device_id,
        v_track_id, v_position_ms, v_queue_state
    );
    
    -- Zaktualizuj stan na nowym urządzeniu
    INSERT INTO device_playback_states (
        device_id, user_id, track_id,
        playback_position_ms, is_playing
    )
    VALUES (
        p_to_device_id, p_user_id, v_track_id,
        v_position_ms, TRUE
    )
    ON DUPLICATE KEY UPDATE
        track_id = v_track_id,
        playback_position_ms = v_position_ms,
        is_playing = TRUE;
    
    -- Zatrzymaj odtwarzanie na poprzednim urządzeniu
    UPDATE device_playback_states
    SET is_playing = FALSE
    WHERE device_id = p_from_device_id;
    
    -- Przenieś kolejkę odtwarzania
    DELETE FROM device_playback_queue WHERE device_id = p_to_device_id;
    INSERT INTO device_playback_queue (device_id, user_id, track_id, position)
    SELECT p_to_device_id, user_id, track_id, position
    FROM device_playback_queue
    WHERE device_id = p_from_device_id;
END //

DELIMITER ;