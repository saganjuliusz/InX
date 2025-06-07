<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

if (isset($_GET['artist_id'])) {
    // Pobierz statystyki dla konkretnego artysty
    $artistId = (int)$_GET['artist_id'];
    
    // Pobierz podstawowe informacje o artyście
    $stmt = $conn->prepare("
        SELECT * FROM artist_performance_stats
        WHERE artist_id = ?
    ");
    
    $stmt->bind_param("i", $artistId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();

    if (!$stats) {
        echo json_encode(['error' => 'Artist not found']);
        exit;
    }

    // Pobierz trendy słuchalności w czasie
    $stmt = $conn->prepare("
        SELECT 
            DATE(lh.played_at) as date,
            COUNT(DISTINCT lh.user_id) as unique_listeners,
            COUNT(*) as total_plays
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        WHERE t.artist_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY DATE(lh.played_at)
        ORDER BY date
    ");
    
    $stmt->bind_param("i", $artistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $listeningTrends = [];
    while ($row = $result->fetch_assoc()) {
        $listeningTrends[] = [
            'date' => $row['date'],
            'unique_listeners' => (int)$row['unique_listeners'],
            'total_plays' => (int)$row['total_plays']
        ];
    }

    // Pobierz najpopularniejsze utwory
    $stmt = $conn->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.play_count,
            t.like_count,
            al.title as album_title,
            al.cover_art_url
        FROM tracks t
        LEFT JOIN albums al ON t.album_id = al.album_id
        WHERE t.artist_id = ?
        ORDER BY t.play_count DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("i", $artistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $topTracks = [];
    while ($row = $result->fetch_assoc()) {
        $topTracks[] = [
            'id' => $row['track_id'],
            'title' => $row['title'],
            'play_count' => (int)$row['play_count'],
            'like_count' => (int)$row['like_count'],
            'album' => [
                'title' => $row['album_title'],
                'cover_art_url' => $row['cover_art_url']
            ]
        ];
    }

    // Pobierz demografię słuchaczy
    $stmt = $conn->prepare("
        SELECT 
            u.country_code,
            COUNT(DISTINCT lh.user_id) as listener_count
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        JOIN users u ON lh.user_id = u.user_id
        WHERE t.artist_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY u.country_code
        ORDER BY listener_count DESC
        LIMIT 10
    ");
    
    $stmt->bind_param("i", $artistId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $demographics = [];
    while ($row = $result->fetch_assoc()) {
        $demographics[] = [
            'country_code' => $row['country_code'],
            'listener_count' => (int)$row['listener_count']
        ];
    }

    echo json_encode([
        'artist_stats' => [
            'total_tracks' => (int)$stats['total_tracks'],
            'total_plays' => (int)$stats['total_plays'],
            'total_likes' => (int)$stats['total_likes'],
            'follower_count' => (int)$stats['follower_count'],
            'average_rating' => round($stats['average_rating'], 2)
        ],
        'listening_trends' => $listeningTrends,
        'top_tracks' => $topTracks,
        'demographics' => $demographics
    ]);
} else {
    // Pobierz ranking artystów
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
    
    $result = $conn->query("
        SELECT * FROM artist_performance_stats
        ORDER BY total_plays DESC
        LIMIT $offset, $limit
    ");
    
    $artists = [];
    while ($row = $result->fetch_assoc()) {
        $artists[] = [
            'id' => $row['artist_id'],
            'name' => $row['name'],
            'total_tracks' => (int)$row['total_tracks'],
            'total_plays' => (int)$row['total_plays'],
            'follower_count' => (int)$row['follower_count'],
            'average_rating' => round($row['average_rating'], 2)
        ];
    }

    echo json_encode(['artists' => $artists]);
}

$conn->close(); 