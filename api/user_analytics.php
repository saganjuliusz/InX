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

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$timeRange = isset($_GET['time_range']) ? $_GET['time_range'] : 'week'; // week, month, year, all

if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Określ zakres dat na podstawie parametru time_range
$dateRange = "";
switch ($timeRange) {
    case 'week':
        $dateRange = "AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateRange = "AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
        break;
    case 'year':
        $dateRange = "AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
        break;
    case 'all':
        $dateRange = "";
        break;
    default:
        $dateRange = "AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
}

// Pobierz statystyki słuchania
$stmt = $conn->prepare("
    SELECT 
        SUM(total_listening_time) as total_time,
        SUM(tracks_played) as total_tracks,
        SUM(unique_tracks) as unique_tracks,
        SUM(unique_artists) as unique_artists,
        SUM(skips) as total_skips,
        SUM(likes) as total_likes,
        SUM(playlist_additions) as playlist_adds,
        SUM(new_tracks_discovered) as discoveries
    FROM daily_user_stats
    WHERE user_id = ? $dateRange
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

// Pobierz ulubione gatunki
$stmt = $conn->prepare("
    SELECT g.name, COUNT(*) as count
    FROM listening_history lh
    JOIN tracks t ON lh.track_id = t.track_id
    JOIN track_genres tg ON t.track_id = tg.track_id
    JOIN genres g ON tg.genre_id = g.genre_id
    WHERE lh.user_id = ? 
    AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY g.genre_id
    ORDER BY count DESC
    LIMIT 5
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$topGenres = [];
while ($row = $result->fetch_assoc()) {
    $topGenres[] = [
        'name' => $row['name'],
        'count' => (int)$row['count']
    ];
}

// Pobierz ulubione nastroje
$stmt = $conn->prepare("
    SELECT m.name, m.emoji, COUNT(*) as count
    FROM listening_history lh
    JOIN tracks t ON lh.track_id = t.track_id
    JOIN track_moods tm ON t.track_id = tm.track_id
    JOIN moods m ON tm.mood_id = m.mood_id
    WHERE lh.user_id = ?
    AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY m.mood_id
    ORDER BY count DESC
    LIMIT 5
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$topMoods = [];
while ($row = $result->fetch_assoc()) {
    $topMoods[] = [
        'name' => $row['name'],
        'emoji' => $row['emoji'],
        'count' => (int)$row['count']
    ];
}

// Pobierz aktywność słuchania według pory dnia
$stmt = $conn->prepare("
    SELECT 
        HOUR(played_at) as hour,
        COUNT(*) as count
    FROM listening_history
    WHERE user_id = ?
    AND played_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY HOUR(played_at)
    ORDER BY hour
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$listeningHours = [];
while ($row = $result->fetch_assoc()) {
    $listeningHours[] = [
        'hour' => (int)$row['hour'],
        'count' => (int)$row['count']
    ];
}

// Przygotuj odpowiedź
$response = [
    'listening_stats' => [
        'total_time' => (int)$stats['total_time'],
        'total_tracks' => (int)$stats['total_tracks'],
        'unique_tracks' => (int)$stats['unique_tracks'],
        'unique_artists' => (int)$stats['unique_artists'],
        'total_skips' => (int)$stats['total_skips'],
        'total_likes' => (int)$stats['total_likes'],
        'playlist_additions' => (int)$stats['playlist_adds'],
        'new_discoveries' => (int)$stats['discoveries']
    ],
    'top_genres' => $topGenres,
    'top_moods' => $topMoods,
    'listening_hours' => $listeningHours,
    'time_range' => $timeRange
];

echo json_encode($response);

$conn->close(); 